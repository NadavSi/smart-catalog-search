<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catalogs without prices (e.g. data/products.csv) are valid; the card just omits the price.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->decimal('price', 10, 2)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->decimal('price', 10, 2)->nullable(false)->change());
    }
};
