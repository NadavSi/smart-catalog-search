<?php

namespace Tests\Support;

use App\Contracts\Reranker;
use App\Support\TextNormalizer;

/**
 * Deterministic stand-in for the cross-encoder: the logit is the number of
 * query words found in the document, minus 1. No shared word scores -1.
 */
class FakeReranker implements Reranker
{
    /** @var array<int, array{string, string[]}> every call, for assertions */
    public array $calls = [];

    public function score(string $query, array $documents): array
    {
        $this->calls[] = [$query, $documents];
        $words = fn (string $text) => array_unique(preg_split('/[^\p{L}\p{N}]+/u', TextNormalizer::normalize($text), -1, PREG_SPLIT_NO_EMPTY));
        $queryWords = $words($query);

        return array_map(fn ($doc) => count(array_intersect($queryWords, $words($doc))) - 1.0, $documents);
    }
}
