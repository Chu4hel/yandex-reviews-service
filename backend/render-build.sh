#!/usr/bin/env bash
# exit on error
set -o errexit

# Install composer dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Prepare SQLite DB
mkdir -p database
touch database/database.sqlite

# Run migrations and seed data
php artisan migrate --force
php artisan db:seed --force

# Optimize
php artisan config:cache
php artisan route:cache
