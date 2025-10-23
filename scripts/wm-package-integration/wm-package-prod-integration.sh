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
if ! docker compose version &> /dev/null; then
    print_error "Docker Compose non è installato!"
    exit 1
fi

# Inizializza e aggiorna submodules Git
print_step "Inizializzazione e aggiornamento submodules Git..."
if ! git submodule update --init --recursive; then
    print_error "Errore durante l'inizializzazione dei submodules!"
    exit 1
fi
print_success "Submodules Git inizializzati e aggiornati"

print_success "Prerequisiti verificati"

# FASE 0: AGGIORNAMENTO DOCKER-COMPOSE.YML
print_step "=== FASE 0: AGGIORNAMENTO DOCKER-COMPOSE.YML ==="

print_step "Aggiornamento file docker-compose.yml con configurazione WMPackage..."

# Backup del file docker-compose.yml esistente
if [ -f "$PROJECT_ROOT/docker-compose.yml" ]; then
    cp "$PROJECT_ROOT/docker-compose.yml" "$PROJECT_ROOT/docker-compose.yml.backup"
    print_step "   ✅ Backup creato: docker-compose.yml.backup"
fi

# Crea il nuovo file docker-compose.yml
cat > "$PROJECT_ROOT/docker-compose.yml" << 'EOF'
version: "3.8"
services:
  phpfpm:
    extra_hosts:
        - host.docker.internal:host-gateway
    # user: root
    build: ./docker/configs/phpfpm
    restart: always
    container_name: "php81-${APP_NAME}"
    image: wm-phpfpm:8.4-fpm
    ports:
      - ${DOCKER_PHP_PORT}:9000
      - ${DOCKER_SERVE_PORT}:8000
    volumes:
      - ".:/var/www/html/${DOCKER_PROJECT_DIR_NAME}"
    working_dir: '/var/www/html/${DOCKER_PROJECT_DIR_NAME}'
    depends_on:
      - db
      - redis
    networks:
      - laravel
  db:
    image: postgis/postgis:16-3.4
    container_name: "postgres-${APP_NAME}"
    restart: always
    environment:
      POSTGRES_PASSWORD: ${DB_PASSWORD:?err}
      POSTGRES_USER_PASSWORD: ${DB_PASSWORD:?err}
      POSTGRES_USER: ${DB_USERNAME:?err}
      POSTGRES_DB: ${DB_DATABASE:?err}
    volumes:
      - "./docker/volumes/postgresql/data:/var/lib/postgresql/data"
    ports:
      - ${DOCKER_PSQL_PORT}:5432
    networks:
      - laravel
  redis:
    image: redis:latest
    container_name: "redis-${APP_NAME}"
    restart: always
    ports:
      - 6379:6379
    networks:
      - laravel
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.17.1
    container_name: "elasticsearch-${APP_NAME}"
    restart: always
    environment:
      - node.name=elasticsearch
      - discovery.type=single-node
      - bootstrap.memory_lock=true
      - xpack.security.enabled=false
      - xpack.security.http.ssl.enabled=false
      - ES_JAVA_OPTS=-Xms512m -Xmx512m
    ulimits:
      memlock:
        soft: -1
        hard: -1
    ports:
      - "9200:9200"
      - "9300:9300"
    volumes:
      - "./docker/volumes/elasticsearch/data:/usr/share/elasticsearch/data"
    networks:
      - laravel
networks:
  laravel:
    driver: bridge
EOF

print_success "File docker-compose.yml aggiornato con successo"

# Avvio dei container Docker
print_step "Avvio container Docker..."
if docker compose up -d; then
    print_success "Container Docker avviati con successo"
else
    print_error "Errore durante l'avvio dei container Docker"
    exit 1
fi

# Attesa che i container siano pronti
print_step "Attesa che i container siano pronti..."
sleep 5

print_success "=== FASE 0 COMPLETATA: Docker-compose.yml aggiornato e container avviati ==="

# Reinstalla dipendenze Composer per aggiornare autoloader
print_step "Reinstallazione dipendenze Composer per aggiornare autoloader..."
if ! docker exec "php81-${APP_NAME}" composer install --no-scripts; then
    print_error "Errore durante la reinstallazione delle dipendenze Composer!"
    exit 1
fi
print_success "Dipendenze Composer reinstallate"

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

# FASE 2.4: AGGIORNAMENTO FILE .ENV
print_step "=== FASE 2.4: AGGIORNAMENTO FILE .ENV ==="

print_step "Eseguendo script di aggiornamento variabili d'ambiente..."
if bash "$SCRIPT_DIR/scripts/update-env-variables.sh"; then
    print_success "Variabili d'ambiente aggiornate con successo"
else
    print_error "Errore durante l'aggiornamento delle variabili d'ambiente"
    exit 1
fi

print_success "=== FASE 2.4 COMPLETATA: File .env aggiornato ==="

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

# FASE 4.6: INIZIALIZZAZIONE DATA CHAIN HIKING ROUTES
print_step "=== FASE 4.6: INIZIALIZZAZIONE DATA CHAIN HIKING ROUTES ==="

print_step "🚀 Avvio inizializzazione data chain per tutti gli hiking routes"
print_step "Questo processo lancerà job asincroni per aggiornare: OSM data, DEM, calcoli geometrici, AWS, etc."
echo ""

if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/init-all-tracks-datachain.sh"; then
    print_error "Errore durante l'inizializzazione data chain degli hiking routes!"
    print_warning "I job potrebbero essere comunque in esecuzione su Horizon"
    # Non interrompiamo lo script per questo errore
fi

print_success "=== FASE 4.6 COMPLETATA: Data chain hiking routes inizializzata ==="

# FASE 4.7: GENERAZIONE PBF OTTIMIZZATI
print_step "=== FASE 4.7: GENERAZIONE PBF OTTIMIZZATI ==="

print_step "🚀 Avvio generazione PBF ottimizzati per tutte le app"
print_step "Questo processo genera i file PBF con clustering geografico e li carica su AWS"
print_warning "⚠️  Ogni app può richiedere diversi minuti per la generazione PBF"
echo ""

if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/generate-pbf-for-apps.sh"; then
    print_error "Errore durante la generazione PBF per alcune app!"
    print_warning "Alcune app potrebbero non avere i file PBF aggiornati"
    # Non interrompiamo lo script per questo errore
fi

print_success "=== FASE 4.7 COMPLETATA: Generazione PBF completata ==="

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
print_step "   ✅ Docker-compose.yml aggiornato e container avviati"
print_step "   ✅ Database resettato dal dump"
print_step "   ✅ File .env aggiornato con variabili WMPackage"
print_step "   ✅ Migrazioni applicate"
print_step "   ✅ Modelli app inizializzati (ID creati per tutte le app)"
print_step "   ✅ UGC Media migrato al sistema Media"
print_step "   ✅ Campi translatable fixati"
for app_id in "${APPS_TO_IMPORT[@]}"; do
    print_step "   ✅ App $app_id configurata"
done
print_step "   ✅ Icone AWS e file geojson POI generati per tutte le app"
print_step "   ✅ Data chain hiking routes inizializzata (job in esecuzione su Horizon)"
print_step "   ✅ File PBF ottimizzati generati e caricati su AWS"
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