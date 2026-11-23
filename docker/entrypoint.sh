#!/usr/bin/env bash
set -e

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan optimize
fi

exec "$@"
