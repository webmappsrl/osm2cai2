#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🚀 Setup Link WMPackage to OSM2CAI2 - Ambiente di Sviluppo"
echo "=========================================================="
echo ""
echo "📁 Script per le app utilizzati:"
echo "   • setup-app26.sh (App 26 - customizzazioni complete, modalità locale)"
echo "   • setup-app20.sh (App 20 - import generico + verifiche)"
echo "   • setup-app58.sh (App 58 - import generico + customizzazioni)"
echo ""

# Script non richiede più parametri

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

# Funzione per gestire errori
handle_error() {
    print_error "ERRORE: Script interrotto alla riga $1"
    print_error "Ultimo comando: $BASH_COMMAND"
    print_error ""
    print_error "📞 Per assistenza controlla:"
    print_error "• Log container: docker-compose logs"
    print_error "• Stato container: docker ps -a"
    print_error "• Log Laravel: docker exec php81-osm2cai2 tail -f storage/logs/laravel.log"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

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

# Verifica prerequisiti
print_step "Verifica prerequisiti..."

# Controlla se siamo già nel container Docker
if [ -f "/var/www/html/osm2cai2/.env" ]; then
    print_warning "Esecuzione dal container Docker rilevata"
    print_step "Saltando controlli host e passando direttamente alla configurazione..."
    
    # Salta alla configurazione Scout/Elasticsearch
    cd /var/www/html/osm2cai2
    
    print_step "=== CONFIGURAZIONE SCOUT/ELASTICSEARCH ==="
    if ! ./scripts/wm-package-integration/scripts/04-enable-scout-automatic-indexing.sh; then
        print_error "Errore durante la configurazione di Scout/Elasticsearch!"
        exit 1
    fi
    
    # Fix alias se necessario
    print_step "=== FIX ALIAS ELASTICSEARCH ==="
    if ! ./scripts/wm-package-integration/scripts/05-fix-elasticsearch-alias.sh; then
        print_warning "Fix alias completato con avvertimenti, ma procediamo..."
    fi
    
    print_success "🎉 Setup completato dal container!"
    print_step "📊 Test API: curl \"http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1\""
    exit 0
fi

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

# FASE 1: SETUP AMBIENTE DOCKER
print_step "=== FASE 1: SETUP AMBIENTE DOCKER ==="

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

# Determina la directory root del progetto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../" && pwd)"

# Verifica file .env
print_step "Verifica file .env..."
if [ ! -f "$PROJECT_ROOT/.env" ]; then
    print_error "File .env non trovato nella root del progetto! Configurare .env prima di eseguire lo script."
    exit 1
else
    print_success "File .env trovato nella root del progetto"
fi

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

# Attesa che i servizi principali siano pronti
wait_for_postgres "postgres-osm2cai2" 90
wait_for_service "Elasticsearch" "http://localhost:9200/_cluster/health" 90
wait_for_service "MinIO" "http://localhost:9003/minio/health/live" 90

# Installazione Xdebug per ambiente di sviluppo
print_step "Installazione Xdebug nel container PHP..."
if [ -f "$PROJECT_ROOT/docker/configs/phpfpm/init-xdebug.sh" ]; then
    cd "$PROJECT_ROOT"
    if ./docker/configs/phpfpm/init-xdebug.sh; then
        print_success "Xdebug installato e configurato"
    else
        print_warning "Errore durante l'installazione di Xdebug (continuo comunque)"
    fi
    cd "$SCRIPT_DIR"
else
    print_warning "Script init-xdebug.sh non trovato, saltando installazione Xdebug"
fi

print_success "=== FASE 1 COMPLETATA: Ambiente Docker pronto ==="

# PULIZIA CODE LARAVEL E REDIS
print_step "Pulizia code Laravel e Redis..."

# Termina Horizon se attivo
print_step "Terminando Horizon se attivo..."
docker exec php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan horizon:terminate" 2>/dev/null || true
sleep 2

# Pulisci Redis
print_step "Pulizia Redis..."
docker exec redis-osm2cai2 redis-cli FLUSHALL 2>/dev/null || true
print_success "Redis pulito"

# Pulisci code Laravel
print_step "Pulizia code Laravel..."
docker exec php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan queue:clear" 2>/dev/null || true
docker exec php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan queue:flush" 2>/dev/null || true
print_success "Code Laravel pulite"

# Avvio servizi Laravel necessari per le fasi successive
print_step "Avvio servizi Laravel (serve + Horizon)..."
docker exec -d php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan serve --host 0.0.0.0"
wait_for_service "Laravel artisan serve" "http://localhost:8008" 30 || print_warning "artisan serve non ha risposto in tempo."

docker exec -d php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan horizon"
sleep 3 # Nota: Horizon non ha un endpoint di health check, un breve sleep è mantenuto.

# Verifica che i servizi siano attivi
print_step "Verifica servizi Laravel..."
if curl -f -s http://localhost:8008 &> /dev/null; then
    print_success "Laravel serve attivo"
else
    print_error "Laravel serve non è accessibile!"
    exit 1
fi

if docker exec php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan horizon:status" | grep -q "running"; then
    print_success "Horizon attivo"
else
    print_error "Horizon non è attivo!"
    exit 1
fi

print_success "Laravel serve e Horizon avviati e verificati (necessari per import)"

# FASE 2: RESET DATABASE
print_step "=== FASE 2: RESET DATABASE ==="

# Reset Database da Dump
print_step "Reset database da dump di backup..."
if ! ./scripts/06-reset-database-from-dump.sh --auto; then
    print_error "Reset database fallito! Interruzione setup."
    exit 1
fi
print_success "Database resettato da dump di backup"

# Applicazione Migrazioni
print_step "Applicazione migrazioni al database..."
if ! docker exec php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan migrate --force"; then
    print_error "Errore durante l'applicazione delle migrazioni! Interruzione setup."
    exit 1
fi
print_success "Migrazioni applicate al database"

print_success "=== FASE 2 COMPLETATA: Database resettato e migrazioni applicate ==="

# FASE 3: CONFIGURAZIONE SERVIZI
print_step "=== FASE 3: CONFIGURAZIONE SERVIZI ==="

# Setup bucket MinIO
print_step "Setup bucket MinIO..."
docker exec php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/setup-minio-bucket.sh"

print_success "=== FASE 3 COMPLETATA: Servizi configurati ==="



# FASE 5: SETUP ELASTICSEARCH
print_step "=== FASE 5: SETUP ELASTICSEARCH ==="

# Setup Elasticsearch
print_step "Setup Elasticsearch e indicizzazione..."

# Cancellazione indici esistenti per partire puliti
print_step "Pulizia indici Elasticsearch esistenti..."
if docker exec php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/07-delete-all-elasticsearch-indices.sh --force"; then
    print_success "Indici Elasticsearch puliti (o nessun indice trovato)"
else
    print_warning "Errore durante la pulizia degli indici Elasticsearch (continuo comunque)"
fi

# Abilita indicizzazione automatica Scout
if ! docker exec php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/04-enable-scout-automatic-indexing.sh"; then
    print_error "Errore durante la configurazione di Scout/Elasticsearch! Interruzione setup."
    exit 1
fi

# Pulisci cache configurazione
print_step "Pulizia cache configurazione..."
docker exec php81-osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan config:clear"
print_success "Cache configurazione pulita"

# Indicizzazione iniziale
print_step "Avvio indicizzazione iniziale (può richiedere diversi minuti)..."
if ! docker exec php81-osm2cai2 bash -c 'cd /var/www/html/osm2cai2 && php -d max_execution_time=3600 -d memory_limit=2G artisan scout:import-ectrack'; then
    print_error "Errore durante l'indicizzazione iniziale! Interruzione setup."
    exit 1
fi
print_success "Indicizzazione iniziale completata"

print_success "=== FASE 5 COMPLETATA: Elasticsearch configurato ==="

# FASE 6: IMPORT APP 26
print_step "=== FASE 6: IMPORT APP 26 ==="
print_step "🎯 App 26: Import + layer + associazione hiking routes + proprietà (modalità locale - media esclusi)"

if ! bash scripts/setup-app26.sh local; then
    print_error "Setup App 26 fallito! Interruzione setup."
    exit 1
fi
print_success "=== FASE 6 COMPLETATA: App 26 configurata con customizzazioni (modalità locale) ==="

# FASE 7: IMPORT APP 20
print_step "=== FASE 7: IMPORT APP 20 ==="
print_step "🎯 App 20: Import generico con verifiche"

if ! bash scripts/setup-app20.sh; then
    print_error "Setup App 20 fallito! Interruzione setup."
    exit 1
fi
print_success "=== FASE 7 COMPLETATA: App 20 configurata con verifiche ==="

# FASE 8: IMPORT APP 58
print_step "=== FASE 8: IMPORT APP 58 ==="
print_step "🎯 App 58: Import generico + customizzazioni specifiche"

if ! bash scripts/setup-app58.sh; then
    print_error "Setup App 58 fallito! Interruzione setup."
    exit 1
fi
print_success "=== FASE 8 COMPLETATA: App 58 configurata con customizzazioni ==="

# FASE 9: VERIFICA SERVIZI FINALI
print_step "=== FASE 9: VERIFICA SERVIZI FINALI ==="

if ! ./scripts/verify-final-services.sh; then
    print_error "Verifica servizi finali fallita!"
    exit 1
fi

print_success "=== FASE 9 COMPLETATA: Verifica servizi completata ==="

echo ""
print_success "🎉 SETUP AMBIENTE DI SVILUPPO COMPLETATO CON SUCCESSO!"
echo "=============================================================="
echo ""
print_step "📋 Riepilogo operazioni completate:"
print_step "   ✅ Ambiente Docker configurato e avviato"
print_step "   ✅ Database resettato e migrazioni applicate"
print_step "   ✅ Servizi (MinIO, Elasticsearch) configurati"
print_step "   ✅ Elasticsearch indicizzato"
print_step "   ✅ App 26 configurata con customizzazioni (modalità locale)"
print_step "   ✅ App 20 configurata con verifiche"
print_step "   ✅ App 58 configurata con customizzazioni"
print_step "   ✅ Verifica servizi finali completata"
echo ""
print_step "🌐 L'applicazione è accessibile su: http://127.0.0.1:8008"
print_step "📊 Horizon è attivo per la gestione delle code"
print_step "🔍 Elasticsearch è pronto per le ricerche"
echo ""
print_step "📁 Script utilizzati per le app:"
print_step "   • setup-app26.sh (App 26 - customizzazioni complete)"
print_step "   • setup-app20.sh (App 20 - import generico + verifiche)"
print_step "   • setup-app58.sh (App 58 - import generico + customizzazioni)"
echo "" 