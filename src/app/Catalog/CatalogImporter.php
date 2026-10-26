<?php

namespace App\Catalog;

use App\Contracts\Embedder;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Vector;

/**
 * Embeds products and upserts them by external_id, so re-running an import
 * updates the catalog in place instead of duplicating it.
 */
class CatalogImporter
{
    public function __construct(private readonly Embedder $embedder) {}

    /**
     * @param  iterable<ProductData>  $products
     * @param  bool  $prune  delete products that are not in this import
     * @param  callable(int $done): void|null  $onProgress
     * @return array{imported: int, pruned: int}
     */
    public function import(iterable $products, int $batchSize = 64, bool $prune = false, ?callable $onProgress = null): array
    {
        $seen = [];
        $batch = [];
        $done = 0;

        $flush = function () use (&$batch, &$done, &$seen, $onProgress) {
            if (! $batch) {
                return;
            }
            $this->upsert($batch);
            $done += count($batch);
            array_push($seen, ...array_map(fn (ProductData $p) => $p->externalId, array_values($batch)));
            $batch = [];
            $onProgress && $onProgress($done);
        };

        foreach ($products as $product) {
            // Duplicate IDs inside one batch would make the upsert fail; last row wins.
            $batch[$product->externalId] = $product;
            if (count($batch) >= $batchSize) {
                $flush();
            }
        }
        $flush();

        $pruned = $prune && $seen ? Product::whereNotIn('external_id', $seen)->delete() : 0;

        return ['imported' => count(array_unique($seen)), 'pruned' => $pruned];
    }

    /** @param  ProductData[]  $batch */
    private function upsert(array $batch): void
    {
        $batch = array_values($batch);
        $vectors = $this->embedder->embedDocuments(array_map(fn (ProductData $p) => $p->embeddingText(), $batch));
        $now = now();

        $rows = array_map(fn (ProductData $p, array $vector) => [
            'external_id' => $p->externalId,
            'name' => $p->name,
            'description' => $p->description,
            'category' => $p->category,
            'brand' => $p->brand,
            'price' => $p->price,
            'image_url' => $p->imageUrl,
            'extra' => json_encode($p->extra, JSON_UNESCAPED_UNICODE),
            'search_text' => $p->searchText(),
            'embedding' => (string) new Vector($vector),
            'created_at' => $now,
            'updated_at' => $now,
        ], $batch, $vectors);

        DB::transaction(fn () => Product::upsert(
            $rows,
            ['external_id'],
            ['name', 'description', 'category', 'brand', 'price', 'image_url', 'extra', 'search_text', 'embedding', 'updated_at'],
        ));
    }
}
