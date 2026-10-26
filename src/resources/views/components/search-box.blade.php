@props(['examples' => [], 'placeholder' => 'למשל: מתנה למישהו שאוהב קפה'])

<form class="search-box" role="search" data-search-form novalidate>
    <label for="search-input" class="visually-hidden">חיפוש מוצרים</label>
    <div class="search-box__field">
        <input id="search-input" type="search" name="q" dir="auto" autocomplete="off" enterkeyhint="search"
               maxlength="{{ config('catalog.search.max_query_length') }}"
               placeholder="{{ $placeholder }}" data-search-input>
        <button type="button" class="search-box__clear" aria-label="ניקוי החיפוש" data-search-clear hidden>
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
        <button type="submit" class="search-box__submit" aria-label="חיפוש">
            <span>חיפוש</span>
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        </button>
    </div>

    @if ($examples)
        <div class="search-box__examples">
            <span>נסו:</span>
            @foreach ($examples as $example)
                <button type="button" class="chip" dir="auto" data-example="{{ $example }}">{{ $example }}</button>
            @endforeach
        </div>
    @endif
</form>
