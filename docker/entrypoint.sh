#!/bin/sh
set -e

# Schema is idempotent; safe on every start.
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan view:cache

exec "$@"
