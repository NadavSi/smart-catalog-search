# Smart Catalog Search — notes for Claude

- Laravel app lives in `src/`; Docker files in `docker/`; catalog CSV and labelled eval queries in `data/`.
- Never install PHP/Composer on the host. Run everything through the containers:
  - tests: `./scripts/test.sh` (uses the `assignmentsDB_test` database, never the real one)
  - artisan: `docker compose exec app php artisan …`
  - composer: `docker compose run --rm --no-deps -v "$PWD/src:/app" --entrypoint composer app …`
- Mount only paths inside this folder; keep the stack on its own compose network.
- Any change to ranking (model, weights, thresholds, synonyms) must be checked with
  `php artisan catalog:eval` before and after, and the numbers reported.
- Never commit `.env` or any key.
