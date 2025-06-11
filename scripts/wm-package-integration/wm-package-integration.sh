#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🚀 Setup Link WMPackage to OSM2CAI2 - Ambiente di Sviluppo"
echo "=========================================================="
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

# Funzione per gestire errori
handle_error() {
    print_error "ERRORE: Script interrotto alla riga $1"
    print_error "Ultimo comando: $BASH_COMMAND"
    print_error ""
    print_error "📞 Per assistenza controlla:"
    print_error "• Log container: docker-compose logs"
    print_error "• Stato container: docker ps -a"
    print_error "• Log Laravel: docker exec php81_osm2cai2 tail -f storage/logs/laravel.log"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

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
    "php81_osm2cai2"
    "postgres_osm2cai2" 
    "elasticsearch_osm2cai2"
    "minio_osm2cai2"
    "mailpit_osm2cai2"
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
sleep 15

# Torna alla directory degli script
cd "$SCRIPT_DIR"

# Attesa che PostgreSQL sia completamente pronto
print_step "Attesa che PostgreSQL sia completamente pronto..."
timeout=60
elapsed=0
while ! docker exec postgres_osm2cai2 pg_isready -h localhost -p 5432 &> /dev/null; do
    if [ $elapsed -ge $timeout ]; then
        print_error "Timeout: PostgreSQL non è diventato pronto in $timeout secondi"
        exit 1
    fi
    sleep 2
    elapsed=$((elapsed + 2))
    print_step "Attendo PostgreSQL... ($elapsed/$timeout secondi)"
done
print_success "PostgreSQL pronto e funzionante"

# Verifica servizi
print_step "Verifica finale servizi..."

# Elasticsearch
if curl -f -s http://localhost:9200/_cluster/health &> /dev/null; then
    print_success "Elasticsearch attivo"
else
    print_warning "Elasticsearch non ancora pronto (verrà configurato dopo)"
fi

# Attesa che MinIO sia completamente pronto
print_step "Attesa che MinIO sia completamente pronto..."
timeout=90
elapsed=0
while ! curl -f -s http://localhost:9003/minio/health/live &> /dev/null; do
    if [ $elapsed -ge $timeout ]; then
        print_warning "Timeout: MinIO non è diventato pronto in $timeout secondi (continuo comunque)"
        break
    fi
    sleep 3
    elapsed=$((elapsed + 3))
    print_step "Attendo MinIO... ($elapsed/$timeout secondi)"
done

if curl -f -s http://localhost:9003/minio/health/live &> /dev/null; then
    print_success "MinIO pronto e funzionante"
else
    print_warning "MinIO non ancora pronto (potrebbero esserci problemi nella FASE 3)"
fi

print_success "=== FASE 1 COMPLETATA: Ambiente Docker pronto ==="

# Avvio servizi Laravel necessari per le fasi successive
print_step "Avvio servizi Laravel (serve + Horizon)..."
docker exec -d php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan serve --host 0.0.0.0"
sleep 3
docker exec -d php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan horizon"
sleep 3
print_success "Laravel serve e Horizon avviati (necessari per import)"

# FASE 2: DATABASE E MIGRAZIONI
print_step "=== FASE 2: DATABASE E MIGRAZIONI ==="

# Gestione intelligente migrazioni (con rollback automatico se necessario)
print_step "Gestione intelligente migrazioni (controllo stato + rollback automatico)..."

# Usa lo script dedicato per gestire le migrazioni
if ! docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/08-manage-migrations.sh"; then
    print_error "Errore durante la gestione delle migrazioni! Interruzione setup."
    exit 1
fi
print_success "Migrazioni gestite con successo (con rollback automatico se necessario)"

# Import App da Geohub
print_step "Import App da Geohub (utilizzando script dedicato)..."
if ! ./scripts/01-import-app-from-geohub.sh 26; then
    print_error "Import App da Geohub fallito! Interruzione setup."
    exit 1
fi
print_success "Import App da Geohub completato con successo"

print_success "=== FASE 2 COMPLETATA: Database configurato ==="

# FASE 3: CONFIGURAZIONE SERVIZI
print_step "=== FASE 3: CONFIGURAZIONE SERVIZI ==="

# Setup bucket MinIO
print_step "Setup bucket MinIO..."
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/setup-minio-bucket.sh"

print_success "=== FASE 3 COMPLETATA: Servizi configurati ==="

# FASE 4: CONFIGURAZIONE APPS E LAYER
print_step "=== FASE 4: CONFIGURAZIONE APPS E LAYER ==="

# Verifica/Creazione App di default
print_step "Verifica App di default..."
APP_COUNT=$(docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::count();\"" 2>/dev/null || echo "0")

if [ "$APP_COUNT" -eq 0 ]; then
    print_step "Nessuna app rilevata, l'import dovrebbe essere già stato eseguito nella FASE 2..."
    print_warning "Se l'app non è stata importata, controlla Horizon: http://localhost:8008/horizon"
    
    # Assegna app_id alla prima app disponibile per tutte le hiking routes esistenti
    FIRST_APP_ID=$(docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::first()->id ?? 1;\"" 2>/dev/null || echo "1")
    print_step "Assegnazione app_id=$FIRST_APP_ID a tutte le hiking routes..."
    docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"
        \\\$count = DB::table('hiking_routes')->whereNull('app_id')->update(['app_id' => $FIRST_APP_ID]);
        echo 'Aggiornate ' . \\\$count . ' hiking routes con app_id=$FIRST_APP_ID';
    \""
    print_success "Hiking routes associate all'app di default"
else
    print_success "App esistenti rilevate ($APP_COUNT)"
    
    # Verifica se ci sono hiking routes senza app_id e assegnale
    ROUTES_WITHOUT_APP=$(docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo DB::table('hiking_routes')->whereNull('app_id')->count();\"" 2>/dev/null || echo "0")
    
    if [ "$ROUTES_WITHOUT_APP" -gt 0 ]; then
        FIRST_APP_ID=$(docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::first()->id ?? 1;\"" 2>/dev/null || echo "1")
        print_step "Assegnazione app_id=$FIRST_APP_ID a $ROUTES_WITHOUT_APP hiking routes senza app..."
        docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"
            \\\$count = DB::table('hiking_routes')->whereNull('app_id')->update(['app_id' => $FIRST_APP_ID]);
            echo 'Aggiornate ' . \\\$count . ' hiking routes con app_id=$FIRST_APP_ID';
        \""
        print_success "Hiking routes associate all'app di default"
    fi
fi

# Creazione layer di accatastamento
print_step "Creazione layer di accatastamento..."
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan osm2cai:create-accatastamento-layers"
print_success "Layer di accatastamento creati"

# Associazione hiking routes ai layer
print_step "Associazione hiking routes ai layer..."
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan osm2cai:associate-hiking-routes-to-layers"
print_success "Hiking routes associati ai layer"

print_success "=== FASE 4 COMPLETATA: Apps e Layer configurati ==="

# FASE 5: SETUP ELASTICSEARCH
print_step "=== FASE 5: SETUP ELASTICSEARCH ==="

# Setup Elasticsearch
print_step "Setup Elasticsearch e indicizzazione..."

# Aspetta che Elasticsearch sia completamente pronto
sleep 15

# Cancellazione indici esistenti per partire puliti
print_step "Pulizia indici Elasticsearch esistenti..."
if docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/07-delete-all-elasticsearch-indices.sh --force"; then
    print_success "Indici Elasticsearch puliti (o nessun indice trovato)"
else
    print_warning "Errore durante la pulizia degli indici Elasticsearch (continuo comunque)"
fi

# Abilita indicizzazione automatica Scout
if ! docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/04-enable-scout-automatic-indexing.sh"; then
    print_error "Errore durante la configurazione di Scout/Elasticsearch! Interruzione setup."
    exit 1
fi

# Pulisci cache configurazione
print_step "Pulizia cache configurazione..."
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan config:clear"
print_success "Cache configurazione pulita"

# Indicizzazione iniziale
print_step "Avvio indicizzazione iniziale (può richiedere diversi minuti)..."
if ! docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php -d max_execution_time=3600 -d memory_limit=2G artisan scout:import App\\\\Models\\\\HikingRoute"; then
    print_error "Errore durante l'indicizzazione iniziale! Interruzione setup."
    exit 1
fi
print_success "Indicizzazione iniziale completata"

# Fix completo Elasticsearch con configurazione single-node
print_step "Fix Elasticsearch e creazione alias ec_tracks per compatibilità API..."
if ! docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/05-fix-elasticsearch-alias.sh"; then
    print_error "Errore durante la configurazione dell'alias ec_tracks! Interruzione setup."
    exit 1
fi
print_success "Elasticsearch configurato per single-node e alias ec_tracks creato"

print_success "=== FASE 5 COMPLETATA: Elasticsearch configurato ==="

# FASE 6: VERIFICA SERVIZI FINALI
print_step "=== FASE 6: VERIFICA SERVIZI FINALI ==="

# Verifica che Laravel serve e Horizon siano ancora attivi
print_step "Verifica servizi Laravel..."
if pgrep -f "artisan serve" > /dev/null; then
    print_success "Laravel serve attivo"
else
    print_step "Riavvio Laravel serve..."
    docker exec -d php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan serve --host 0.0.0.0"
    sleep 3
fi

if docker exec php81_osm2cai2 php artisan horizon:status | grep -q "running"; then
    print_success "Horizon attivo"
else
    print_step "Riavvio Horizon..."
    docker exec -d php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan horizon"
    sleep 3
fi

# Test servizi
print_step "Test finale servizi..."

# Test Laravel
print_step "Verifica accesso all'applicazione Laravel..."
if curl -f -s http://localhost:8008 &> /dev/null; then
    print_success "Laravel attivo su http://localhost:8008"
else
    print_error "Laravel non è accessibile su http://localhost:8008!"
    print_error "Verifica che il server sia stato avviato correttamente."
    exit 1
fi

# Test API Elasticsearch
if curl -f -s "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1" &> /dev/null; then
    print_success "API Elasticsearch funzionante"
else
    print_warning "API Elasticsearch potrebbe richiedere configurazione aggiuntiva"
fi

print_success "=== FASE 6 COMPLETATA: Servizi avviati ==="

echo ""
echo "🎉 Setup Link WMPackage to OSM2CAI2 Completato!"
echo "======================================"
echo ""
echo "📋 Servizi Disponibili:"
echo "   • Applicazione: http://localhost:8008"
echo "   • Nova Admin: http://localhost:8008/nova"
echo "   • MinIO Console: http://localhost:9003 (minioadmin/minioadmin)"
echo "   • MailPit: http://localhost:8025"
echo "   • Elasticsearch: http://localhost:9200"
echo "   • PostgreSQL: localhost:5508"
echo ""
echo "🔧 Comandi Utili:"
echo "   • Accesso container PHP: docker exec -u 0 -it php81_osm2cai2 bash"
echo "   • Dashboard Horizon: http://localhost:8008/horizon"
echo "   • Riavvio Horizon: docker exec php81_osm2cai2 php artisan horizon:terminate"
echo "   • Status Horizon: docker exec php81_osm2cai2 php artisan horizon:status"
echo "   • Log Laravel: docker exec php81_osm2cai2 tail -f storage/logs/laravel.log"
echo "   • Test MinIO: ./scripts/test-minio-laravel.sh"
echo "   • Fix alias Elasticsearch: docker exec php81_osm2cai2 ./scripts/wm-package-integration/scripts/05-fix-elasticsearch-alias.sh"
echo ""
echo "🛑 Per fermare tutto:"
echo "   docker-compose down && docker-compose -f docker-compose.develop.yml down"
echo ""
print_success "Ambiente di sviluppo pronto per l'uso!" 