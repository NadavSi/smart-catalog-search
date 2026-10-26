# Smart Product Catalog Search

Search a product catalog in Hebrew, English or a mix of both, by product name ("iPhone 16", "אייפון") or by describing a need ("מתנה למישהו שאוהב לבשל", "משהו שיעזור לנקות שערות של כלב מהספה").

**Live:** https://my-portal.ddns.net/catalog-search/

| Layer | Choice |
|---|---|
| Backend | PHP 8.4, Laravel 13, served by Octane on FrankenPHP (worker mode) |
| Search | Hybrid: semantic (BGE-M3 embeddings + pgvector) + lexical (pg_trgm) + a brand-transliteration dictionary |
| Embeddings | Run **locally in PHP** (TransformersPHP / ONNX Runtime). No external API, no key, no per-query cost |
| Database | PostgreSQL 16 with `pgvector` and `pg_trgm` |
| Frontend | Blade components + ~150 lines of vanilla JS modules, no build step |
| Runtime | Docker Compose: two containers (`app`, `assignmentsDB`) |

## Running it

Requirements: Docker with Compose v2. Nothing else; PHP, Composer and the model all live in the image.

```bash
cp .env.example .env
# fill in DB_PASSWORD and APP_KEY (the commands to generate them are in the file)

docker compose up -d --build        # first build downloads the ~560MB model into the image
docker compose exec app php artisan catalog:import    # loads data/products.csv
open http://localhost:8095
```

### Loading the catalog

`php artisan catalog:import [path] [--prune]`

- Reads a CSV (default `data/products.csv`), embeds every product and **upserts by product id**, so re-running is safe.
- `--prune` also removes products that are no longer in the file.
- Columns are matched **by header name**, not position (`name`/`title`/`שם`, `price`/`מחיר`, `image`/`image_url`/`תמונה`, …; see `config/catalog.php`). Unknown columns are kept in a JSON `extra` column. Rows with no name or an unparseable price are skipped and reported. Prices like `₪ 1,299.90` or `1299 ש"ח` are parsed.
- The delimiter (`,` `;` tab `|`) and a UTF-8 BOM are detected automatically.

### Tests

```bash
./scripts/test.sh                   # PHPUnit: 26 tests, real Postgres, separate assignmentsDB_test database
docker compose exec app php artisan catalog:eval data/eval.json   # relevance benchmark, see below
```

## How search works

```
query ─► normalize ─► expand transliterations ─► embed (BGE-M3, in-process)
                         "אייפון 16" → +"iphone 16"
                                                       │
         Postgres:  candidates = 100 nearest by vector (HNSW)  ∪  trigram matches (GIN)
                    semantic  = cosine(query, product)
                    lexical   = max word_similarity(variant, product text)
                    baseline  = query · catalog centroid     (= mean similarity to every product)
                                                       │
         keep if   semantic − baseline ≥ 0.08   or   lexical ≥ 0.6
         rank by   0.75·semantic + 0.25·lexical, drop anything > 0.10 below the best hit
```

Code map (`src/app`):

| Piece | Responsibility |
|---|---|
| `Contracts/Embedder` | Interface: `embedQuery`, `embedDocuments`. The rest of the app depends only on this |
| `Services/Embeddings/TransformersEmbedder` | Local ONNX model; pooling and prefixes come from config, so switching models is a config change |
| `Catalog/CsvCatalogReader` → `ProductData` → `CatalogImporter` | Parse and validate → typed DTO → embed and upsert in batches |
| `Search/QueryExpander` | Hebrew ↔ Latin brand spellings (`config/catalog.php` → `synonyms`) |
| `Search/ProductSearch` | The hybrid query and relevance filter above |
| `Support/TextNormalizer` | One canonical text form (case, niqqud, gershayim, whitespace) for indexing and querying |
| `Http/…` | Thin controller, `SearchRequest` validation, `ProductResource` output shape |
| `resources/views/components` | `search-box`, `product-card` (a `<template>`), `state-message` (idle / empty / error) |
| `public/js` | `api.js` (fetch, timeout, error messages) · `product-card.js` (render) · `search-page.js` (state machine) |

## Key decisions

**Local embeddings instead of an embeddings API.** Semantic search needs embeddings, and Claude doesn't offer any. A hosted API (OpenAI, Voyage) would mean another key and another bill, plus a network hop on every keystroke-debounced search. TransformersPHP runs ONNX models inside PHP over FFI, so the stack stays *PHP + Postgres* with no Python sidecar. Search latency is ~60–90ms warm. The price is a ~560MB model and ~1.1GB RAM per worker.

**Picking the model by measurement.** Four multilingual models ran against the same catalog and 22 labelled queries, half of them English against Hebrew product names:

| model | hit@1 | MRR | ms/query |
|---|---|---|---|
| multilingual-e5-small | 18/22 | 0.85 | 14 |
| paraphrase-multilingual-MiniLM-L12-v2 | 18/22 | 0.89 | 13 |
| paraphrase-multilingual-mpnet-base-v2 | 19/22 | 0.92 | 22 |
| **bge-m3** | **20/22** | **0.94** | 41 |

