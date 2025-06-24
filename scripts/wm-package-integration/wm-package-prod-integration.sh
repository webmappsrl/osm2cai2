#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🚀 Setup Link WMPackage to OSM2CAI2 - Ambiente di Produzione"
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
    print_error "• Log Laravel: docker exec ${PHP_CONTAINER} tail -f storage/logs/laravel.log"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Funzioni di attesa per i servizi
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
    while ! docker exec "$container_name" pg_isready -h db -p 5432 &> /dev/null; do
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

# Determina la directory root del progetto e ci si sposta
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../" && pwd)"
cd "$PROJECT_ROOT"

# Carica le variabili dal file .env se esiste
if [ -f .env ]; then
    print_step "Caricamento delle variabili dal file .env..."
    # Esporta le variabili per renderle disponibili allo script
    set -o allexport
    source .env
    set +o allexport
    print_success "Variabili d'ambiente caricate."
else
    print_error "File .env non trovato. Impossibile continuare senza configurazione."
    exit 1
fi

# Definisci i nomi dei container utilizzando le variabili d'ambiente
if [ -z "$APP_NAME" ]; then
    print_error "La variabile APP_NAME non è definita nel file .env."
    exit 1
fi
PHP_CONTAINER="php81_${APP_NAME}"
POSTGRES_CONTAINER="postgres_${APP_NAME}"

print_step "Utilizzo dei nomi container: ${PHP_CONTAINER}, ${POSTGRES_CONTAINER}"

print_step "=== FASE 0: VERIFICA PREREQUISITI HOST ==="

# Controlla se Docker è installato
if ! command -v docker &> /dev/null; then
    print_error "Docker non è installato! Eseguire lo script dal sistema host, non da un container."
    exit 1
fi

# Rileva il comando Docker Compose corretto (V1 o V2)
print_step "Rilevamento del comando Docker Compose..."
if command -v docker-compose &> /dev/null; then
    COMPOSE_CMD="docker-compose"
    print_success "Trovato Docker Compose V1 ('docker-compose')"
elif docker compose version &> /dev/null; then
    COMPOSE_CMD="docker compose"
    print_success "Trovato Docker Compose V2 ('docker compose')"
else
    print_error "Docker Compose non trovato. Né 'docker-compose' né 'docker compose' sono disponibili."
    exit 1
fi

# Verifica file .env
print_step "Verifica file .env..."
if [ ! -f "$PROJECT_ROOT/.env" ]; then
    print_error "File .env non trovato nella root del progetto! Configurare .env prima di eseguire lo script."
    exit 1
else
    print_success "File .env trovato nella root del progetto"
fi

# Verifica file docker-compose.yml e crealo se non esiste
print_step "Verifica file docker-compose.yml..."
if [ ! -f "$PROJECT_ROOT/docker-compose.yml" ]; then
    print_warning "File docker-compose.yml non trovato. Lo copio da docker-compose.yml.example."
    if [ -f "$PROJECT_ROOT/docker-compose.yml.example" ]; then
        cp "$PROJECT_ROOT/docker-compose.yml.example" "$PROJECT_ROOT/docker-compose.yml"
        print_success "File docker-compose.yml creato con successo."
    else
        print_error "File docker-compose.yml.example non trovato! Impossibile creare docker-compose.yml."
        exit 1
    fi
else
    print_success "File docker-compose.yml trovato."
fi

print_success "Prerequisiti verificati"

# FASE 0.5: DOWNLOAD DATABASE BACKUP (PRIMA DI TOCCARE I CONTAINER)
print_step "=== FASE 0.5: DOWNLOAD ULTIMO BACKUP DATABASE (se l'ambiente esistente è attivo) ==="
# Controlla se il container PHP è in esecuzione
if ${COMPOSE_CMD} ps -q phpfpm | grep -q .; then
    print_step "Container 'phpfpm' trovato. Eseguo il download dell'ultimo backup del database... (potrebbe richiedere tempo)"
    if docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan wm:download-db-backup --latest"; then
        print_success "Backup del database scaricato con successo. Si troverà in storage/backups/last_dump.sql.gz"
    else
        print_warning "Tentativo di download del backup fallito. Questo potrebbe essere normale se l'ambiente non è completamente configurato. Continuo..."
    fi
