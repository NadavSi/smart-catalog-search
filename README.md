# Smart Product Catalog Search

Search a product catalog in Hebrew, English or a mix of both, by product name ("iPhone 16", "אייפון") or by describing a need ("מתנה למישהו שאוהב לבשל", "משהו שיעזור לנקות שערות של כלב מהספה").

**Live:** https://my-portal.ddns.net/catalog-search/

| Layer | Choice |
|---|---|
| Backend | PHP 8.4, Laravel 13, served by Octane on FrankenPHP (worker mode) |
| Search | Hybrid: semantic (BGE-M3 embeddings + pgvector) + lexical (pg_trgm) + a brand-transliteration dictionary; optional cross-encoder re-ranking (bge-reranker-v2-m3, off by default) |
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
- Columns are matched **by header name**, not position (`name`/`title`/`שם`, `price`/`מחיר`, `image`/`image_url`/`תמונה`, …; see `config/catalog.php`). Unknown columns (e.g. `subcategory`, `attributes`) are kept in a JSON `extra` column. Only a name is required; a missing price is stored as null and the card omits it. Rows with no name or an unparseable price are skipped and reported. Prices like `₪ 1,299.90` or `1299 ש"ח` are parsed.
- The delimiter (`,` `;` tab `|`) and a UTF-8 BOM are detected automatically.

### Tests

```bash
./scripts/test.sh                   # PHPUnit: 32 tests, real Postgres, separate assignmentsDB_test database
docker compose exec app php artisan catalog:eval data/eval.json   # relevance benchmark, see below
docker compose exec app php artisan catalog:eval data/eval.json --sweep=min_margin=0.08,0.12,0.15   # threshold sweep
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
                                                       │
         optional (SEARCH_RERANK=true): cross-encoder re-scores the top 10 (query + product text together),
                   keep if logit ≥ −4, order by that score
```

Code map (`src/app`):

| Piece | Responsibility |
|---|---|
| `Contracts/Embedder` | Interface: `embedQuery`, `embedDocuments`. The rest of the app depends only on this |
| `Services/Embeddings/TransformersEmbedder` | Local ONNX model; pooling and prefixes come from config, so switching models is a config change |
| `Catalog/CsvCatalogReader` → `ProductData` → `CatalogImporter` | Parse and validate → typed DTO → embed and upsert in batches |
| `Search/QueryExpander` | Hebrew ↔ Latin brand spellings (`config/catalog.php` → `synonyms`) |
| `Search/ProductSearch` | The hybrid query and relevance filter above, then the optional re-ranking stage |
| `Contracts/Reranker` → `Services/Reranking/TransformersReranker` | Cross-encoder scoring of (query, product) pairs; `CachingReranker` makes eval sweeps cheap |
| `Support/TextNormalizer` | One canonical text form (case, niqqud, gershayim, whitespace) for indexing and querying |
| `Http/…` | Thin controller, `SearchRequest` validation, `ProductResource` output shape |
| `resources/views/components` | `search-box`, `product-card` (a `<template>`), `state-message` (idle / empty / error) |
| `public/js` | `api.js` (fetch, timeout, error messages) · `product-card.js` (render) · `search-page.js` (state machine) |

## Key decisions

**Local embeddings instead of an embeddings API.** Semantic search needs embeddings, and Claude doesn't offer any. A hosted API (OpenAI, Voyage) would mean another key and another bill, plus a network hop on every keystroke-debounced search. TransformersPHP runs ONNX models inside PHP over FFI, so the stack stays *PHP + Postgres* with no Python sidecar. Search latency was ~60–90ms warm on the 25-product sample and is ~250–800ms on the 1,000-product catalog (query embedding ~100ms, the per-query centroid, trigram scoring). The price is a ~560MB model and ~1.1GB RAM per worker.

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

