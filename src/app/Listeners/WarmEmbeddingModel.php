<?php

namespace App\Listeners;

use App\Contracts\Embedder;
use App\Services\Embeddings\TransformersEmbedder;
use Laravel\Octane\Events\WorkerStarting;

/** Loads the embedding model when an Octane worker boots, not on its first search. */
class WarmEmbeddingModel
{
    public function handle(WorkerStarting $event): void
    {
        $embedder = $event->app->make(Embedder::class);

        if ($embedder instanceof TransformersEmbedder) {
            $embedder->warm();
        }
    }
}
