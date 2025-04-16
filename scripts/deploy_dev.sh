#!/bin/bash
set -e

echo "Production deployment started ..."

php artisan down

composer install --no-interaction --prefer-dist --optimize-autoloader
composer dump-autoload

# Update nova assets for the custom login page
# https://github.com/Muetze42/nova-assets-changer
php artisan nova:custom-assets

# Clear and cache config
php artisan config:cache
php artisan config:clear

# Clear the old cache
php artisan clear-compiled

php artisan optimize

php artisan migrate --force

# gracefully terminate laravel horizon
php artisan horizon:terminate

php artisan up

echo "Deployment finished!"
