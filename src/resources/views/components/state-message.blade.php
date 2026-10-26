@props(['state', 'icon', 'title', 'action' => null])

{{-- One of the page's non-result states (idle / empty / error); search-page.js shows at most one. --}}
<div class="state-message" data-state="{{ $state }}" hidden>
    <div class="state-message__icon" aria-hidden="true">{{ $icon }}</div>
    <h2 class="state-message__title">{{ $title }}</h2>
    <p class="state-message__text" data-state-text>{{ $slot }}</p>
    @if ($action)
        <button type="button" class="button" data-state-action="{{ $state }}">{{ $action }}</button>
    @endif
</div>