**Re-ranking: built, measured, off by default.** On the 1,000-product catalog the hybrid search ranks well (82% correct #1) but cannot say "nothing matches": all 10 out-of-catalog queries in `data/eval.json` ("משקפי שמש", "ביטוח רכב", `asdfgh`) return results. Raising `min_margin` doesn't fix it; every step that removes junk also empties legitimate queries, because short English queries against Hebrew names score as low as noise. A cross-encoder reads the query and the product *together*, which is the right tool for that, so `bge-reranker-v2-m3` (multilingual, Apache-2.0, same base as bge-m3) now re-scores the top 10 when `SEARCH_RERANK=true`. TransformersPHP has no classification mapping for xlm-roberta, so `TransformersReranker` runs the ONNX graph through the base model class and reads `logits` directly (checked against the model card's reference scores).

| (50 labelled + 10 "should be empty" queries) | hit@1 | MRR | correct queries returning nothing | junk queries with results | per query |
|---|---|---|---|---|---|
| hybrid only (default) | 41/50 | 0.87 | 0 | 10/10 | ~0.5s |
| hybrid, `min_margin=0.18` | 42/50 | 0.85 | 7 | 2/10 | ~0.5s |
| + re-rank, order only | 42/50 | 0.89 | 2 | 10/10 | ~7s |
| **+ re-rank, `rerank_min=-4`** | 41/50 | 0.87 | 3 | **4/10** | ~7s |
| + re-rank, `rerank_min=-3` | 41/50 | 0.85 | 5 | 2/10 | ~7s |

It is a better trade-off than any embedding threshold (2/10 false positives for 5 lost queries instead of 7), and it fixes real near misses ("ביטוח רכב" goes from 12 results to one borderline one). But it is still weak cross-language (`vacuum cleaner` puts an air purifier first; `power bank` and `dash cam` drop out), and on this 6-core CPU it costs ~0.7s per pair, ~7s per search. So it ships disabled, downloaded and ready: on a GPU the same model scores a pair in a few ms (see the next section). Two smaller rerankers were rejected: `mmarco-mMiniLMv2` is fast (~0.1s/pair) but could not separate a Dyson vacuum from a vacuum storage bag, and `jina-reranker-v2-multilingual` is licensed non-commercial.

**A memory leak in the tokenizer, fixed by overriding one class.** Importing the 1,000-product catalog ran out of memory. TransformersPHP's `CharTrie::commonPrefixSearch()` (0.6.2, still on `main`) uses `getChild()`, which *creates* missing nodes, so every lookup permanently grew the vocabulary trie: ~50 varied Hebrew descriptions exhausted 512MB, and Octane workers leaked on every query. `src/overrides/CharTrie.php` replaces the class through `autoload.files` with a read-only lookup. Embeddings were verified byte-identical before and after.

## Testing

1. **Unit:** text normalization, price parsing, CSV header mapping and bad-row handling (price is optional), query expansion, and the `CharTrie` override (it is the class in use, finds the same prefixes, and searching no longer grows it).
2. **Feature (real Postgres + pgvector):** import idempotency, search by name, Hebrew, typos, unrelated query → empty, validation (422), the API not leaking internal columns, the page rendering RTL, and the re-ranking stage (skipped when off, decides order and relevance when on, can empty the results). The models are swapped for a deterministic bag-of-words `FakeEmbedder` and a word-overlap `FakeReranker`, so tests run in ~3s. Tests use a separate `assignmentsDB_test` database, and the base `TestCase` refuses to run against any database not ending in `_test`.
3. **Relevance benchmark:** `catalog:eval` runs labelled queries (`[{"query": …, "expect": [ids]}]`; an empty `expect` means "should return nothing") through the real pipeline. It reports hit@1, MRR, queries that lost every correct result, false positives and time per query. `--set=min_margin=0.1` overrides a setting; `--sweep=rerank_min=-5,-4,-3` runs once per value in one process, reusing re-ranker scores. `data/eval.json` has 60 queries for the 1,000-product catalog (every id checked against the CSV). This drove the model choice and every threshold in `config/catalog.php`.
4. **Manual:** headless-Chrome screenshots at desktop and phone width for the idle, loading, results, empty and error states.

Sample queries: `שואב אבק` · `iPhone 16` · `אייפון` · `מתנה למישהו שאוהב לבשל` · `משהו שיעזור לנקות שערות של כלב מהספה` · `vacuum cleaner` · `שואב robot` · `roborok` (typo) · `asdfgh` (→ no results only with re-ranking on; see above)

## Known limitations and next steps

- **No reliable "no results" on CPU.** With the default settings every query returns something. The re-ranker cuts false positives from 10/10 to 4/10 but needs a GPU to be fast enough (see *Re-ranking*).
- **Weak English one-word queries against Hebrew names** ("speaker" for "רמקול"). Enriching each product at import time with an English and a Hebrew title/keywords (one LLM call per product, cached) would close most of this.
- **The synonym dictionary is hand-written.** It could be generated from the catalog's brand list.
- **Thresholds are tuned on a small labelled set.** They should be re-tuned against real search logs, with click-through as the signal.
- **Memory:** ~1.2GB per Octane worker (~2.4GB with re-ranking on, measured; PHP's `memory_limit` is 1G because each model builds a ~250MB tokenizer trie in PHP), so one worker is the default (`OCTANE_WORKERS`).
- **Import re-embeds every row** (~5.5 min for 1,000 products on this CPU). It should skip rows whose text hash hasn't changed.
- Not built: filters/facets, pagination (top 24), autocomplete, search analytics, and a cached centroid (it's recomputed per query, which is fine for thousands of products but not millions).

## Toward a commercial-grade platform

What this project would need to serve a real store with tens of thousands of products and real traffic, in rough order of value. The numbers above are the starting point: the current bottlenecks are measured, not guessed.

**1. Move the models out of PHP onto a GPU inference service.** Today each web worker holds both models (~2.4GB resident) and does all inference on CPU, which is why the re-ranker costs ~7s and only one worker fits. A dedicated service (Hugging Face Text Embeddings Inference, NVIDIA Triton, or ONNX Runtime with the CUDA provider) behind an internal HTTP API changes that:
- the re-ranker drops to a few ms per pair, so it can score the top 50 in well under 100ms and be turned on;
- the web tier becomes small stateless PHP workers that scale horizontally;
- requests are batched across users, and one GPU (e.g. an NVIDIA L4) serves both models.

The app already depends only on the `Embedder` and `Reranker` interfaces, so this is two new adapter classes, not a rewrite.

**2. Pick models by benchmark, with a larger budget.** Candidates worth running through `catalog:eval`:

| | Self-hosted (GPU) | Hosted API |
|---|---|---|
| Embeddings | bge-m3 in fp16 instead of int8; multilingual-e5-large-instruct; Qwen3-Embedding | OpenAI `text-embedding-3-large`, Cohere `embed-multilingual-v3`, Voyage multilingual |
| Re-ranking | bge-reranker-v2-m3 (fp16), larger Qwen3-Reranker | Cohere Rerank 3.5 (multilingual) |

Hosted APIs mean no GPU to run, but a per-query cost, a network hop and query text leaving the company, so the decision is cost and privacy as much as quality. Whichever wins, the int8 quantization used here should be re-checked: it was chosen to fit a CPU, not for quality.

**3. Enrich the catalog at import, not at query time.** The worst remaining failures are short English queries against Hebrew products (`power bank`, `dash cam`, `vacuum cleaner`). Once per product, an LLM (e.g. Claude) generates English and Hebrew names, synonyms, use cases ("gift for coffee lovers", "for a dog owner") and structured fields parsed from the `attributes` column (power, capacity, size). That text is embedded and trigram-indexed. It costs one call per product, cached by content hash, and none per search.

**4. Learn from real traffic.** Log every query, result list, click and add-to-cart. That gives:
- a labelled set of thousands of queries instead of 60, with `catalog:eval` run in CI as a gate on every ranking change;
- online A/B tests, with zero-results rate, click-through and conversion per query as the metrics;
- eventually, fine-tuning the embedding model and re-ranker on (query, clicked product) pairs. That is where training starts to pay off; with 60 hand-written queries it would only overfit.

**5. Scale the index and ingestion.**
- Compute the catalog centroid at import instead of on every query. It is currently an `avg()` over every product (~20ms at 1,000, so seconds at 100k).
- Make imports incremental: re-embed only rows whose content hash changed, through a queue, so price and stock updates don't re-embed anything.
- Postgres + pgvector (HNSW) is comfortable into the low millions of vectors. Beyond that, or once rich facets, a Hebrew analyzer and BM25 are needed, OpenSearch/Elasticsearch offers keyword and vector search in one engine.

**6. Build the store features around search.** Filters and facets (category, brand, price, stock), pagination, autocomplete and spelling suggestions. Business rules on top of relevance: boost in-stock and promoted items, demote out-of-stock ones, pin results for key queries. Personalization later, once there's history.

**7. Operate it.**
- Monitoring: p95 latency per stage, error rate, zero-results rate, GPU utilization.
- A query cache (Redis) for popular queries.
- Autoscaling for the web and inference tiers separately.
- Per-client rate limits (already in place) and a query-log retention policy that respects user privacy.

## Working with Claude

Built with Claude Code: scaffolding, implementation, tests, the benchmark harness and the deployment wiring. I set the constraints (PHP/Laravel, a minimal stack, no new paid API keys, strict isolation from everything else on the host) and the rule that no claim about quality counts until something has been run to check it: tests, the eval command, screenshots, or a look at the database itself.

Suggestions that were checked and then changed or rejected:

- **The first model choice was wrong.** Claude's initial pick, multilingual-e5-small, passed a quick smoke test. A labelled benchmark run before building on it showed e5 failing the English↔Hebrew queries, so the model was switched to bge-m3 on the numbers.
- **The relevance cut-off was replaced.** The first version filtered on raw cosine ≥ 0.80, a number carried over from e5. A threshold sweep showed that no absolute cut-off separates good from bad results, so it became the centroid-margin rule.
- **A library feature that silently didn't work.** TransformersPHP's `normalize: true` returned vectors of length ~4.7. The ranking only looked right because cosine hides it. This was caught by checking vector norms, and the code now normalizes itself.
- **A dangerous test setup, caught before it mattered.** PHPUnit's `<env force>` doesn't override `$_SERVER`, which Docker fills, so the first test runs were resetting the *real* database (still empty at the time). Unexpected 404s in the tests exposed it. Fixed with `<server>` entries and explicit overrides, plus a guard that refuses any database not ending in `_test`.
- **Rejected:** a hosted embeddings API (adds a secret and a per-query cost) and Claude-based query rewriting (an LLM call on every search is slow and expensive for this need).
