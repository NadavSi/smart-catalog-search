<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price === null ? null : (float) $this->price,
            'image_url' => $this->image_url,
            'category' => $this->category,
            'subcategory' => $this->extra['subcategory'] ?? null,
            'brand' => $this->brand,
            'description' => $this->description,
            'attributes' => self::parseAttributes($this->extra['attributes'] ?? null),
            'score' => $this->when(isset($this->score), fn () => round($this->score, 4)),
        ];
    }

    /**
     * "הספק: 1400W | נפח: 6.2 ליטר" → [['label' => 'הספק', 'value' => '1400W'], ...].
     *
     * @return array<int, array{label: ?string, value: string}>
     */
    private static function parseAttributes(?string $raw): array
    {
        $attributes = [];
        foreach (explode('|', (string) $raw) as $part) {
            [$label, $value] = str_contains($part, ':') ? explode(':', $part, 2) : [null, $part];
            if (($value = trim($value)) !== '') {
                $attributes[] = ['label' => $label === null ? null : trim($label), 'value' => $value];
            }
        }

        return $attributes;
    }
}
