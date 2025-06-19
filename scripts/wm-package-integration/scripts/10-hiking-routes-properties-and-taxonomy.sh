#!/bin/bash

# Script to perform post-import operations for Hiking Routes:
# 1. Populate the properties JSON field from osmfeatures_data.
# 2. Dispatch jobs to get taxonomy wheres.

set -e

# Colors for output
BLUE='\033[0;34m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Populating Hiking Routes properties from osmfeatures_data...${NC}"
php artisan osm2cai:populate-properties --model=HikingRoute
echo -e "${GREEN}✅ Hiking Routes properties population command dispatched successfully.${NC}"

echo -e "\n${BLUE}🚀 Dispatching jobs to get taxonomy wheres for Hiking Routes...${NC}"
php artisan osm2cai:get-taxonomy-where-from-osmfeatures
echo -e "${GREEN}✅ Jobs for taxonomy wheres dispatched successfully.${NC}" 