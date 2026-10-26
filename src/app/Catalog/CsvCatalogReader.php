<?php

namespace App\Catalog;

use App\Support\TextNormalizer;
use Generator;
use InvalidArgumentException;
use SplFileObject;

/**
 * Streams a product CSV as ProductData. Columns are found by header name
 * (see config/catalog.php), so the column order and naming can vary.
 */
class CsvCatalogReader
{
    /** @var array<int, string> row number => reason, for rows that were skipped */
    private array $skipped = [];

    /**
     * @param  array<string, string[]>  $columns  field => accepted header names
     * @param  string[]  $required
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $required = ['name'],
    ) {}

    /** @return Generator<int, ProductData> */
    public function read(string $path): Generator
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("Cannot read CSV file: {$path}");
        }

        $this->skipped = [];
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->setCsvControl($this->detectDelimiter($path), '"', '');

        $map = null;
        foreach ($file as $index => $row) {
            if ($row === [null] || $row === false) {
                continue;
            }
            if ($map === null) {
                $map = $this->mapHeader($row);

                continue;
            }

            $line = $index + 1;
            try {
                yield $this->toProduct($row, $map);
            } catch (InvalidArgumentException $e) {
                $this->skipped[$line] = $e->getMessage();
            }
        }
    }

    /** @return array<int, string> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /** @return array{fields: array<string, int>, extra: array<int, string>} */
    private function mapHeader(array $header): array
    {
        $header = array_map(fn ($h) => TextNormalizer::normalize(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $header);
        $fields = [];
        foreach ($this->columns as $field => $aliases) {
            $aliases = array_map(TextNormalizer::normalize(...), $aliases);
            foreach ($header as $i => $name) {
                if (in_array($name, $aliases, true)) {
                    $fields[$field] = $i;
                    break;
                }
            }
        }

        $missing = array_diff($this->required, array_keys($fields));
        if ($missing) {
            throw new InvalidArgumentException(
                'CSV is missing required column(s): '.implode(', ', $missing).'. Found headers: '.implode(', ', $header)
            );
        }

        $extra = array_diff_key(array_filter($header), array_flip($fields));

        return ['fields' => $fields, 'extra' => $extra];
    }

    private function toProduct(array $row, array $map): ProductData
    {
        $get = fn (string $field) => isset($map['fields'][$field])
            ? (trim((string) ($row[$map['fields'][$field]] ?? '')) ?: null)
            : null;

        $name = $get('name') ?? throw new InvalidArgumentException('empty name');
        // Price is optional (some catalogs have none), but a value that is there must parse.
        $price = ($raw = $get('price')) === null ? null : self::parsePrice($raw);

        $extra = [];
        foreach ($map['extra'] as $i => $header) {
            if (($value = trim((string) ($row[$i] ?? ''))) !== '') {
                $extra[$header] = $value;
            }
        }

        return new ProductData(
            externalId: $get('external_id') ?? substr(sha1(TextNormalizer::normalize($name)), 0, 16),
            name: $name,
            price: $price,
            description: $get('description'),
            category: $get('category'),
            brand: $get('brand'),
            imageUrl: self::parseUrl($get('image_url')),
            extra: $extra,
        );
    }

    /** Accepts "1,299.90", "₪ 1299", "1299 ש\"ח" and the like. */
    public static function parsePrice(?string $value): float
    {
        $clean = preg_replace('/[^\d.,]/u', '', (string) $value);
        // Thousands separators: drop commas when a dot is the decimal point, or when they group 3 digits.
        $clean = preg_match('/,\d{1,2}$/', $clean) && ! str_contains($clean, '.')
            ? str_replace(',', '.', $clean)
            : str_replace(',', '', $clean);

        if ($clean === '' || ! is_numeric($clean)) {
            throw new InvalidArgumentException("invalid price \"{$value}\"");
        }

        return round((float) $clean, 2);
    }

    private static function parseUrl(?string $value): ?string
    {
        return $value !== null && filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)
            ? $value
            : null;
    }

    private function detectDelimiter(string $path): string
    {
        $firstLine = (string) fgets($handle = fopen($path, 'r'));
        fclose($handle);
        $counts = array_map(fn ($d) => substr_count($firstLine, $d), [',' => ',', ';' => ';', "\t" => "\t", '|' => '|']);

        return array_search(max($counts), $counts, true) ?: ',';
    }
}
