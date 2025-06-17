#!/bin/bash

# Script per gestione intelligente delle migrazioni OSM2CAI2
# Controlla lo stato delle migrazioni e fa rollback automatico se necessario prima di riapplicarle

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Variabili
DRY_RUN=false
FORCE_ROLLBACK=false
SKIP_ROLLBACK=false

# Migrazioni WM-Package che potrebbero richiedere rollback (batch 16-17)
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
)

# Funzione di help
show_help() {
    echo -e "${BLUE}🔄 Script Gestione Migrazioni OSM2CAI2${NC}"
    echo -e "${BLUE}======================================${NC}"
    echo
    echo "Gestisce intelligentemente le migrazioni con controllo stato e rollback automatico."
    echo
    echo "Utilizzo: $0 [opzioni]"
    echo
    echo "Opzioni:"
    echo "  --dry-run          Mostra cosa verrà fatto senza eseguire"
    echo "  --force-rollback   Forza il rollback anche se le migrazioni sono OK"
    echo "  --skip-rollback    Salta il rollback e applica solo le migrazioni"
    echo "  --help             Mostra questo help"
    echo
    echo "Esempi:"
    echo "  $0                     # Gestione automatica intelligente"
    echo "  $0 --dry-run           # Test senza modifiche"
    echo "  $0 --force-rollback    # Forza rollback + riapplica"
    echo "  $0 --skip-rollback     # Solo applica migrazioni"
    echo
    echo "Comportamento:"
    echo "  1. Controlla stato migrazioni WM-Package"
    echo "  2. Se già applicate -> rollback automatico se non --skip-rollback"
    echo "  3. Applica tutte le migrazioni"
    echo "  4. Verifica successo operazione"
}

# Parse parametri
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --force-rollback)
            FORCE_ROLLBACK=true
            shift
            ;;
        --skip-rollback)
            SKIP_ROLLBACK=true
            shift
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Parametro sconosciuto: $1${NC}"
            show_help
            exit 1
            ;;
    esac
done

# Funzione per verificare se siamo nel container Docker
check_docker_context() {
    if [[ ! -f "/.dockerenv" ]]; then
        echo -e "${RED}❌ Questo script deve essere eseguito dall'interno del container Docker PHP${NC}"
        echo -e "${YELLOW}💡 Esegui: docker exec php81_osm2cai2 bash -c \"cd /var/www/html/osm2cai2 && $0\"${NC}"
        exit 1
    fi
}

# Funzione per ottenere lo stato delle migrazioni
get_migration_status() {
    # Anche in dry-run dobbiamo ottenere lo stato reale per decidere cosa fare
    php artisan migrate:status 2>/dev/null || {
        echo -e "${RED}❌ Errore nel recupero stato migrazioni${NC}"
        exit 1
    }
}

