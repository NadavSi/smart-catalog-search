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
            'price' => (float) $this->price,
            'image_url' => $this->image_url,
            'category' => $this->category,
            'brand' => $this->brand,
            'score' => $this->when(isset($this->score), fn () => round($this->score, 4)),
        ];
    }
}
