#!/bin/sh
set -e

# Aplica migrações e gera caches de configuração/rotas a cada subida do container.
php artisan migrate --force
php artisan optimize

exec "$@"
