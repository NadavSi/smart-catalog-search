#!/bin/sh
# Runs the PHPUnit suite against the stack's Postgres (database assignmentsDB_test).
# Requires `docker compose up -d` first. Only this project's folder is mounted.
set -e
cd "$(dirname "$0")/.."
. ./.env
docker compose run --rm --no-deps -u "$(id -u):$(id -g)" \
  -v "$PWD/src:/app" \
  -e DB_PASSWORD="$DB_PASSWORD" -e DB_DATABASE=assignmentsDB_test \
  -e APP_ENV=testing -e APP_URL=http://localhost \
  --entrypoint sh app -c '[ -d vendor/phpunit ] || composer install --no-interaction -q; vendor/bin/phpunit "$@"' sh "$@"
