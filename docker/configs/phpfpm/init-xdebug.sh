#!/bin/bash

source .env
containerName="php81_$APP_NAME"

# Install xdebug without interactive terminal for automated execution
echo "Installing Xdebug in container $containerName..."
docker exec -u 0 $containerName pecl install xdebug

# Copy xdebug configuration
echo "Copying Xdebug configuration..."
docker cp ./docker/configs/phpfpm/xdebug.ini $containerName:/usr/local/etc/php/conf.d/.

# Restart PHP-FPM to load the extension
echo "Restarting PHP-FPM to load Xdebug..."
docker exec -u 0 $containerName pkill -USR2 php-fpm

echo "Xdebug installation completed!"
