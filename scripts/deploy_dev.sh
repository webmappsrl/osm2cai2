#!/bin/bash
set -e

echo "Deployment started ..."

# Enter maintenance mode or return true
# if already is in maintenance mode
(php artisan down) || true

git submodule update --init --recursive

# Install composer dependencies
composer install  --no-interaction --prefer-dist --optimize-autoloader

# Update nova assets for the custom login page
# https://github.com/Muetze42/nova-assets-changer
php artisan nova:custom-assets

# Run database migrations
php artisan migrate

# Create admin user
php artisan db:seed

php artisan optimize:clear
php artisan config:clear

# Exit maintenance mode
php artisan up

echo "Deployment finished!"
