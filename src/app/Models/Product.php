<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;

class Product extends Model
{
    protected $fillable = [
        'external_id', 'name', 'description', 'category', 'brand',
        'price', 'image_url', 'extra', 'search_text', 'embedding',
    ];

    protected $hidden = ['embedding', 'search_text'];

    /** What the re-ranker reads for this product; same fields as the embedding text. */
    public function rerankText(): string
    {
        return implode('. ', array_filter([$this->name, $this->brand, $this->category, $this->description]));
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'extra' => 'array',
            'embedding' => Vector::class,
        ];
    }
}