else
    print_warning "Container 'phpfpm' non trovato. Salto il download preliminare del backup."
fi

# FASE 1: SETUP AMBIENTE DOCKER
print_step "=== FASE 1: SETUP AMBIENTE DOCKER ==="

# Ricrea i container di produzione
print_step "Fermo e rimuovo i container di produzione esistenti..."
${COMPOSE_CMD} -f docker-compose.yml down -v --remove-orphans 2>/dev/null || true

print_step "Avvio container di produzione (solo docker-compose.yml)..."
${COMPOSE_CMD} -f docker-compose.yml up -d

# Torna alla directory degli script
cd "$SCRIPT_DIR"

# Attesa che i servizi principali siano pronti
wait_for_postgres "$POSTGRES_CONTAINER" 90
wait_for_service "Elasticsearch" "http://localhost:9200/_cluster/health" 90

# Installazione dipendenze Composer
print_step "Installazione dipendenze Composer per la produzione..."
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && composer install --no-dev --optimize-autoloader"
print_success "Dipendenze Composer installate"

# Ottimizzazione Laravel per la produzione
print_step "Ottimizzazione cache Laravel per la produzione..."
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan config:cache"
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan route:cache"
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan view:cache"
print_success "Cache di configurazione, rotte e viste generate"

# Avvio Horizon (gestito da Supervisor in produzione)
print_step "Avvio Horizon..."
if docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan horizon:status | grep -q 'Horizon is running'"; then
    print_success "Horizon è già in esecuzione (gestione affidata a Supervisor)"
else
    docker exec -d "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan horizon"
    sleep 3
    print_success "Comando di avvio Horizon inviato"
fi

print_success "=== FASE 1 COMPLETATA: Ambiente Docker e Laravel pronti per la produzione ==="

# FASE 2: DATABASE E MIGRAZIONI
print_step "=== FASE 2: DATABASE, MIGRAZIONI E SEED INIZIALE ==="

# Applicazione Migrazioni
print_step "Applicazione migrazioni al database..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan migrate --force"; then
    print_error "Errore durante l'applicazione delle migrazioni! Interruzione setup."
    exit 1
fi
print_success "Migrazioni applicate al database"

# Import App da Geohub
print_step "Import App da Geohub (seeding iniziale)..."
print_warning "I job di importazione verranno inviati alla coda e processati in background da Horizon."
for APP_ID in 26 20 58; do
    print_step "Import App da Geohub con ID $APP_ID..."
    if ! ./scripts/01-import-app-from-geohub.sh $APP_ID 'SI'; then
        print_error "Import App da Geohub con ID $APP_ID fallito! Interruzione setup."
        exit 1
    fi
    print_success "Import App da Geohub con ID $APP_ID completato con successo"
done

print_success "Tutti i job di importazione sono stati inviati alla coda."
print_step "Attendi qualche minuto e controlla Horizon per vedere il progresso"
sleep 10 # Breve attesa per dare tempo ai job di essere inviati

print_success "=== FASE 2 COMPLETATA: Database configurato e popolato ==="

# FASE 3: CONFIGURAZIONE APPS E LAYER
print_step "=== FASE 3: CONFIGURAZIONE APPS E LAYER ==="

# Verifica/Creazione App di default e associazione hiking routes
print_step "Verifica App di default e associazione hiking routes..."
ROUTES_WITHOUT_APP=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo DB::table('hiking_routes')->whereNull('app_id')->count();\"" 2>/dev/null || echo "0")
if [ "$ROUTES_WITHOUT_APP" -gt 0 ]; then
    FIRST_APP_ID=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::first()->id ?? 1;\"" 2>/dev/null || echo "1")
    print_step "Assegnazione app_id=$FIRST_APP_ID a $ROUTES_WITHOUT_APP hiking routes senza app..."
    docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"
        \\\$count = DB::table('hiking_routes')->whereNull('app_id')->update(['app_id' => $FIRST_APP_ID]);
        echo 'Aggiornate ' . \\\$count . ' hiking routes con app_id=$FIRST_APP_ID';
    \""
    print_success "Hiking routes associate all'app di default"
else
    print_success "Tutte le hiking routes hanno già un'app associata."
fi

# Creazione layer di accatastamento
print_step "Creazione layer di accatastamento..."
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan osm2cai:create-accatastamento-layers"
print_success "Layer di accatastamento creati"

