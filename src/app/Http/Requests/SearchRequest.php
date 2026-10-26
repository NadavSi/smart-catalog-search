<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:'.config('catalog.search.max_query_length')],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'נא להקליד מה לחפש.',
            'q.max' => 'החיפוש ארוך מדי.',
        ];
    }

    public function searchQuery(): string
    {
        return trim($this->validated('q'));
    }
}
