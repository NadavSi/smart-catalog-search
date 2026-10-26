<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->decimal('price', 10, 2);
            $table->text('image_url')->nullable();
            $table->jsonb('extra')->default('{}');
            // Normalized name/brand/category/description; what trigram matching runs on.
            $table->text('search_text');
            $table->vector('embedding', config('catalog.embeddings.dimensions'));
            $table->timestamps();
        });

        DB::statement('CREATE INDEX products_embedding_idx ON products USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX products_search_text_trgm_idx ON products USING gin (search_text gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
