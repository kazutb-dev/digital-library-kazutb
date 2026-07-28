# ──────────────────────────────────────────────────────────────
#  Digital Library — Dockerfile (Laravel 13 / PHP 8.3)
# ──────────────────────────────────────────────────────────────

FROM php:8.4-fpm

# ── System dependencies ────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libpq-dev \
    libzip-dev \
    curl \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    pcntl \
    bcmath \
    opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Composer ───────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── PHP configuration ──────────────────────────────────────────
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

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

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
