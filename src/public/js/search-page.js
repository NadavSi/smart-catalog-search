// Wires the search box to the API and switches between the page states:
// idle → loading → results | empty | error. The query is kept in ?q= so results can be shared.

import { ApiError, searchProducts } from './api.js';
import { renderProductCard, renderSkeletons } from './product-card.js';
import { openProductDialog } from './product-dialog.js';

const DEBOUNCE_MS = 450;
const MIN_LIVE_LENGTH = 2;

const form = document.querySelector('[data-search-form]');
const input = document.querySelector('[data-search-input]');
const grid = document.querySelector('[data-results]');
const summary = document.querySelector('[data-summary]');
const resultsHeader = document.querySelector('[data-results-header]');
const clearButton = document.querySelector('[data-search-clear]');
const stateMessages = document.querySelectorAll('[data-state]');

let controller = null;
let debounceTimer = null;
let lastQuery = '';

function showState(state, text) {
    stateMessages.forEach((el) => {
        el.hidden = el.dataset.state !== state;
        if (!el.hidden && text) el.querySelector('[data-state-text]').textContent = text;
    });
    grid.setAttribute('aria-busy', String(state === 'loading'));
    if (state !== 'results' && state !== 'loading') grid.replaceChildren();
    if (state !== 'results') summary.textContent = '';
    resultsHeader.hidden = state !== 'results';
}

function syncClearButton() {
    clearButton.hidden = input.value === '';
}

async function runSearch(query, { updateUrl = true } = {}) {
    query = query.trim();
    clearTimeout(debounceTimer);
    if (query === lastQuery) return;
    lastQuery = query;
    controller?.abort();

    if (updateUrl) {
        const url = new URL(location.href);
        query ? url.searchParams.set('q', query) : url.searchParams.delete('q');
        history.replaceState(null, '', url);
    }

    if (!query) return showState('idle');

    const current = (controller = new AbortController());
    showState('loading');
    grid.replaceChildren(...renderSkeletons(8));

    try {
        const { products, meta } = await searchProducts(query, { signal: current.signal });
        if (current !== controller) return; // a newer search took over
        if (!products.length) return showState('empty');

        showState('results');
        grid.replaceChildren(...products.map((p) => {
            const card = renderProductCard(p);
            card.addEventListener('click', () => openProductDialog(p));
            return card;
        }));
        summary.textContent = `${products.length} תוצאות עבור "${meta.query}"`;
    } catch (error) {
        if (current !== controller || current.signal.aborted) return; // a newer search took over
        lastQuery = ''; // allow retrying the same query
        showState('error', error instanceof ApiError ? error.message : 'לא הצלחנו להשלים את החיפוש.');
    }
}

form.addEventListener('submit', (event) => {
    event.preventDefault();
    lastQuery = ''; // pressing Enter always re-runs
    runSearch(input.value);
    input.blur(); // closes the keyboard on mobile
});

input.addEventListener('input', () => {
    syncClearButton();
    clearTimeout(debounceTimer);
    const value = input.value.trim();
    if (value.length === 0) return runSearch('');
    if (value.length >= MIN_LIVE_LENGTH) debounceTimer = setTimeout(() => runSearch(value), DEBOUNCE_MS);
});

form.querySelectorAll('[data-example]').forEach((chip) => chip.addEventListener('click', () => {
    input.value = chip.dataset.example;
    syncClearButton();
    runSearch(input.value);
}));

clearButton.addEventListener('click', () => {
    input.value = '';
    syncClearButton();
    runSearch('');
    input.focus();
});

document.querySelector('[data-state-action="error"]')?.addEventListener('click', () => runSearch(input.value));

const initial = new URLSearchParams(location.search).get('q') ?? '';
input.value = initial;
syncClearButton();
initial ? runSearch(initial, { updateUrl: false }) : showState('idle');
