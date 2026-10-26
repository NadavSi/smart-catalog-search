<?php

namespace App\Listeners;

use App\Contracts\Embedder;
use App\Contracts\Reranker;
use App\Services\Embeddings\TransformersEmbedder;
use App\Services\Reranking\TransformersReranker;
use Laravel\Octane\Events\WorkerStarting;

/** Loads the models when an Octane worker boots, not on its first search. */
class WarmEmbeddingModel
{
    public function handle(WorkerStarting $event): void
    {
        $embedder = $event->app->make(Embedder::class);

        if ($embedder instanceof TransformersEmbedder) {
            $embedder->warm();
        }

        $reranker = $event->app->make(Reranker::class);
        if (config('catalog.search.rerank') && $reranker instanceof TransformersReranker) {
            $reranker->warm();
        }
    }
}
