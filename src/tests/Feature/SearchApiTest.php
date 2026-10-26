<?php

namespace Tests\Feature;

use App\Catalog\CatalogImporter;
use App\Catalog\CsvCatalogReader;
use App\Contracts\Embedder;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeEmbedder;
use Tests\TestCase;

/** Runs the real hybrid SQL against Postgres + pgvector, with a fake embedding model. */
class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Embedder::class, new FakeEmbedder(config('catalog.embeddings.dimensions')));
        // The fake model's cosine scale differs from e5's.
        config(['catalog.search.min_margin' => 0.2, 'catalog.search.max_gap' => 0.5]);

        $this->app->make(CatalogImporter::class)->import(
            $this->app->make(CsvCatalogReader::class)->read(base_path('tests/Fixtures/products.csv'))
        );
    }

    public function test_import_is_idempotent(): void
    {
        $this->assertSame(5, Product::count());

        $this->app->make(CatalogImporter::class)->import(
            $this->app->make(CsvCatalogReader::class)->read(base_path('tests/Fixtures/products.csv'))
        );

        $this->assertSame(5, Product::count());
    }

    public function test_finds_product_by_name(): void
    {
        $this->getJson('/api/search?q=iphone 16')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Apple iPhone 16 128GB שחור')
            ->assertJsonPath('data.0.price', 3799)
            ->assertJsonStructure(['data' => [['id', 'name', 'price', 'image_url']], 'meta' => ['query', 'count', 'took_ms']]);
    }

    public function test_finds_hebrew_query(): void
    {
        $this->getJson('/api/search?q='.urlencode('שואב אבק'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'שואב אבק רובוטי Roborock S8');
    }

    public function test_lexical_match_tolerates_typos(): void
    {
        $this->getJson('/api/search?q=roborok')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'שואב אבק רובוטי Roborock S8');
    }

    public function test_returns_empty_list_for_unrelated_query(): void
    {
        $this->getJson('/api/search?q=zzzqqq')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.count', 0);
    }

    public function test_validates_query(): void
    {
        $this->getJson('/api/search?q=')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/api/search?q='.str_repeat('a', 201))->assertUnprocessable();
    }

    public function test_does_not_expose_internal_columns(): void
    {
        $product = $this->getJson('/api/search?q=iphone')->json('data.0');

        $this->assertArrayNotHasKey('embedding', $product);
        $this->assertArrayNotHasKey('search_text', $product);
    }

    public function test_search_page_renders_rtl(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('product-card-template', false);
    }
}
