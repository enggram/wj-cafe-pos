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


# Wait for MySQL to be ready
echo "[1/5] Waiting for database to be ready..."
MAX_RETRIES=30
RETRY_COUNT=0

while ! php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'ok'; } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "ERROR: Database service failed to start - could not connect after ${MAX_RETRIES} attempts ($(($MAX_RETRIES * 2))s elapsed)" >&2
        exit 1
    fi
    echo "  Database not ready yet... retrying ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done

echo "  Database connection established."

# Run migrations and seed if artisan exists
if [ -f artisan ]; then
    echo "[2/5] Running database migrations..."
    php artisan migrate --force || {
        echo "ERROR: Database migration failed - the application cannot start without a valid schema" >&2
        exit 1
    }
    echo "  Migrations completed."

    # Seed database on first boot (when categories table is empty)
    echo "[3/5] Checking if initial data seeding is required..."
    NEEDS_SEED=$(php artisan tinker --execute="echo \App\Models\Category::count() === 0 ? 'yes' : 'no';" 2>/dev/null || echo "yes")
    if [ "$NEEDS_SEED" = "yes" ]; then
        echo "  Running initial data seeder..."
        php artisan db:seed --force || {
            echo "ERROR: Database seeding failed - initial data could not be loaded" >&2
            exit 1
        }
        echo "  Initial data seeded successfully."
    else
        echo "  Database already contains data, skipping seed."
    fi

    # Generate application key if not set
    if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
        echo "[4/5] Generating application key..."
        php artisan key:generate --force
    else
        echo "[4/5] Application key already set."
    fi

    # Cache configuration in production
    echo "[5/5] Configuring for ${APP_ENV:-production} environment..."
    if [ "$APP_ENV" = "production" ]; then
        php artisan config:cache || {
            echo "WARNING: Config cache failed, continuing without cache" >&2
        }
        php artisan route:cache || {
            echo "WARNING: Route cache failed, continuing without cache" >&2
        }
        php artisan view:cache || {
            echo "WARNING: View cache failed, continuing without cache" >&2
        }
        echo "  Production caches generated."
    else
        # Clear caches in non-production to ensure fresh state
        php artisan config:clear 2>/dev/null || true
        php artisan route:clear 2>/dev/null || true
        php artisan view:clear 2>/dev/null || true
        echo "  Development mode - caches cleared."
    fi
else
    echo "ERROR: Laravel artisan not found - application files may be missing" >&2
    exit 1
fi

# Set correct permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo "=========================================="
echo " Application ready - starting services"
echo " Listening on port 80"
echo "=========================================="

exec "$@"
