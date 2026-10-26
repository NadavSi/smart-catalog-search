<?php

namespace App\Catalog;

use App\Support\TextNormalizer;

/**
 * One catalog row, validated and typed, independent of the CSV layout.
 */
final readonly class ProductData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public float $price,
        public ?string $description = null,
        public ?string $category = null,
        public ?string $brand = null,
        public ?string $imageUrl = null,
        public array $extra = [],
    ) {}

    /** The text the embedding model sees. Name first: it carries the most signal. */
    public function embeddingText(): string
    {
        return implode('. ', array_filter([$this->name, $this->brand, $this->category, $this->description]));
    }

    /** The text trigram matching runs on. */
    public function searchText(): string
    {
        return TextNormalizer::normalize(
            implode(' ', array_filter([$this->name, $this->brand, $this->category, $this->description]))
        );
    }
}
