#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🔄 Sync da Produzione e Applicazione Integrazione WMPackage"
echo "=========================================================="
echo "📅 Avviato: $(date '+%Y-%m-%d %H:%M:%S')"
echo "🤖 Modalità: Automatica (Cronjob)"
echo ""
echo "📋 USAGE:"
echo "   $0                    # Importa tutte le app di default (26, 20, 58)"
echo "   $0 --help             # Mostra questo help"
echo "   $0 --apps 26 20       # Importa solo le app specificate"
echo "   $0 -a 26 20           # Forma abbreviata"
echo ""
echo "📁 App disponibili:"
echo "   • App 26: setup-app26.sh (customizzazioni complete)"
echo "   • App 20: setup-app20.sh (import generico + verifiche)"
echo "   • App 58: setup-app58.sh (import generico + customizzazioni)"
echo ""
echo "📝 Esempi:"
echo "   $0                    # Importa tutte le app"
echo "   $0 --apps 26          # Importa solo App 26"
echo "   $0 --apps 20 58       # Importa App 20 e 58"
echo ""

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzione per stampe colorate
print_step() {
    echo -e "${BLUE}➜${NC} $1"
}

print_success() {
    echo -e "${GREEN}✅${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠️${NC} $1"
}

print_error() {
    echo -e "${RED}❌${NC} $1"
}

# Configurazione app disponibili (compatibile con bash 3.2)
APP_IDS=("26" "20" "58")
APP_SCRIPTS=("setup-app26.sh" "setup-app20.sh" "setup-app58.sh")

# App di default da importare (tutte)
DEFAULT_APPS=("26" "20" "58")

# Funzione per ottenere la configurazione di un'app
get_app_config() {
    local app_id="$1"
    for i in "${!APP_IDS[@]}"; do
        if [[ "${APP_IDS[$i]}" == "$app_id" ]]; then
            echo "${APP_SCRIPTS[$i]}"
            return 0
        fi
    done
    return 1
}

# Funzione per verificare se un'app esiste
app_exists() {
    local app_id="$1"
    for id in "${APP_IDS[@]}"; do
        if [[ "$id" == "$app_id" ]]; then
            return 0
        fi
    done
    return 1
}

# Funzione per mostrare l'help
show_help() {
    echo "🔄 Sync da Produzione e Applicazione Integrazione WMPackage"
    echo "=========================================================="
    echo ""
    echo "📋 USAGE:"
    echo "   $0                    # Importa tutte le app di default (26, 20, 58)"
    echo "   $0 --help             # Mostra questo help"
    echo "   $0 --apps 26 20       # Importa solo le app specificate"
    echo "   $0 -a 26 20           # Forma abbreviata"
    echo "   $0 --sync             # Sincronizza dump da produzione prima del setup"
    echo "   $0 -s                 # Forma abbreviata per sync"
    echo "   $0 --sync --apps 26   # Sync + import solo App 26"
    echo ""
    echo "📁 App disponibili:"
    echo "   • App 26: setup-app26.sh (customizzazioni complete)"
    echo "   • App 20: setup-app20.sh (import generico + verifiche)"
    echo "   • App 58: setup-app58.sh (import generico + customizzazioni)"
    echo ""
    echo "📝 Esempi:"
    echo "   $0                    # Importa tutte le app"
    echo "   $0 --apps 26          # Importa solo App 26"
    echo "   $0 --apps 20 58       # Importa App 20 e 58"
    echo "   $0 --sync             # Sync da produzione + importa tutte le app"
    echo "   $0 --sync --apps 26   # Sync da produzione + importa solo App 26"
    echo ""
}

# Funzione per validare gli ID delle app
validate_app_ids() {
    local app_ids=("$@")
    
    for app_id in "${app_ids[@]}"; do
        if ! app_exists "$app_id"; then
            print_error "ID app non valido: $app_id"
            echo ""
            echo "App disponibili:"
            for id in "${APP_IDS[@]}"; do
                local config=$(get_app_config "$id")
                echo "   • App $id: $config"
            done
            exit 1
        fi
    done
}

# Funzione per importare una singola app
import_app() {
    local app_id="$1"
    local script_name=$(get_app_config "$app_id")
    
    print_step "=== FASE: IMPORT APP $app_id ==="
    print_step "🎯 App $app_id: $script_name"
    
    if ! bash "$SCRIPT_DIR/scripts/$script_name"; then
        print_error "Setup App $app_id fallito! Interruzione setup."
        exit 1
    fi
    print_success "=== FASE COMPLETATA: App $app_id configurata ==="
}

