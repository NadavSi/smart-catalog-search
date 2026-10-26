<?php

namespace App\Providers;

use App\Catalog\CsvCatalogReader;
use App\Contracts\Embedder;
use App\Search\ProductSearch;
use App\Search\QueryExpander;
use App\Services\Embeddings\TransformersEmbedder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: the model is loaded once per worker (see config/octane.php `warm`).
        $this->app->singleton(Embedder::class, fn () => new TransformersEmbedder(
            model: config('catalog.embeddings.model'),
            cacheDir: config('catalog.embeddings.cache_dir'),
            pooling: config('catalog.embeddings.pooling'),
            queryPrefix: config('catalog.embeddings.query_prefix'),
            documentPrefix: config('catalog.embeddings.document_prefix'),
            batchSize: config('catalog.embeddings.batch_size'),
        ));

        $this->app->bind(ProductSearch::class, fn ($app) => new ProductSearch(
            $app->make(Embedder::class),
            new QueryExpander(config('catalog.synonyms')),
            config('catalog.search'),
        ));

        $this->app->bind(CsvCatalogReader::class, fn () => new CsvCatalogReader(
            config('catalog.csv.columns'),
            config('catalog.csv.required'),
        ));
    }

    public function boot(): void
    {
        // Every search runs the embedding model, so cap it per client.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