# Funzione per controllare se le migrazioni WM-Package sono già applicate
check_wm_package_migrations() {
    echo -e "${BLUE}🔍 Controllo stato migrazioni WM-Package...${NC}"
    
    local migration_output
    migration_output=$(get_migration_status)
    
    local applied_count=0
    local total_count=${#WM_PACKAGE_MIGRATIONS[@]}
    
    for migration in "${WM_PACKAGE_MIGRATIONS[@]}"; do
        if echo "$migration_output" | grep -q "$migration.*Ran"; then
            ((applied_count++))
        fi
    done
    
    echo -e "${PURPLE}📊 Stato migrazioni WM-Package: $applied_count/$total_count applicate${NC}"
    
    if [[ $applied_count -gt 0 ]]; then
        return 0  # Alcune migrazioni sono applicate
    else
        return 1  # Nessuna migrazione applicata
    fi
}

# Funzione per fare rollback delle migrazioni WM-Package
rollback_wm_package_migrations() {
    echo -e "${YELLOW}🔄 Rollback migrazioni WM-Package specifiche...${NC}"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${YELLOW}[DRY-RUN]${NC} Farebbe rollback SOLO delle migrazioni WM-Package specificate"
        for migration in "${WM_PACKAGE_MIGRATIONS[@]}"; do
            echo -e "${YELLOW}[DRY-RUN]${NC}   - $migration"
        done
        return 0
    fi
    
    # Ottieni lo stato attuale delle migrazioni
    local migration_output
    migration_output=$(get_migration_status)
    
    # Array per memorizzare le migrazioni da fare rollback (in ordine inverso)
    local migrations_to_rollback=()
    
    # Controlla quali migrazioni WM-Package sono effettivamente applicate
    echo -e "${BLUE}🔍 Identificazione migrazioni WM-Package applicate...${NC}"
    for migration in "${WM_PACKAGE_MIGRATIONS[@]}"; do
        if echo "$migration_output" | grep -q "$migration.*Ran"; then
            migrations_to_rollback=("$migration" "${migrations_to_rollback[@]}")
            echo -e "${YELLOW}   📋 Trovata applicata: $migration${NC}"
        fi
    done
    
    if [[ ${#migrations_to_rollback[@]} -eq 0 ]]; then
        echo -e "${GREEN}✅ Nessuna migrazione WM-Package da rollback${NC}"
        return 0
    fi
    
    echo -e "${YELLOW}📋 Rollback di ${#migrations_to_rollback[@]} migrazioni WM-Package specifiche...${NC}"
    
    # Strategia: fare rollback usando il file specifico della migrazione
    local rollback_errors=0
    
    for migration in "${migrations_to_rollback[@]}"; do
        echo -e "${YELLOW}   🔄 Rollback: $migration${NC}"
        
        # Trova il file della migrazione
        local migration_file
        migration_file=$(find database/migrations -name "*_${migration}.php" 2>/dev/null | head -1)
        
        if [[ -n "$migration_file" ]]; then
            # Prova a fare rollback usando il path specifico
            if php artisan migrate:rollback --path="$migration_file" 2>/dev/null; then
                echo -e "${GREEN}   ✅ Rollback completato: $migration${NC}"
            else
                echo -e "${YELLOW}   ⚠️  Rollback alternativo per: $migration${NC}"
                # Fallback: prova con un singolo step (più sicuro)
                if ! php artisan migrate:rollback --step=1 2>/dev/null; then
                    echo -e "${RED}   ❌ Errore rollback: $migration${NC}"
                    ((rollback_errors++))
                fi
            fi
        else
            echo -e "${RED}   ❌ File migrazione non trovato: $migration${NC}"
            ((rollback_errors++))
        fi
    done
    
    # Verifica finale
    migration_output=$(get_migration_status)
    local still_applied=0
    
    for migration in "${WM_PACKAGE_MIGRATIONS[@]}"; do
        if echo "$migration_output" | grep -q "$migration.*Ran"; then
            ((still_applied++))
        fi
    done
    
    if [[ $still_applied -eq 0 ]]; then
        echo -e "${GREEN}✅ Rollback completato - tutte le migrazioni WM-Package specifiche sono state rollback${NC}"
    else
        echo -e "${YELLOW}⚠️  $still_applied migrazioni WM-Package ancora applicate${NC}"
        if [[ $rollback_errors -gt 0 ]]; then
            echo -e "${RED}⚠️  Si sono verificati $rollback_errors errori durante il rollback${NC}"
            echo -e "${BLUE}💡 Le migrazioni rimanenti potrebbero avere dipendenze. Procedo comunque...${NC}"
        fi
    fi
    
    echo -e "${GREEN}✅ Rollback selettivo completato (solo migrazioni WM-Package)${NC}"
}

# Funzione per applicare le migrazioni
apply_migrations() {
    echo -e "${BLUE}🚀 Applicazione migrazioni...${NC}"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${YELLOW}[DRY-RUN]${NC} Applicherebbe tutte le migrazioni"
        return 0
    fi
    
    if php artisan migrate --force; then
        echo -e "${GREEN}✅ Migrazioni applicate con successo${NC}"
    else
        echo -e "${RED}❌ Errore durante l'applicazione delle migrazioni${NC}"
        exit 1
    fi
}

# Funzione per verificare lo stato finale
verify_final_status() {
    echo -e "${BLUE}🔍 Verifica stato finale migrazioni...${NC}"
    
    local migration_output
    migration_output=$(get_migration_status)
    
    local failed_count=0
    for migration in "${WM_PACKAGE_MIGRATIONS[@]}"; do
        if ! echo "$migration_output" | grep -q "$migration.*Ran"; then
            echo -e "${RED}❌ Migrazione non applicata: $migration${NC}"
            ((failed_count++))
        fi
    done
    
    if [[ $failed_count -eq 0 ]]; then
        echo -e "${GREEN}✅ Tutte le migrazioni WM-Package sono state applicate correttamente${NC}"
        return 0
    else
        echo -e "${RED}❌ $failed_count migrazioni WM-Package non sono state applicate${NC}"
        return 1
    fi
}

# Main
check_docker_context

# Controlla se le migrazioni WM-Package sono già applicate
if check_wm_package_migrations; then
    if [[ "$SKIP_ROLLBACK" == "true" ]]; then
        echo -e "${YELLOW}⚠️  Migrazioni WM-Package già applicate, ma --skip-rollback specificato${NC}"
    elif [[ "$FORCE_ROLLBACK" == "true" ]]; then
        echo -e "${YELLOW}⚠️  Forzato rollback delle migrazioni WM-Package...${NC}"
        rollback_wm_package_migrations
    else
        echo -e "${YELLOW}⚠️  Migrazioni WM-Package già applicate, procedo con rollback...${NC}"
        rollback_wm_package_migrations
    fi
else
    echo -e "${GREEN}✅ Nessuna migrazione WM-Package applicata, procedo con l'applicazione${NC}"
fi

# Applica le migrazioni
apply_migrations

# Verifica lo stato finale
verify_final_status

# Esegui solo se non sourcato
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi 