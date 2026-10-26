<?php

namespace App\Contracts;

/**
 * Scores how well each document answers a query by reading the two together
 * (a cross-encoder). Slower than comparing embeddings, but much better at
 * telling a near miss ("vacuum storage bag") from the real thing.
 */
interface Reranker
{
    /**
     * @param  string[]  $documents
     * @return float[] one relevance logit per document, same order; higher is more relevant
     */
    public function score(string $query, array $documents): array;
}
