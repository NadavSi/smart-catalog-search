# Tests

34 PHPUnit tests: 13 feature tests against the real HTTP API and Postgres + pgvector, and 21 unit tests for the
pure building blocks.

## Running

```sh
docker compose up -d      # the suite needs the stack's Postgres
./scripts/test.sh         # from the project root; extra args go to phpunit, e.g. --filter=Reranker
```

The suite runs inside the `app` container against a separate database, `assignmentsDB_test`, which is wiped
between tests (`RefreshDatabase`). It never touches the real catalog.

**Expected result:** `OK (34 tests, 86 assertions)`.

## Test setup

| File | Role |
|---|---|
| `Fixtures/products.csv` | 8-row catalog: 6 valid products and 2 broken rows (empty name, price `חינם`). Covers prices in several formats, a missing image, an invalid URL, a product with no price, and one row with `subcategory` / `attributes`. |
| `Support/FakeEmbedder.php` | Stands in for the e5 embedding model. Each word hashes to a vector dimension, so texts that share words come out similar. Tests stay deterministic and fast, without loading the ~100 MB model. |
| `Support/FakeReranker.php` | Stands in for the cross-encoder. The score is the number of query words found in the product minus 1, so a product sharing no words scores -1. It records every call for assertions. |

The feature tests loosen two thresholds (`min_margin`, `max_gap`) because the fake model's cosine scale differs
from e5's.

## Feature tests: `Feature/SearchApiTest.php`

Each test imports the fixture catalog and then calls the app over HTTP, running the real hybrid SQL (vector
similarity + trigram matching).

| Test | What it does | Expected result |
|---|---|---|
| `test_import_is_idempotent` | Imports the fixture a second time. | Still 6 products, with no duplicates. |
| `test_finds_product_by_name` | `GET /api/search?q=iphone 16` | 200. The first result is "Apple iPhone 16 128GB שחור" with price 3799. The response has `data[].{id,name,price,image_url}` and `meta.{query,count,took_ms}`. |
| `test_finds_hebrew_query` | Searches `שואב אבק` | The first result is the Roborock S8 robot vacuum. |
| `test_lexical_match_tolerates_typos` | Searches `roborok` (misspelled) | The first result is still the Roborock S8, matched by trigram similarity. |
| `test_returns_empty_list_for_unrelated_query` | Searches `zzzqqq` | 200 with an empty `data` and `meta.count = 0`. No weak matches are returned. |
| `test_validates_query` | An empty `q`, and a `q` of 201 characters | 422 with a validation error on `q`. |
| `test_returns_details_for_the_product_popup` | Searches `iphone 16` | The result includes `category`, `subcategory`, `description`, and `attributes` parsed from `"נפח: 128GB \| צבע: שחור"` into `[{label, value}, …]`. |
| `test_sends_security_headers` | `GET /` | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, and a CSP containing `script-src 'self'`. |
| `test_does_not_expose_internal_columns` | Searches `iphone` | The results contain no `embedding` and no `search_text`. |
| `test_reranker_is_not_called_when_disabled` | Searches with re-ranking off (the default) | The re-ranker is never called. |
| `test_reranker_decides_order_and_relevance` | Turns re-ranking on and searches `שואב אבק רובוטי` | Only the Roborock is returned. Its `score` is above 0.5 (a sigmoid of the logit). At most `rerank_top` candidates are sent to the re-ranker. |
| `test_reranker_can_empty_the_results` | Turns re-ranking on with a very high threshold (`rerank_min = 5`) | An empty result list: the re-ranker can reject every candidate. |
| `test_search_page_renders_rtl` | `GET /` | 200. The page has `dir="rtl"` and the product card template. |

## Unit tests

### `Unit/CsvCatalogReaderTest.php`: CSV import

| Test | What it does | Expected result |
|---|---|---|
| `test_reads_valid_rows_and_skips_invalid_ones` | Reads the fixture | 6 products. `"3,799.00"` becomes 3799.0 and `"₪ 449.90"` becomes 449.9. An empty image and an invalid URL become `null`, and a missing price is allowed. The skipped rows are reported as `[7 => 'empty name', 9 => 'invalid price "חינם"']`. |
| `test_matches_headers_by_alias_and_keeps_unknown_columns` | Reads a `;`-separated CSV with a UTF-8 BOM and Hebrew headers (`שם מוצר;מחיר;צבע`) | The name and price map correctly, the unknown column is kept in `extra` (`['צבע' => 'אדום']`), and an id is derived since the CSV has none. |
| `test_rejects_csv_without_required_columns` | A CSV with no name column | An `InvalidArgumentException` whose message mentions `name`. |
| `test_parses_prices` (5 cases) | `1,299.90` / `₪ 1299` / `1299 ש"ח` / `49,90` / `12,500` | 1299.9 / 1299.0 / 1299.0 / 49.9 / 12500.0. A comma is read as a decimal point when it is followed by 1–2 digits, and as a thousands separator otherwise. |

### `Unit/QueryExpanderTest.php`: Hebrew ↔ Latin brand spellings

| Test | Input | Expected variants |
|---|---|---|
| `test_hebrew_spelling_adds_latin_variant` | `אייפון 16`, `איפון` | `[אייפון 16, iphone 16]`, `[איפון, iphone]` |
| `test_latin_name_adds_hebrew_variant` | `iphone 16 pro` | `[iphone 16 pro, אייפון 16 pro]` |
| `test_handles_hebrew_prefix_letters` | `כיסוי לאייפון` | `[כיסוי לאייפון, כיסוי iphone]`: the prefix `ל` is stripped. |
| `test_matches_whole_words_only` | `iphones`, `global` | No expansion, so `lg` inside `global` is not touched. |
| `test_multi_word_spelling` | `מקרר אל ג'י` | `[מקרר אל ג'י, מקרר lg]` |

### `Unit/TextNormalizerTest.php`: text normalization (used on both queries and products)

| Case | Input | Expected |
|---|---|---|
| lowercases english | `iPhone 16 PRO` | `iphone 16 pro` |
| strips niqqud | `שָׁלוֹם` | `שלום` |
| gershayim to quote | `מק״ט` | `מק"ט` |
| collapses whitespace | `"  שואב \t  אבק\n"` | `שואב אבק` |
| mixed languages | `אייפון  iPhone` | `אייפון iphone` |

### `Unit/CharTrieTest.php`: tokenizer override

This guards the patched `overrides/CharTrie.php`, which replaces the vocabulary trie used by the transformers
library's tokenizer.

| Test | Expected result |
|---|---|
| `test_override_is_the_class_in_use` | The autoloader resolves `CharTrie` to `overrides/CharTrie.php`, not the vendor copy. |
| `test_finds_all_prefixes_shortest_first` | `commonPrefixSearch('▁שואב אבק')` returns `[▁ש, ▁שו, ▁שואב]`. Text with no matches returns `[]`. |
| `test_search_does_not_grow_the_trie` | The trie's node count is unchanged after searching unseen text, so it doesn't leak memory in the long-running worker. |

## Not covered by PHPUnit

- **Search relevance against the real model.** Run `docker compose exec app php artisan catalog:eval`, which scores
  hit@1, MRR and false positives on the labelled queries in `data/`. Run it before and after any ranking change.
- **Frontend behaviour** (the search box, the product popup, the loading and error states) has no automated
  browser tests. Check it manually.
