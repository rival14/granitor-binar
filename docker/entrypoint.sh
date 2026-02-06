#!/bin/sh
set -e

echo "[Entrypoint] Starting application setup..."

# Ensure .env exists (excluded from image via .dockerignore, env vars come from docker-compose)
if [ ! -f /app/.env ]; then
    echo "[Entrypoint] Creating .env from environment variables..."
    touch /app/.env
fi

# Clear stale bootstrap cache (may reference dev-only providers from local)
echo "[Entrypoint] Clearing bootstrap cache..."
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

# Rebuild package manifest (skipped during build to avoid dev-dependency errors)
echo "[Entrypoint] Discovering packages..."
php artisan package:discover --ansi

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "[Entrypoint] Generating application key..."
    php artisan key:generate --force
fi

# Run migrations
echo "[Entrypoint] Running database migrations..."
php artisan migrate --force

# Cache configuration for production performance
if [ "$APP_ENV" = "production" ]; then
    echo "[Entrypoint] Caching configuration for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Ensure storage directories have correct permissions
chown -R www-data:www-data /app/storage /app/bootstrap/cache

echo "[Entrypoint] Setup complete. Starting FrankenPHP..."

exec "$@"
