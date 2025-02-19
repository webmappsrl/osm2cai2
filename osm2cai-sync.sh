#!/bin/bash
# Script per eseguire in sequenza i comandi Laravel necessari per sincronizzare i dati dal database legacy di osm2cai.
echo "Esecuzione del comando: osm2cai:sync-users"
php artisan osm2cai:sync-users

echo "Esecuzione del comando: osm2cai:associate-users-to-ec-pois"
php artisan osm2cai:associate-users-to-ec-pois

echo "Esecuzione del comando: osm2cai:set-hr-osm2cai-status-4"
php artisan osm2cai:set-hr-osm2cai-status-4

echo "Esecuzione del comando: osm2cai:import-hiking-routes-issues"
php artisan osm2cai:import-hiking-routes-issues

echo "Esecuzione del comando: osm2cai:sync-hiking-routes-validator-from-legacy"
php artisan osm2cai:sync-hiking-routes-validator-from-legacy

echo "Esecuzione del comando: osm2cai:sync-hr-feature-image-from-legacy"
php artisan osm2cai:sync-hr-feature-image-from-legacy

echo "Esecuzione del comando: osm2cai:sync-ugc"
php artisan osm2cai:sync-ugc

echo "Esecuzione del comando: osm2cai:update-hr-region-favorite-from-legacy"
php artisan osm2cai:update-hr-region-favorite-from-legacy

echo "Esecuzione del comando: osm2cai:associate-clubs-to-hiking-routes"
php artisan osm2cai:associate-clubs-to-hiking-routes

echo "Esecuzione del comando: osm2cai:check-hiking-routes-geometry"
php artisan osm2cai:check-hiking-routes-geometry

echo "Esecuzione del comando: osm2cai:check-hiking-routes-existence-on-osm (dispatch jobs)"
php artisan osm2cai:check-hiking-routes-existence-on-osm

echo "Tutti i comandi sono stati eseguiti."