e5-small (the first choice) failed exactly the cross-language cases the brief asks for ("vacuum cleaner" ↔ "שואב אבק"). It also scored unrelated queries higher than some correct ones, which makes "no results" impossible to decide.

**Hybrid ranking.** Embeddings are fuzzy on model numbers and brand names, while trigrams are exact and typo-tolerant ("roborok" → Roborock). They're blended rather than run as two separate searches, so a single ranked list comes back.

**"No results" from relative similarity.** Raw cosine is a weak relevance signal: a nonsense query still scores ~0.4 against everything. A product is kept only if it stands out from the catalog as a whole. Its similarity is compared with the query's *mean* similarity to all products, which for unit vectors is just the dot product with the catalog centroid, so it costs one `avg(embedding)` query.

**Transliteration dictionary.** "אייפון 16" ranked the *iPhone case* (whose Hebrew name contains "לאייפון 16") above the phone. A small, explicit brand dictionary fixed it and lifted hit@1 from 84% to 92% on the benchmark.

**Octane worker mode.** Loading the model takes ~5s, so it must stay in memory between requests. Each worker loads it once at boot (`WarmEmbeddingModel` listener).

**Small, framework-light frontend.** Blade components own the markup, including the card `<template>`, and three ES modules own the behaviour. Queries are kept in `?q=`, so a search is a shareable link. In-flight requests are aborted when a newer search starts.

## Testing

1. **Unit:** text normalization, price parsing, CSV header mapping and bad-row handling, query expansion.
2. **Feature (real Postgres + pgvector):** import idempotency, search by name, Hebrew, typos, unrelated query → empty, validation (422), the API not leaking internal columns, the page rendering RTL. The model is swapped for a deterministic bag-of-words `FakeEmbedder`, so tests run in ~2s. Tests use a separate `assignmentsDB_test` database, and the base `TestCase` refuses to run against any database not ending in `_test`.
3. **Relevance benchmark:** `catalog:eval` runs labelled queries (`[{"query": …, "expect": [ids]}]`; an empty `expect` means "should return nothing") through the real pipeline. It reports hit@1, MRR and false positives, and `--set=min_margin=0.1` overrides a threshold for a sweep. This drove the model choice and every threshold in `config/catalog.php`.
4. **Manual:** headless-Chrome screenshots at desktop and phone width for the idle, loading, results, empty and error states.

Sample queries: `שואב אבק` · `iPhone 16` · `אייפון` · `מתנה למישהו שאוהב לבשל` · `משהו שיעזור לנקות שערות של כלב מהספה` · `vacuum cleaner` · `שואב robot` · `roborok` (typo) · `asdfgh` (→ no results)

## Known limitations and next steps

- **Near-miss false positives:** "מכונת כביסה" (washing machine) can still return a coffee *machine*. A cross-encoder re-ranker over the top ~20 would fix this class of error, at the cost of one more model.
- **Weak English one-word queries against Hebrew names** ("speaker" for "רמקול"). Enriching each product at import time with an English and a Hebrew title/keywords (one LLM call per product, cached) would close most of this.
- **The synonym dictionary is hand-written.** It could be generated from the catalog's brand list.
- **Thresholds are tuned on a small labelled set.** They should be re-tuned against real search logs, with click-through as the signal.
- **Memory:** ~1.1GB per Octane worker, so one worker is the default (`OCTANE_WORKERS`). A dedicated embedding service, or a hosted API, would let the web tier scale independently.
- Not built: filters/facets, pagination (top 24), autocomplete, search analytics, and a cached centroid (it's recomputed per query, which is fine for thousands of products but not millions).

## Working with Claude

Built with Claude Code: scaffolding, implementation, tests, the benchmark harness and the deployment wiring. I set the constraints (PHP/Laravel, a minimal stack, no new paid API keys, strict isolation from everything else on the host) and the rule that no claim about quality counts until something has been run to check it: tests, the eval command, screenshots, or a look at the database itself.

Suggestions that were checked and then changed or rejected:

- **The first model choice was wrong.** Claude's initial pick, multilingual-e5-small, passed a quick smoke test. A labelled benchmark run before building on it showed e5 failing the English↔Hebrew queries, so the model was switched to bge-m3 on the numbers.
- **The relevance cut-off was replaced.** The first version filtered on raw cosine ≥ 0.80, a number carried over from e5. A threshold sweep showed that no absolute cut-off separates good from bad results, so it became the centroid-margin rule.
- **A library feature that silently didn't work.** TransformersPHP's `normalize: true` returned vectors of length ~4.7. The ranking only looked right because cosine hides it. This was caught by checking vector norms, and the code now normalizes itself.
- **A dangerous test setup, caught before it mattered.** PHPUnit's `<env force>` doesn't override `$_SERVER`, which Docker fills, so the first test runs were resetting the *real* database (still empty at the time). Unexpected 404s in the tests exposed it. Fixed with `<server>` entries and explicit overrides, plus a guard that refuses any database not ending in `_test`.
- **Rejected:** a hosted embeddings API (adds a secret and a per-query cost) and Claude-based query rewriting (an LLM call on every search is slow and expensive for this need).
