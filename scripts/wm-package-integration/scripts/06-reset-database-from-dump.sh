#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🗑️  Reset Database OSM2CAI2 da Dump"
echo "=================================="
echo ""

# Funzione per gestire errori
handle_error() {
    echo ""
    echo "❌ ERRORE: Script interrotto alla riga $1"
    echo "❌ Ultimo comando: $BASH_COMMAND"
    echo ""
    echo "🔧 Possibili soluzioni:"
    echo "   • Verifica che il file dump esista"
    echo "   • Controlla che PostgreSQL sia attivo: docker ps"
    echo "   • Verifica i permessi sul file dump"
    echo "   • Riavvia l'ambiente: docker-compose restart"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

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

# Determina la directory root del progetto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../" && pwd)"

# Carica le variabili dal file .env se esiste
if [ -f "$PROJECT_ROOT/.env" ]; then
    print_step "Caricamento delle variabili dal file .env..."
    # Esporta le variabili per renderle disponibili allo script
    set -o allexport
    source "$PROJECT_ROOT/.env"
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
PHP_CONTAINER="php81-${APP_NAME}"
POSTGRES_CONTAINER="postgres-${APP_NAME}"

print_step "Utilizzo dei nomi container: ${PHP_CONTAINER}, ${POSTGRES_CONTAINER}"

# Verifica che il file dump esista
if [ ! -f "$PROJECT_ROOT/storage/app/backups/dump.sql.gz" ]; then
    print_error "File dump non trovato in $PROJECT_ROOT/storage/app/backups/dump.sql.gz"
    exit 1
fi

print_success "Prerequisiti verificati"

# Verifica che PostgreSQL sia attivo
print_step "Verifica che PostgreSQL sia attivo..."
if ! docker exec "$POSTGRES_CONTAINER" pg_isready -h localhost -p 5432 &> /dev/null; then
    print_error "PostgreSQL non è attivo. Avvia prima l'ambiente con docker-compose up -d"
    exit 1
fi
print_success "PostgreSQL attivo"

# Controlla se è modalità automatica (cronjob)
AUTO_MODE=false
if [ "$1" = "--auto" ] || [ "$1" = "-a" ]; then
    AUTO_MODE=true
fi

# Conferma dall'utente (solo se non in modalità automatica)
if [ "$AUTO_MODE" = false ]; then
    echo ""
    print_warning "⚠️  ATTENZIONE: Questa operazione cancellerà TUTTI i dati nel database!"
    print_warning "⚠️  Il database verrà sostituito completamente con il dump di backup."
    echo ""
    read -p "🤔 Sei sicuro di voler procedere? (digita 'SI' per confermare): " confirm

    if [ "$confirm" != "SI" ]; then
        print_warning "Operazione annullata"
        exit 0
    fi
else
    print_step "🤖 Modalità automatica (cronjob) - procedo senza conferma utente"
fi

echo ""

# FASE 1: Ferma applicazioni che usano il database
print_step "=== FASE 1: STOP SERVIZI COLLEGATI AL DATABASE ==="

# Ferma Laravel server se attivo
print_step "Fermando Laravel server..."
docker exec "$PHP_CONTAINER" bash -c "pkill -f 'php artisan serve'" 2>/dev/null || true
print_success "Laravel server fermato"

# Ferma Horizon se attivo
print_step "Fermando Laravel Horizon..."
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan horizon:terminate" 2>/dev/null || true
print_success "Horizon fermato"

# Aspetta che i processi si fermino
sleep 3

print_success "=== FASE 1 COMPLETATA ==="

# FASE 2: Reset Database
print_step "=== FASE 2: RESET DATABASE ==="

# Termina connessioni attive al database
print_step "Terminando connessioni attive al database..."
docker exec "$POSTGRES_CONTAINER" psql -U osm2cai2 -d postgres -c "
SELECT pg_terminate_backend(pid) 
FROM pg_stat_activity 
WHERE datname = 'osm2cai2' AND pid <> pg_backend_pid();
" 2>/dev/null || true

# Drop e ricrea database
print_step "Cancellando database osm2cai2..."
docker exec "$POSTGRES_CONTAINER" psql -U osm2cai2 -d postgres -c "DROP DATABASE IF EXISTS osm2cai2;" 2>/dev/null
print_success "Database cancellato"

print_step "Ricreando database osm2cai2..."
docker exec "$POSTGRES_CONTAINER" psql -U osm2cai2 -d postgres -c "CREATE DATABASE osm2cai2 OWNER osm2cai2;"
print_success "Database ricreato"

print_success "=== FASE 2 COMPLETATA ==="

# FASE 3: Ripristino da Dump
print_step "=== FASE 3: RIPRISTINO DA DUMP ==="

print_step "Estraendo e caricando dump (questo può richiedere diversi minuti)..."
print_warning "Dimensione dump: $(du -h $PROJECT_ROOT/storage/app/backups/dump.sql.gz | cut -f1)"

# Carica il dump
if gunzip -c $PROJECT_ROOT/storage/app/backups/dump.sql.gz | docker exec -i "$POSTGRES_CONTAINER" psql -U osm2cai2 -d osm2cai2; then
    print_success "Dump caricato con successo"
else
    print_error "Errore durante il caricamento del dump"
    exit 1
fi

print_success "=== FASE 3 COMPLETATA ==="

# FASE 4: Verifica e Test
print_step "=== FASE 4: VERIFICA DATABASE ==="

# Test connessione database
print_step "Test connessione database..."
if docker exec "$POSTGRES_CONTAINER" psql -U osm2cai2 -d osm2cai2 -c "SELECT 1;" &> /dev/null; then
    print_success "Connessione database OK"
else
    print_error "Problema connessione database"
    exit 1
fi

# Conta record principali
print_step "Verifica dati principali..."
HIKING_ROUTES=$(docker exec "$POSTGRES_CONTAINER" psql -U osm2cai2 -d osm2cai2 -t -c "SELECT count(*) FROM hiking_routes;" 2>/dev/null | tr -d ' ' || echo "0")
print_success "HikingRoutes trovati: $HIKING_ROUTES"

USERS=$(docker exec "$POSTGRES_CONTAINER" psql -U osm2cai2 -d osm2cai2 -t -c "SELECT count(*) FROM users;" 2>/dev/null | tr -d ' ' || echo "0")
print_success "Users trovati: $USERS"

print_success "=== FASE 4 COMPLETATA ==="

# FASE 5: Restart Servizi
print_step "=== FASE 5: RIAVVIO SERVIZI ==="

# Clear cache Laravel
print_step "Pulizia cache Laravel..."
docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan cache:clear && php artisan config:clear && php artisan view:clear" 2>/dev/null || true
print_success "Cache pulita"

# Riavvia Laravel server
print_step "Riavvio Laravel server..."
docker exec -d "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan serve --host 0.0.0.0"
sleep 3
print_success "Laravel server riavviato"

# Riavvia Horizon
print_step "Riavvio Laravel Horizon..."
docker exec -d "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan horizon"
sleep 2
print_success "Horizon riavviato"

print_success "=== FASE 5 COMPLETATA ==="

echo ""
echo "🎉 Reset Database Completato!"
echo "============================"
echo ""
echo "📊 Statistiche Database:"
echo "   • HikingRoutes: $HIKING_ROUTES"
echo "   • Users: $USERS"
echo ""
echo "🌐 Servizi Disponibili:"
echo "   • Applicazione: http://localhost:8008"
echo "   • Nova Admin: http://localhost:8008/nova"
echo "   • Dashboard Horizon: http://localhost:8008/horizon"
echo ""
echo "🔧 Prossimi passi consigliati:"
echo "   1. Verifica login Nova Admin"
echo "   2. Controlla che i dati siano presenti"
echo "   3. Se necessario, riesegui le migrazioni:"
echo "      docker exec php81-osm2cai2 php artisan migrate"
echo "   4. Reindicizza Elasticsearch se necessario:"
echo "      docker exec php81-osm2cai2 php artisan scout:import 'App\\Models\\HikingRoute'"
echo ""
print_success "Database ripristinato con successo dal dump!" 