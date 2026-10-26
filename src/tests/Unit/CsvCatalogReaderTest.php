<?php

namespace Tests\Unit;

use App\Catalog\CsvCatalogReader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CsvCatalogReaderTest extends TestCase
{
    private function reader(): CsvCatalogReader
    {
        return $this->app->make(CsvCatalogReader::class);
    }

    public function test_reads_valid_rows_and_skips_invalid_ones(): void
    {
        $reader = $this->reader();
        $products = iterator_to_array($reader->read(__DIR__.'/../Fixtures/products.csv'), false);

        $this->assertCount(6, $products);
        $this->assertSame('Apple iPhone 16 128GB שחור', $products[0]->name);
        $this->assertSame(3799.0, $products[0]->price);
        $this->assertSame(449.9, $products[2]->price);
        $this->assertNull($products[2]->imageUrl, 'empty image stays null');
        $this->assertNull($products[4]->imageUrl, 'invalid URL is dropped');
        $this->assertNull($products[5]->price, 'a missing price is allowed');
        $this->assertSame([7 => 'empty name', 9 => 'invalid price "חינם"'], $reader->skipped());
    }

    public function test_matches_headers_by_alias_and_keeps_unknown_columns(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "\xEF\xBB\xBFשם מוצר;מחיר;צבע\nכיסא;199;אדום\n");

        $products = iterator_to_array($this->reader()->read($path), false);
        unlink($path);

        $this->assertSame('כיסא', $products[0]->name);
        $this->assertSame(199.0, $products[0]->price);
        $this->assertSame(['צבע' => 'אדום'], $products[0]->extra);
        $this->assertNotEmpty($products[0]->externalId, 'an id is derived when the CSV has none');
    }

    public function test_rejects_csv_without_required_columns(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "colour,size\nred,L\n");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name');
        try {
            iterator_to_array($this->reader()->read($path));
        } finally {
            unlink($path);
        }
    }

    #[DataProvider('prices')]
    public function test_parses_prices(string $input, float $expected): void
    {
        $this->assertSame($expected, CsvCatalogReader::parsePrice($input));
    }

    public static function prices(): array
    {
        return [
            ['1,299.90', 1299.9],
            ['₪ 1299', 1299.0],
            ['1299 ש"ח', 1299.0],
            ['49,90', 49.9],
            ['12,500', 12500.0],
        ];
    }
}
