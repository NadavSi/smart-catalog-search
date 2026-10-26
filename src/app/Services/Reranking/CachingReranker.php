<?php

namespace App\Services\Reranking;

use App\Contracts\Reranker;

/**
 * Memoizes scores for the life of the object. Used by `catalog:eval --sweep`,
 * where every run re-scores the same (query, product) pairs. Unbounded, so
 * not for the web workers.
 */
class CachingReranker implements Reranker
{
    /** @var array<string, float> */
    private array $cache = [];

    public function __construct(private readonly Reranker $inner) {}

    public function score(string $query, array $documents): array
    {
        $keys = array_map(fn ($doc) => md5($query."\0".$doc), $documents);
        $missing = array_values(array_unique(array_filter($keys, fn ($k) => ! isset($this->cache[$k]))));

        if ($missing) {
            $byKey = array_combine($keys, $documents);
            $scores = $this->inner->score($query, array_map(fn ($k) => $byKey[$k], $missing));
            $this->cache = array_merge($this->cache, array_combine($missing, $scores));
        }

        return array_map(fn ($k) => $this->cache[$k], $keys);
    }
}
