# ──────────────────────────────────────────────────────────────
#  Digital Library — Dockerfile (Laravel 13 / PHP 8.3)
# ──────────────────────────────────────────────────────────────

FROM postgres:18 AS pgtools

FROM php:8.4-fpm

# ── System dependencies ────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libpq-dev \
    libzip-dev \
    libldap2-dev \
    freetds-dev \
    curl \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu \
    && docker-php-ext-install \
    pdo \
    pdo_dblib \
    pdo_pgsql \
    pgsql \
    zip \
    ldap \
    pcntl \
    bcmath \
    opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Match the PostgreSQL 18 server exactly. Debian's default client is currently
# older and pg_dump intentionally refuses a newer server.
COPY --from=pgtools /usr/lib/postgresql/18/bin/pg_dump /usr/local/bin/pg_dump
COPY --from=pgtools /usr/lib/postgresql/18/bin/pg_restore /usr/local/bin/pg_restore
COPY --from=pgtools /usr/lib/postgresql/18/bin/createdb /usr/local/bin/createdb

# ── Composer ───────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── PHP configuration ──────────────────────────────────────────
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf

# ── Nginx configuration ────────────────────────────────────────
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-available/laravel.conf
RUN ln -s /etc/nginx/sites-available/laravel.conf /etc/nginx/sites-enabled/laravel.conf

# ── Supervisor configuration ───────────────────────────────────
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf

# ── Application ────────────────────────────────────────────────
WORKDIR /app

# Install PHP dependencies first (layer caching)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# Copy application source
COPY . .

# Never ship a stale Vite hot-file into the production image.
RUN rm -f /app/public/hot

# ── Permissions ────────────────────────────────────────────────
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# ── Entrypoint ─────────────────────────────────────────────────
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["/entrypoint.sh"]
