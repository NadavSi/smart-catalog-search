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

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'extra' => 'array',
            'embedding' => Vector::class,
        ];
    }
}
