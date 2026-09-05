#!/bin/bash
set -e

echo "=========================================="
echo " WhiteJersey Cafe POS - Container Startup"
echo "=========================================="

# ── Configure Nginx to listen on the platform-provided port ──
# Railway/Fly set $PORT; locally we default to 80.
export NGINX_PORT="${PORT:-80}"
envsubst '\$NGINX_PORT' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default
echo "Nginx configured to listen on port ${NGINX_PORT}"

# ── SQLite database setup ──
# The DB file lives on a persistent volume so data survives redeploys.
# DB_DATABASE should be an absolute path (e.g. /var/www/html/storage/app/database/database.sqlite).
DB_FILE="${DB_DATABASE:-/var/www/html/storage/app/database/database.sqlite}"
echo "[1/5] Ensuring SQLite database exists at ${DB_FILE}..."
mkdir -p "$(dirname "$DB_FILE")"
if [ ! -f "$DB_FILE" ]; then
    touch "$DB_FILE"
    echo "  Created new SQLite database file."
else
    echo "  Existing SQLite database found."
fi
chown -R www-data:www-data "$(dirname "$DB_FILE")" 2>/dev/null || true
chmod -R 775 "$(dirname "$DB_FILE")" 2>/dev/null || true

if [ -f artisan ]; then
    echo "[2/5] Running database migrations..."
    php artisan migrate --force || {
        echo "ERROR: Database migration failed - the application cannot start without a valid schema" >&2
        exit 1
    }
    echo "  Migrations completed."

    echo "[3/5] Seeding initial data (idempotent)..."
    php artisan db:seed --force 2>&1 || echo "  WARNING: Seeding skipped or already done."

    if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
        echo "[4/5] Generating application key..."
        php artisan key:generate --force
    else
        echo "[4/5] Application key already set."
    fi

    echo "[5/5] Configuring for ${APP_ENV:-production} environment..."
    if [ "$APP_ENV" = "production" ]; then
        php artisan config:clear 2>/dev/null || true
        php artisan config:cache || echo "WARNING: Config cache failed" >&2
        php artisan route:cache  || echo "WARNING: Route cache failed" >&2
        php artisan view:cache   || echo "WARNING: View cache failed" >&2
        echo "  Production caches generated."
    else
        php artisan config:clear 2>/dev/null || true
        php artisan route:clear 2>/dev/null || true
        php artisan view:clear 2>/dev/null || true
        echo "  Development mode - caches cleared."
    fi
else
    echo "ERROR: Laravel artisan not found - application files may be missing" >&2
    exit 1
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo "=========================================="
echo " Application ready - starting services"
echo " Listening on port ${NGINX_PORT}"
echo "=========================================="

exec "$@"
