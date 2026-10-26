<?php

namespace Tests\Support;

use App\Contracts\Embedder;
use App\Support\TextNormalizer;

/**
 * Deterministic stand-in for the real model: a bag-of-words vector where each
 * token hashes to a dimension. Texts that share words come out similar, which
 * is enough to test ranking and wiring without loading a 100MB model.
 */
class FakeEmbedder implements Embedder
{
    public function __construct(private readonly int $dimensions = 384) {}

    public function embedQuery(string $text): array
    {
        return $this->vector($text);
    }

    public function embedDocuments(array $texts): array
    {
        return array_map($this->vector(...), $texts);
    }

    private function vector(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);
        foreach (preg_split('/[^\p{L}\p{N}]+/u', TextNormalizer::normalize($text), -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $vector[crc32($token) % $this->dimensions] += 1.0;
        }
        // A small constant component so no vector is all-zero (cosine is undefined there).
        $vector[0] += 0.01;
        $length = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vector)));

        return array_map(fn ($x) => $x / $length, $vector);
    }
}
