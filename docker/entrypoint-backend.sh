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

PORT="${PORT:-8000}"
echo "Starting Laravel application on port $PORT..."
exec php artisan serve --host=0.0.0.0 --port="$PORT"
