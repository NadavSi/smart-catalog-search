<?php

namespace App\Console\Commands;

use App\Contracts\Embedder;
use App\Contracts\Reranker;
use App\Services\Embeddings\TransformersEmbedder;
use App\Services\Reranking\TransformersReranker;
use Illuminate\Console\Command;

class DownloadModel extends Command
{
    protected $signature = 'catalog:download-model';

    protected $description = 'Download the embedding and re-ranking models into storage/models (run at image build time)';

    public function handle(Embedder $embedder, Reranker $reranker): int
    {
        if ($embedder instanceof TransformersEmbedder) {
            $embedder->warm();
        }
        $this->info('Embedding model ready: '.config('catalog.embeddings.model'));

        // Downloaded even while search.rerank is off, so turning it on is a config change.
        if ($reranker instanceof TransformersReranker) {
            $reranker->warm();
        }
        $this->info('Re-ranking model ready: '.config('catalog.reranker.model'));

        return self::SUCCESS;
    }
}
