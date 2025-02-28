#!/bin/bash
# Script per eseguire il comando osm2cai:sync per tutti i modelli supportati.

echo "Inizio della sincronizzazione completa dei modelli da OSM2CAI legacy"

echo "Esecuzione del comando: osm2cai:sync MountainGroups"
php artisan osm2cai:sync MountainGroups

echo "Esecuzione del comando: osm2cai:sync NaturalSprings"
php artisan osm2cai:sync NaturalSpring

echo "Esecuzione del comando: osm2cai:sync Areas"
php artisan osm2cai:sync Area

echo "Esecuzione del comando: osm2cai:sync Sectors"
php artisan osm2cai:sync Sector

echo "Esecuzione del comando: osm2cai:sync Sections"
php artisan osm2cai:sync Club

echo "Esecuzione del comando: osm2cai:sync Itinerary"
php artisan osm2cai:sync Itinerary

echo "Esecuzione del comando: osm2cai:sync cai_huts"
php artisan osm2cai:sync CaiHut

echo "Tutti i modelli sono stati sincronizzati con successo."