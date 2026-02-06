# ==============================================================================
# Stage 1: Composer dependencies
# ==============================================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-scripts

# ==============================================================================
# Stage 2: FrankenPHP production image
# ==============================================================================
FROM dunglas/frankenphp:1-php8.4-alpine AS production

LABEL maintainer="Granitor Binar"

# Install PHP extensions required by Laravel
RUN install-php-extensions \
    pdo_mysql \
    bcmath \
    opcache \
    zip \
    intl \
    pcntl \
    redis

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-app.ini"

WORKDIR /app

# Copy application source + vendor dependencies from builder stage
COPY --from=vendor /app /app

# Caddy configuration for Laravel
COPY Caddyfile /etc/caddy/Caddyfile

# Create necessary directories and set permissions
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
EXPOSE 443

ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
