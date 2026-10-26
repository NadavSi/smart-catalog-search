# One image for the web app and the CLI (import, tests).
# FrankenPHP = PHP + Caddy in a single binary, so no separate nginx/php-fpm.
FROM dunglas/frankenphp:1-php8.4-bookworm AS base

# ffi: TransformersPHP runs the ONNX embedding model through it.
RUN install-php-extensions pdo_pgsql ffi intl zip opcache pcntl \
 && apt-get update && apt-get install -y --no-install-recommends git unzip curl libgomp1 \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /app

FROM base AS app
COPY src/composer.json src/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY src/ ./
RUN composer dump-autoload --optimize --no-dev \
 && mkdir -p storage/framework/cache storage/framework/views storage/logs storage/models \
 && chown -R www-data:www-data storage bootstrap/cache

# Bake the embedding model into the image so the first search isn't a 100MB download.
RUN php artisan catalog:download-model

# Run unprivileged. Caddy (inside FrankenPHP) keeps its state under XDG dirs.
ENV XDG_CONFIG_HOME=/tmp/caddy-config XDG_DATA_HOME=/tmp/caddy-data
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]
# Octane worker mode: each worker loads the model once and keeps it in memory
# (~1.1GB per worker for bge-m3, hence one by default).
CMD ["sh", "-c", "exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers=${OCTANE_WORKERS:-1} --max-requests=10000 --log-level=warn"]
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s CMD curl -fsS http://127.0.0.1:8000/up || exit 1
