#!/bin/sh
set -e

mkdir -p /var/www/html/database
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
fi

# Ensure storage directories exist and writable
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
chmod -R 777 /var/www/html/storage /var/www/html/database

# Run migrations and seed default user and demo organization
php artisan migrate --force
php artisan db:seed --force

# Start background queue worker if QUEUE_CONNECTION is database
if [ "$QUEUE_CONNECTION" = "database" ]; then
    echo "Starting Laravel queue worker in background..."
    php artisan queue:work --sleep=1 --tries=3 --timeout=600 &
fi

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
PORT="${PORT:-8000}"
echo "Starting Laravel application on port $PORT with $PHP_CLI_SERVER_WORKERS workers..."
exec php artisan serve --host=0.0.0.0 --port="$PORT"