# Associazione hiking routes ai layer
print_step "Associazione hiking routes ai layer..."
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan osm2cai:associate-hiking-routes-to-layers"
print_success "Hiking routes associati ai layer"

# Popolamento proprietà e tassonomie per i percorsi
print_step "Popolamento proprietà e tassonomie per i percorsi..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/10-hiking-routes-properties-and-taxonomy.sh"; then
    print_error "Errore durante il popolamento delle proprietà e tassonomie dei percorsi! Interruzione setup."
    exit 1
fi
print_success "Proprietà e tassonomie dei percorsi popolate"

# Migrazione media per Hiking Routes
print_step "Migrazione media per Hiking Routes..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/09-migrate-hiking-route-media.sh full"; then
    print_error "Errore durante la migrazione dei media! Interruzione setup."
    exit 1
fi
print_success "Migrazione media per Hiking Routes completata"

print_success "=== FASE 3 COMPLETATA: Apps e Layer configurati ==="

# FASE 4: SETUP ELASTICSEARCH
print_step "=== FASE 4: SETUP ELASTICSEARCH ==="

# Setup Elasticsearch
print_step "Setup Elasticsearch e indicizzazione..."

# Cancellazione indici esistenti (per setup iniziale pulito)
print_warning "Pulizia indici Elasticsearch esistenti... Questa operazione è distruttiva!"
if docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/07-delete-all-elasticsearch-indices.sh --force"; then
    print_success "Indici Elasticsearch puliti (o nessun indice trovato)"
else
    print_warning "Errore durante la pulizia degli indici Elasticsearch (continuo comunque)"
fi

# Abilita indicizzazione automatica Scout
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/04-enable-scout-automatic-indexing.sh"; then
    print_error "Errore durante la configurazione di Scout/Elasticsearch! Interruzione setup."
    exit 1
fi

# Pulisci cache configurazione
print_step "Pulizia cache configurazione..."
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan config:clear"
print_success "Cache configurazione pulita"

# Indicizzazione iniziale
print_step "Avvio indicizzazione iniziale (può richiedere diversi minuti)..."
if ! docker exec "$PHP_CONTAINER" bash -c 'cd /var/www/html/osm2cai2 && php -d max_execution_time=3600 -d memory_limit=2G artisan scout:import-ectrack'; then
    print_error "Errore durante l'indicizzazione iniziale! Interruzione setup."
    exit 1
fi
print_success "Indicizzazione iniziale completata"

print_success "=== FASE 4 COMPLETATA: Elasticsearch configurato ==="

# FASE 5: VERIFICA SERVIZI FINALI
print_step "=== FASE 5: VERIFICA SERVIZI FINALI ==="

# Verifica che Horizon sia attivo
print_step "Verifica stato di Horizon..."
if docker exec "$PHP_CONTAINER" php artisan horizon:status | grep -q "running"; then
    print_success "Horizon attivo e in esecuzione"
else
    print_warning "Horizon non risulta attivo. Potrebbe essere necessario un controllo manuale del Supervisor."
    print_step "Tentativo di riavvio di Horizon..."
    docker exec -d "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan horizon"
    sleep 3
fi

echo ""
echo "🎉 Setup di Produzione per WMPackage to OSM2CAI2 Completato!"
echo "=========================================================="
echo ""
echo "📋 Servizi Docker Attivi:"
echo "   • phpfpm"
echo "   • db (PostgreSQL)"
echo "   • redis"
echo "   • elasticsearch"
echo ""
echo "🔧 Comandi Utili:"
echo "   • Accesso container PHP (root): docker exec -u 0 -it ${PHP_CONTAINER} bash"
echo "   • Accesso container PHP (www-data): docker exec -it ${PHP_CONTAINER} bash"
echo "   • Status Horizon: docker exec ${PHP_CONTAINER} php artisan horizon:status"
echo "   • Riavvio Horizon (via Supervisor): docker exec ${PHP_CONTAINER} php artisan horizon:terminate"
echo "   • Log Laravel: docker exec ${PHP_CONTAINER} tail -f storage/logs/laravel.log"
echo ""
echo "🛑 Per fermare tutto:"
echo "   ${COMPOSE_CMD} down"
echo ""
print_success "Ambiente di produzione pronto!" 