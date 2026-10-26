<?php

namespace App\Console\Commands;

use App\Contracts\Embedder;
use App\Services\Embeddings\TransformersEmbedder;
use Illuminate\Console\Command;

class DownloadModel extends Command
{
    protected $signature = 'catalog:download-model';

    protected $description = 'Download the embedding model into storage/models (run at image build time)';

    public function handle(Embedder $embedder): int
    {
        if ($embedder instanceof TransformersEmbedder) {
            $embedder->warm();
        }
        $this->info('Embedding model ready: '.config('catalog.embeddings.model'));

        return self::SUCCESS;
    }
}
