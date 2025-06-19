#!/bin/bash

# Script per rollback selettivo delle migrazioni WM-Package
# Da eseguire SOLO all'interno del container PHP (php81_osm2cai2)

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

WM_PACKAGE_MIGRATIONS=(
    "2025_01_05_000000_create_layerables_table"
    "2025_01_06_000000_update_layerables_references_to_hiking_route"
    "2025_06_04_131953_create_apps_table"
    "2025_06_04_133022_create_layer_associated_app"
    "2025_06_04_133024_create_layers_table"
    "2025_06_04_143244_create_taxonomy_activities_table"
    "2025_06_04_143245_create_taxonomy_activityables_table"
    "2025_06_04_143246_create_taxonomy_poi_typeables_table"
    "2025_06_04_143247_create_taxonomy_poi_types_table"
    "2025_06_04_143248_create_taxonomy_targetables_table"
    "2025_06_04_143249_create_taxonomy_targets_table"
    "2025_06_04_143250_create_taxonomy_whenables_table"
    "2025_06_04_143251_create_taxonomy_whens_table"
    "2025_06_04_143255_z_add_foreign_keys_to_app_layer_table"
    "2025_06_04_143256_z_add_foreign_keys_to_apps_table"
    "2025_06_05_105602_z_add_user_foreign_key_to_layers_table"
    "2025_06_05_111420_z_add_foreign_keys_to_hiking_routes_table"
    "2025_06_05_111425_z_add_foreign_keys_to_taxonomy_activityables_table"
    "2025_06_05_111426_z_add_foreign_keys_to_taxonomy_poi_typeables_table"
    "2025_06_05_111427_z_add_foreign_keys_to_taxonomy_targetables_table"
    "2025_06_05_111428_z_add_foreign_keys_to_taxonomy_whenables_table"
    "2025_06_10_111003_update_media_table_for_wm_package"
    "2025_06_12_143945_convert_hr_geometry_to_3_d"
    "2025_06_18_103441_add_properties_createdby_geohubid_to_ugc_pois_table"
)

echo -e "${BLUE}Rollback selettivo delle migrazioni WM-Package...${NC}"

# Ottieni lo stato attuale delle migrazioni
migration_output=$(php artisan migrate:status 2>/dev/null)

# Array per memorizzare le migrazioni da rollback (in ordine inverso)
migrations_to_rollback=()

for (( idx=${#WM_PACKAGE_MIGRATIONS[@]}-1 ; idx>=0 ; idx-- )) ; do
    migration="${WM_PACKAGE_MIGRATIONS[idx]}"
    if echo "$migration_output" | grep -q "$migration.*Ran"; then
        migrations_to_rollback+=("$migration")
    fi
done

if [[ ${#migrations_to_rollback[@]} -eq 0 ]]; then
    echo -e "${GREEN}Nessuna migrazione WM-Package da rollback.${NC}"
    exit 0
fi

for migration in "${migrations_to_rollback[@]}"; do
    echo -e "${YELLOW}Rollback: $migration${NC}"
    migration_file=$(find database/migrations -name "*_${migration}.php" 2>/dev/null | head -1)
    if [[ -n "$migration_file" ]]; then
        if php artisan migrate:rollback --path="$migration_file"; then
            echo -e "${GREEN}   ✅ Rollback completato: $migration${NC}"
        else
            echo -e "${RED}   ❌ Errore rollback: $migration${NC}"
        fi
    else
        echo -e "${RED}   ❌ File migrazione non trovato: $migration${NC}"
    fi
done

echo -e "${BLUE}Rollback completato.${NC}"