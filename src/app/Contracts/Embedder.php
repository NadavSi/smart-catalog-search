<?php

namespace App\Contracts;

/**
 * Turns text into unit-length vectors. Queries and documents are separate
 * calls because retrieval models (e5 and others) encode them differently.
 */
interface Embedder
{
    /** @return float[] */
    public function embedQuery(string $text): array;

    /**
     * @param  string[]  $texts
     * @return float[][]
     */
    public function embedDocuments(array $texts): array;
}
