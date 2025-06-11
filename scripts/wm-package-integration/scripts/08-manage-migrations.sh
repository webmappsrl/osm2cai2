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
    echo "  2. Se già applicate -> rollback automatico"
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
    echo -e "${YELLOW}🔄 Rollback migrazioni WM-Package...${NC}"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${YELLOW}[DRY-RUN]${NC} Farebbe rollback delle migrazioni WM-Package"
        return 0
    fi
    
    # Approccio semplificato: rollback degli ultimi 2 batch che contengono le migrazioni WM-Package
    echo -e "${YELLOW}📋 Rollback batch 17 (migrazioni WM-Package principali)...${NC}"
    if ! php artisan migrate:rollback --step=20 2>/dev/null; then
        echo -e "${YELLOW}⚠️  Batch 17 già rollback o parzialmente rollback${NC}"
    fi
    
    echo -e "${YELLOW}📋 Rollback batch 16 (migrazioni layerables)...${NC}"
    if ! php artisan migrate:rollback --step=5 2>/dev/null; then
        echo -e "${YELLOW}⚠️  Batch 16 già rollback o parzialmente rollback${NC}"
    fi
    
    # Verifica finale
    local migration_output
    migration_output=$(get_migration_status)
    
    local still_applied=0
    for migration in "${WM_PACKAGE_MIGRATIONS[@]}"; do
        if echo "$migration_output" | grep -q "$migration.*Ran"; then
            ((still_applied++))
        fi
    done
    
    if [[ $still_applied -eq 0 ]]; then
        echo -e "${GREEN}✅ Rollback completato - tutte le migrazioni WM-Package sono state rollback${NC}"
    else
        echo -e "${YELLOW}⚠️  $still_applied migrazioni WM-Package ancora applicate (procedo comunque)${NC}"
    fi
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
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${YELLOW}[DRY-RUN]${NC} Verificherebbe stato finale"
        return 0
    fi
    
    local migration_output
    migration_output=$(get_migration_status)
    
    local applied_count=0
    local pending_count=0
    
    for migration in "${WM_PACKAGE_MIGRATIONS[@]}"; do
        if echo "$migration_output" | grep -q "$migration.*Ran"; then
            ((applied_count++))
        else
            ((pending_count++))
        fi
    done
    
    echo -e "${PURPLE}📊 Stato finale WM-Package: $applied_count applicate, $pending_count in sospeso${NC}"
    
    if [[ $pending_count -eq 0 ]]; then
        echo -e "${GREEN}✅ Tutte le migrazioni WM-Package sono applicate${NC}"
    else
        echo -e "${YELLOW}⚠️  $pending_count migrazioni WM-Package ancora in sospeso${NC}"
    fi
    
    # Conta tutte le migrazioni in sospeso
    local total_pending
    total_pending=$(echo "$migration_output" | grep -c "Pending" || echo "0")
    
    if [[ $total_pending -eq 0 ]]; then
        echo -e "${GREEN}✅ Tutte le migrazioni del progetto sono applicate${NC}"
    else
        echo -e "${BLUE}📋 $total_pending migrazioni totali ancora in sospeso${NC}"
    fi
}

# Funzione principale
main() {
    echo -e "${BLUE}🔄 Gestione Migrazioni OSM2CAI2${NC}"
    echo -e "${BLUE}===============================${NC}"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${YELLOW}🧪 Modalità DRY-RUN attiva - nessuna modifica verrà applicata${NC}"
        echo
    fi
    
    # Verifica contesto Docker
    check_docker_context
    
    # Determina se serve rollback
    local needs_rollback=false
    
    if [[ "$SKIP_ROLLBACK" == "true" ]]; then
        echo -e "${BLUE}⏭️  Rollback saltato (--skip-rollback)${NC}"
    elif [[ "$FORCE_ROLLBACK" == "true" ]]; then
        echo -e "${YELLOW}🔄 Rollback forzato (--force-rollback)${NC}"
        needs_rollback=true
    else
        # Controllo automatico
        if check_wm_package_migrations; then
            echo -e "${YELLOW}⚠️  Migrazioni WM-Package già applicate - rollback necessario${NC}"
            needs_rollback=true
        else
            echo -e "${GREEN}✅ Nessuna migrazione WM-Package applicata - rollback non necessario${NC}"
        fi
    fi
    
    # Esegui rollback se necessario
    if [[ "$needs_rollback" == "true" ]]; then
        rollback_wm_package_migrations
    fi
    
    # Applica migrazioni
    apply_migrations
    
    # Verifica stato finale
    verify_final_status
    
    echo
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${GREEN}✅ Simulazione gestione migrazioni completata${NC}"
    else
        echo -e "${GREEN}✅ Gestione migrazioni completata con successo${NC}"
    fi
}

# Esegui solo se non sourcato
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi 