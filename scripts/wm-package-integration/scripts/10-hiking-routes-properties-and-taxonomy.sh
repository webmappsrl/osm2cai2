#!/bin/bash

# Script to perform post-import operations for Hiking Routes:
# 1. Populate the properties JSON field from osmfeatures_data.
# 2. Dispatch jobs to get taxonomy wheres.

set -e

# Carica le variabili dal file .env se esiste
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../" && pwd)"

if [ -f "$PROJECT_ROOT/.env" ]; then
    set -o allexport
    source "$PROJECT_ROOT/.env"
    set +o allexport
else
    echo "❌ File .env non trovato nella root del progetto."
    exit 1
fi

CONTAINER_NAME="php81-${APP_NAME}"

# Colors for output
BLUE='\033[0;34m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Populating Hiking Routes properties from osmfeatures_data...${NC}"
docker exec "$CONTAINER_NAME" bash -c "cd /var/www/html/osm2cai2 && php artisan osm2cai:populate-properties --model=HikingRoute"
echo -e "${GREEN}✅ Hiking Routes properties population command dispatched successfully.${NC}"

echo -e "\n${BLUE}🚀 Dispatching jobs to get taxonomy wheres for Hiking Routes...${NC}"
docker exec "$CONTAINER_NAME" bash -c "cd /var/www/html/osm2cai2 && php artisan osm2cai:get-taxonomy-where-from-osmfeatures"
echo -e "${GREEN}✅ Jobs for taxonomy wheres dispatched successfully.${NC}" 