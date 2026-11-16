# syntax=docker/dockerfile:1
# Backend Laravel — PHP-FPM 8.5 (Alpine). Alvos: "dev" (desenvolvimento) e "prod" (padrão).

# Versões fixadas. PHP 8.5.12 corrige CVE-2026-109618 e CVE-2026-109619 (RCE, php.net 21/10/2026).
# Ao atualizar, consulte https://www.php.net/archive/ e ajuste PHP_MIN abaixo.
FROM php:8.5.12-fpm-alpine AS base
ARG PHP_MIN=8.5.12
RUN php -r 'exit(version_compare(PHP_VERSION, getenv("PHP_MIN"), ">=") ? 0 : 1);' \
    || (echo "PHP $(php -r 'echo PHP_VERSION;') < $PHP_MIN: versão vulnerável" && exit 1)
# opcache já vem embutido no PHP 8.5; só falta o driver do PostgreSQL.
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && apk del .build \
    && apk upgrade --no-cache
COPY --from=composer:2.11.2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# ---------- Desenvolvimento: código montado por volume, sem PHP na máquina ----------
FROM base AS dev
ENV COMPOSER_HOME=/tmp/composer
# Instala dependências se faltarem, recria o banco (efêmero) e sobe o servidor.
# O código vem por volume: alterações valem na próxima requisição, sem rebuild.
# (php -S em vez de "artisan serve": este último relê o .env e ignora as variáveis do compose.)
CMD ["sh", "-c", "[ -d vendor ] || composer install; php artisan migrate:fresh --force && cd public && exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]

# ---------- Produção ----------
FROM base AS prod
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf "expose_php=Off\nopcache.validate_timestamps=0\n" > "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN rm -f .env \
    && composer dump-autoload --optimize --no-dev \
    && composer audit --no-dev --locked \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm /usr/bin/composer

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