# Funzione per gestire errori
handle_error() {
    print_error "ERRORE: Script interrotto alla riga $1"
    print_error "Ultimo comando: $BASH_COMMAND"
    print_error ""
    print_error "📞 Per assistenza controlla:"
    print_error "• Connessione SSH a osm2caiProd"
    print_error "• File dump in storage/app/backups/"
    print_error "• Stato container: docker ps -a"
    print_error "• Verifica: ssh osm2caiProd 'ls -la html/osm2cai2/storage/backups/'"
    exit 1
}

# Parsing dei parametri
APPS_TO_IMPORT=()
SYNC_FROM_PROD=false  # Default: no sync, usa dump locale esistente

while [[ $# -gt 0 ]]; do
    case $1 in
        --help|-h)
            show_help
            exit 0
            ;;
        --apps|-a)
            shift
            while [[ $# -gt 0 && ! $1 =~ ^-- ]]; do
                APPS_TO_IMPORT+=("$1")
                shift
            done
            ;;
        --sync|-s)
            SYNC_FROM_PROD=true
            shift
            ;;
        *)
            print_error "Parametro non riconosciuto: $1"
            echo ""
            show_help
            exit 1
            ;;
    esac
done

# Se non sono state specificate app, usa quelle di default
if [ ${#APPS_TO_IMPORT[@]} -eq 0 ]; then
    APPS_TO_IMPORT=("${DEFAULT_APPS[@]}")
    print_step "Nessuna app specificata, importando tutte le app di default: ${APPS_TO_IMPORT[*]}"
else
    # Valida gli ID delle app forniti
    validate_app_ids "${APPS_TO_IMPORT[@]}"
    print_step "App da importare: ${APPS_TO_IMPORT[*]}"
fi

echo ""
echo "📁 Script per le app che verranno utilizzati:"
for app_id in "${APPS_TO_IMPORT[@]}"; do
    config=$(get_app_config "$app_id")
    echo "   • $config (App $app_id)"
done
echo ""

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Determina la directory root del progetto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../" && pwd)"

# Verifica prerequisiti
print_step "Verifica prerequisiti..."

# Controlla se Docker è installato
if ! command -v docker &> /dev/null; then
    print_error "Docker non è installato!"
    exit 1
fi

# Controlla se Docker Compose è installato
if ! command -v docker-compose &> /dev/null; then
    print_error "Docker Compose non è installato!"
    exit 1
fi


print_success "Prerequisiti verificati"

# PULIZIA SELETTIVA AMBIENTE OSM2CAI2
print_step "Pulizia selettiva ambiente OSM2CAI2..."

# Ferma solo i container di OSM2CAI2 tramite docker-compose
print_step "Fermando container OSM2CAI2..."
docker-compose down -v --remove-orphans 2>/dev/null || true
docker-compose -f docker-compose.develop.yml down -v --remove-orphans 2>/dev/null || true

# Lista dei container specifici OSM2CAI2 da verificare/pulire
OSM2CAI_CONTAINERS=(
    "php81-osm2cai2"
    "postgres-osm2cai2"
    "elasticsearch-osm2cai2"
    "minio-osm2cai2"
    "mailpit-osm2cai2"
)

# Verifica e ferma eventuali container OSM2CAI2 rimasti attivi
for container in "${OSM2CAI_CONTAINERS[@]}"; do
    if docker ps -q -f name="^${container}$" | grep -q .; then
        print_step "Fermando container rimasto attivo: $container"
        docker stop "$container" 2>/dev/null || true
        docker rm "$container" 2>/dev/null || true
    fi
done

# Rimuove solo i volumi specifici di OSM2CAI2 (se esistono e non sono utilizzati)
OSM2CAI_VOLUMES=(
    "osm2cai2_postgres_data"
    "osm2cai2_elasticsearch_data"
    "osm2cai2_minio_data"
)

for volume in "${OSM2CAI_VOLUMES[@]}"; do
    if docker volume ls -q | grep -q "^${volume}$"; then
        if ! docker ps -a --filter "volume=${volume}" --format "table {{.Names}}" | grep -q -v "NAMES"; then
            print_step "Rimuovendo volume non utilizzato: $volume"
            docker volume rm "$volume" 2>/dev/null || true
        fi
    fi
done

print_success "Pulizia selettiva completata (solo container OSM2CAI2)"

# Creazione directory per volumi Docker
print_step "Creazione directory per volumi Docker..."
mkdir -p "$PROJECT_ROOT/docker/volumes/minio/data"
mkdir -p "$PROJECT_ROOT/docker/volumes/postgresql/data"
mkdir -p "$PROJECT_ROOT/docker/volumes/elasticsearch/data"
print_success "Directory volumi create"

# Avvio container
print_step "Avvio tutti i container (base + sviluppo)..."
cd "$PROJECT_ROOT"
docker-compose -f docker-compose.yml -f docker-compose.develop.yml up -d

# Torna alla directory degli script
cd "$SCRIPT_DIR"

# Funzioni di attesa per i servizi, per eliminare gli sleep "magici"
wait_for_service() {
    local service_name="$1"
    local health_url="$2"
    local timeout="$3"

    print_step "Attesa che $service_name sia completamente pronto..."
    local elapsed=0
    while ! curl -f -s "$health_url" &> /dev/null; do
        if [ $elapsed -ge $timeout ]; then
            print_warning "Timeout: $service_name non è diventato pronto in $timeout secondi (continuo comunque)"
            return 1
        fi
        sleep 3
        elapsed=$((elapsed + 3))
        print_step "Attendo $service_name... ($elapsed/$timeout secondi)"
    done
    print_success "$service_name pronto e funzionante"
    return 0
}

wait_for_postgres() {
    local container_name="$1"
    local timeout="$2"

    print_step "Attesa che PostgreSQL sia completamente pronto..."
    local elapsed=0
    while ! docker exec "$container_name" pg_isready -h localhost -p 5432 &> /dev/null; do
        if [ $elapsed -ge $timeout ]; then
            print_error "Timeout: PostgreSQL non è diventato pronto in $timeout secondi"
            exit 1
        fi
        sleep 2
        elapsed=$((elapsed + 2))
        print_step "Attendo PostgreSQL... ($elapsed/$timeout secondi)"
    done
    print_success "PostgreSQL pronto e funzionante"
}

# Attesa che i servizi principali siano pronti
wait_for_postgres "postgres-osm2cai2" 90
wait_for_service "Elasticsearch" "http://localhost:9200/_cluster/health" 90
wait_for_service "MinIO" "http://localhost:9003/minio/health/live" 90

print_success "Tutti i servizi sono pronti e funzionanti"

# Log automatico per cronjob
echo ""
if [ "$SYNC_FROM_PROD" = true ]; then
    print_warning "⚠️  ATTENZIONE: Questa operazione:"
    print_warning "   • Scaricherà il dump da produzione (~600MB)"
    print_warning "   • Cancellerà TUTTI i dati nel database locale"
    print_warning "   • Applicherà l'integrazione WMPackage di produzione"
else
    print_warning "⚠️  ATTENZIONE: Questa operazione:"
    print_warning "   • Utilizzerà il dump locale esistente (se disponibile)"
    print_warning "   • Cancellerà TUTTI i dati nel database locale"
    print_warning "   • Applicherà l'integrazione WMPackage"
fi
echo ""
print_step "🤖 Modalità automatica (cronjob) - procedo senza conferma utente"
echo ""

# FASE 1: Download Dump da Produzione (se richiesto)
if [ "$SYNC_FROM_PROD" = true ]; then
    print_step "=== FASE 1: DOWNLOAD DUMP DA PRODUZIONE ==="
    
    if ! bash "$SCRIPT_DIR/scripts/sync-dump-from-production.sh"; then
        print_error "Errore durante il sync del dump da produzione! Interruzione setup."
        exit 1
    fi
    
    print_success "=== FASE 1 COMPLETATA ==="
else
    print_step "=== FASE 1: SYNC DA PRODUZIONE SALTATO ==="
    print_step "Utilizzando dump locale esistente (se disponibile)"
    print_success "=== FASE 1 COMPLETATA ==="
fi

# FASE 2: Reset Database dal Dump
print_step "=== FASE 2: RESET DATABASE DAL DUMP ==="

print_step "Eseguendo script di reset database (modalità automatica)..."
if bash "$SCRIPT_DIR/scripts/06-reset-database-from-dump.sh" --auto; then
    print_success "Reset database completato con successo"
else
    print_error "Errore durante il reset del database"
    exit 1
fi

print_success "=== FASE 2 COMPLETATA ==="

# Carica le variabili dal file .env per le fasi successive
if [ -f "$PROJECT_ROOT/.env" ]; then
    set -o allexport
    source "$PROJECT_ROOT/.env"
    set +o allexport
    PHP_CONTAINER="php81-${APP_NAME}"
    POSTGRES_CONTAINER="postgres-${APP_NAME}"
else
    print_error "File .env non trovato per le fasi successive"
    exit 1
fi

# FASE 2.5: Esecuzione Migrazioni
print_step "=== FASE 2.5: ESECUZIONE MIGRAZIONI ==="

print_step "Eseguendo migrazioni Laravel..."
if docker exec "$PHP_CONTAINER" php artisan migrate; then
    print_success "Migrazioni completate con successo"
else
    print_error "Errore durante l'esecuzione delle migrazioni"
    exit 1
fi

print_success "=== FASE 2.5 COMPLETATA ==="

# FASE 2.6: INIZIALIZZAZIONE APP MODELS
print_step "=== FASE 2.6: INIZIALIZZAZIONE APP MODELS ==="

# Inizializza i modelli app per tutte le app (26, 20, 58) senza dipendenze
print_step "Inizializzazione modelli app (senza dipendenze)..."
if ! bash "$SCRIPT_DIR/scripts/init-apps.sh"; then
    print_error "Errore durante l'inizializzazione dei modelli app! Interruzione setup."
    exit 1
fi
print_success "Modelli app inizializzati (ID creati per tutte le app)"

print_success "=== FASE 2.6 COMPLETATA: Modelli app inizializzati ==="

# Migrazione UGC Media to Media (dopo init app)
print_step "Migrazione UGC Media to Media..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan osm2cai:migrate-ugc-media-to-media --force"; then
    print_error "Errore durante la migrazione UGC Media to Media! Interruzione setup."
    exit 1
fi
print_success "UGC Media migrato al sistema Media"

# FASE 2.7: CONFIGURAZIONE SERVIZI
print_step "=== FASE 2.7: CONFIGURAZIONE SERVIZI ==="

# Setup bucket MinIO
print_step "Setup bucket MinIO..."
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/setup-minio-bucket.sh"

print_success "=== FASE 2.7 COMPLETATA: Servizi configurati ==="

# FASE 3: FIX CAMPI TRANSLATABLE
print_step "=== FASE 3: FIX CAMPI TRANSLATABLE NULL ==="

# Fix dei campi translatable null prima degli import delle app
print_step "Fix dei campi translatable null nei modelli..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/fix-translatable-fields.sh"; then
    print_error "Errore durante il fix dei campi translatable! Interruzione setup."
    exit 1
fi
print_success "Campi translatable fixati"

print_success "=== FASE 3 COMPLETATA: Campi translatable fixati ==="

# FASE 4: IMPORT APP SPECIFICATE
print_step "=== FASE 4: IMPORT APP SPECIFICATE ==="

for app_id in "${APPS_TO_IMPORT[@]}"; do
    import_app "$app_id"
done

print_success "=== FASE 4 COMPLETATA: Tutte le app specificate importate ==="

# FASE 4.5: PROCESSAMENTO ICONE AWS E GEOJSON POI
print_step "=== FASE 4.5: PROCESSAMENTO ICONE AWS E GEOJSON POI ==="

# Esegue lo script dedicato per il processamento di tutte le app
print_step "Esecuzione script processamento icone AWS e geojson POI..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/process-all-apps-icons-and-geojson.sh"; then
    print_error "Errore durante il processamento delle icone AWS e geojson POI! Interruzione setup."
    exit 1
fi
print_success "Processamento icone AWS e geojson POI completato"

print_success "=== FASE 4.5 COMPLETATA: Processamento icone AWS e geojson POI completato ==="

# FASE 5: Verifica Finale
print_step "=== FASE 5: VERIFICA FINALE ==="

# Verifica che i servizi siano attivi
print_step "Verifica servizi attivi..."
if docker ps | grep -q "$PHP_CONTAINER" && docker ps | grep -q "$POSTGRES_CONTAINER"; then
    print_success "Container attivi"
else
    print_warning "Alcuni container potrebbero non essere attivi"
fi

# Test connessione database
print_step "Test connessione database finale..."
if docker exec "$POSTGRES_CONTAINER" psql -U osm2cai2 -d osm2cai2 -c "SELECT 1;" &> /dev/null; then
    print_success "Database funzionante"
else
    print_error "Problema connessione database"
    exit 1
fi

print_success "=== FASE 5 COMPLETATA ==="

echo ""
print_success "🎉 SYNC DA PRODUZIONE E INTEGRAZIONE COMPLETATA CON SUCCESSO!"
echo "📅 Completato: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
print_step "📋 Riepilogo operazioni:"
if [ "$SYNC_FROM_PROD" = true ]; then
    print_step "   ✅ Dump scaricato da osm2caiProd"
else
    print_step "   ✅ Dump locale utilizzato (sync saltato)"
fi
print_step "   ✅ Database resettato dal dump"
print_step "   ✅ Migrazioni applicate"
print_step "   ✅ Modelli app inizializzati (ID creati per tutte le app)"
print_step "   ✅ UGC Media migrato al sistema Media"
print_step "   ✅ Servizi (MinIO) configurati"
print_step "   ✅ Campi translatable fixati"
for app_id in "${APPS_TO_IMPORT[@]}"; do
    print_step "   ✅ App $app_id configurata"
done
print_step "   ✅ Icone AWS e file geojson POI generati per tutte le app"
print_step "   ✅ Verifica finale completata"
echo ""
print_step "📁 Script utilizzati per le app:"
for app_id in "${APPS_TO_IMPORT[@]}"; do
    config=$(get_app_config "$app_id")
    print_step "   • $config (App $app_id)"
done
echo ""
print_step "🌐 L'applicazione dovrebbe essere accessibile su: http://127.0.0.1:8008"
print_step "📊 Horizon dovrebbe essere attivo per la gestione delle code"
echo "" 