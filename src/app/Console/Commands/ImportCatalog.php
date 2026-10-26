<?php

namespace App\Console\Commands;

use App\Catalog\CatalogImporter;
use App\Catalog\CsvCatalogReader;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportCatalog extends Command
{
    protected $signature = 'catalog:import
        {path=data/products.csv : CSV file, relative to the project root}
        {--prune : Delete products that are not in this CSV}';

    protected $description = 'Load a product CSV into the database (embeds every product; safe to re-run)';

    public function handle(CsvCatalogReader $reader, CatalogImporter $importer): int
    {
        $path = $this->argument('path');
        $path = str_starts_with($path, '/') ? $path : base_path($path);

        $this->info("Importing {$path} (the first run loads the embedding model, give it a few seconds)...");
        $started = microtime(true);

        try {
            $result = $importer->import(
                $reader->read($path),
                prune: $this->option('prune'),
                onProgress: fn (int $done) => $this->line("  {$done} products embedded"),
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($reader->skipped() as $line => $reason) {
            $this->warn("  skipped line {$line}: {$reason}");
        }

        $this->info(sprintf(
            'Done in %.1fs: %d imported, %d skipped, %d pruned.',
            microtime(true) - $started, $result['imported'], count($reader->skipped()), $result['pruned'],
        ));

        return self::SUCCESS;
    }
}
