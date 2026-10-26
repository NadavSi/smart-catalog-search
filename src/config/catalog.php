<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CSV import
    |--------------------------------------------------------------------------
    |
    | Each product field lists the CSV header names it may appear under
    | (matched case-insensitively, after trimming). Columns that match no
    | field are kept in the product's `extra` JSON so nothing is lost.
    |
    */

    'csv' => [
        'columns' => [
            'external_id' => ['id', 'sku', 'product_id', 'מק"ט', 'מקט'],
            'name' => ['name', 'title', 'product_name', 'product', 'שם', 'שם מוצר'],
            'description' => ['description', 'desc', 'details', 'תיאור'],
            'category' => ['category', 'categories', 'department', 'קטגוריה'],
            'brand' => ['brand', 'manufacturer', 'מותג', 'יצרן'],
            'price' => ['price', 'cost', 'מחיר'],
            'image_url' => ['image', 'image_url', 'img', 'picture', 'thumbnail', 'תמונה'],
        ],
        'required' => ['name', 'price'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | BGE-M3 runs locally (ONNX via TransformersPHP): no API key, no
    | per-query cost. It was picked over multilingual-e5 and the
    | paraphrase-multilingual models by a labelled Hebrew/English benchmark
    | (see README): it is the strongest at cross-language matches such as
    | "vacuum cleaner" ↔ "שואב אבק".
    |
    | Changing the model means changing `dimensions`, then re-running the
    | migration and the import.
    |
    */

    'embeddings' => [
        'model' => env('EMBEDDING_MODEL', 'Xenova/bge-m3'),
        'dimensions' => 1024,
        'pooling' => 'cls',
        // Some models (e.g. e5: "query: " / "passage: ") expect these.
        'query_prefix' => '',
        'document_prefix' => '',
        'cache_dir' => storage_path('models'),
        'batch_size' => 16,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search ranking
    |--------------------------------------------------------------------------
    |
    | score = semantic_weight * cosine + lexical_weight * trigram word similarity
    |
    | A result is kept only if it stands out semantically (cosine minus the
    | query's mean cosine to the whole catalog >= min_margin) or matches
    | lexically (>= min_lexical), and is within max_gap of the best hit,
    | which keeps the long tail out.
    | The thresholds are tuned to the model's cosine range; retune with
    | `php artisan catalog:eval` after changing either.
    |
    */

    'search' => [
        'limit' => 24,
        'candidates' => 100,
        'semantic_weight' => 0.75,
        'lexical_weight' => 0.25,
        'min_margin' => 0.08,
        'min_lexical' => 0.6,
        'max_gap' => 0.10,
        'max_query_length' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Brand transliterations
    |--------------------------------------------------------------------------
    |
    | Latin name (lowercase) => Hebrew spellings, first one preferred. Applied
    | to queries in both directions by App\Search\QueryExpander.
    |
    */

    'synonyms' => [
        'iphone' => ['אייפון', 'איפון'],
        'ipad' => ['אייפד', 'איפד'],
        'airpods' => ['איירפודס', 'אירפודס'],
        'macbook' => ['מקבוק', 'מאקבוק'],
        'apple' => ['אפל'],
        'samsung' => ['סמסונג'],
        'galaxy' => ['גלקסי'],
        'xiaomi' => ['שיאומי'],
        'sony' => ['סוני'],
        'playstation' => ['פלייסטיישן'],
        'dyson' => ['דייסון'],
        'roborock' => ['רובורוק'],
        'philips' => ['פיליפס'],
        'nespresso' => ['נספרסו'],
        'delonghi' => ['דלונגי'],
        'kitchenaid' => ['קיטשן אייד'],
        'ninja' => ['נינג\'ה'],
        'lego' => ['לגו'],
        'nike' => ['נייקי'],
        'adidas' => ['אדידס'],
        'jbl' => ['ג\'יי בי אל'],
        'lg' => ['אל ג\'י'],
        'bosch' => ['בוש'],
    ],

];
