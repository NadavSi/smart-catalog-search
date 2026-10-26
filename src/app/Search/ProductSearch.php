<?php

namespace App\Search;

use App\Contracts\Embedder;
use App\Models\Product;
use App\Support\TextNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Vector;

/**
 * Hybrid search: semantic similarity (pgvector cosine over sentence
 * embeddings) blended with lexical similarity (pg_trgm word_similarity over
 * the normalized product text).
 *
 * Semantic covers "a gift for someone who loves to cook"; lexical keeps exact
 * names and models ("iPhone 16", brand names) on top, where embeddings are
 * fuzzy.
 *
 * Deciding that nothing matches: raw cosine is a poor signal (a nonsense
 * query still scores ~0.4 against everything), so a product must stand out
 * from the catalog as a whole. Its similarity minus the query's mean
 * similarity to all products must reach min_margin. With unit vectors that
 * mean is just the dot product with the catalog centroid.
 */
class ProductSearch
{
    public function __construct(
        private readonly Embedder $embedder,
        private readonly QueryExpander $expander,
        private readonly array $config,
    ) {}

    /** @return Collection<int, Product> each with `score`, `semantic`, `margin` and `lexical` attributes */
    public function search(string $query): Collection
    {
        $text = TextNormalizer::normalize($query);
        if ($text === '') {
            return collect();
        }

        // "אייפון 16" → also "iphone 16": the model sees both, trigrams take the best.
        $variants = $this->expander->variants($text);
        $vector = (string) new Vector($this->embedder->embedQuery(implode(' / ', $variants)));
        $lexical = 'GREATEST('.implode(', ', array_fill(0, count($variants), 'word_similarity(?, search_text)')).')';
        $c = $this->config;

        // <#> is negative inner product; avg() of the vectors is the centroid.
        $baseline = (float) DB::selectOne('SELECT -(avg(embedding) <#> ?::vector) AS b FROM products', [$vector])?->b;

        // Candidates: nearest neighbours by vector (uses the HNSW index), plus
        // anything the trigram operator matches (uses the GIN index).
        $products = Product::query()
            ->select('products.*')
            ->selectRaw('1 - (embedding <=> ?::vector) AS semantic', [$vector])
            ->selectRaw("{$lexical} AS lexical", $variants)
            ->where(fn ($q) => $q
                ->whereIn('id', fn ($sub) => $sub->select('id')->from('products')
                    ->orderByRaw('embedding <=> ?::vector', [$vector])
                    ->limit($c['candidates']))
                ->orWhere(function ($q) use ($variants) {
                    foreach ($variants as $variant) {
                        $q->orWhereRaw('? <% search_text', [$variant]);
                    }
                }))
            ->get();

        foreach ($products as $product) {
            $product->margin = $product->semantic - $baseline;
            $product->score = $c['semantic_weight'] * $product->semantic + $c['lexical_weight'] * $product->lexical;
        }

        $relevant = $products
            ->filter(fn ($p) => $p->margin >= $c['min_margin'] || $p->lexical >= $c['min_lexical'])
            ->sortByDesc('score')
            ->values();

        $best = $relevant->first()?->score;

        return $relevant
            ->filter(fn ($p) => $p->score >= $best - $c['max_gap'])
            ->take($c['limit'])
            ->values();
    }
}
