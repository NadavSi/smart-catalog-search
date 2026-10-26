<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Http\Resources\ProductResource;
use App\Search\ProductSearch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, ProductSearch $search): AnonymousResourceCollection
    {
        $started = microtime(true);
        $query = $request->searchQuery();
        $results = $search->search($query);

        return ProductResource::collection($results)->additional(['meta' => [
            'query' => $query,
            'count' => $results->count(),
            'took_ms' => (int) round((microtime(true) - $started) * 1000),
        ]]);
    }
}
