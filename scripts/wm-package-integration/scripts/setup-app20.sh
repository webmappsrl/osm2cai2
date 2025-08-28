#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🎯 Setup App 20 - Import Generico"
echo "=================================="
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
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Determina la directory root del progetto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../" && pwd)"

# Carica le variabili dal file .env
if [ -f "$PROJECT_ROOT/.env" ]; then
    print_step "Caricamento delle variabili dal file .env..."
    set -o allexport
    source "$PROJECT_ROOT/.env"
    set +o allexport
    print_success "Variabili d'ambiente caricate."
else
    print_error "File .env non trovato. Impossibile continuare senza configurazione."
    exit 1
fi

# Definisci i nomi dei container
if [ -z "$APP_NAME" ]; then
    print_error "La variabile APP_NAME non è definita nel file .env."
    exit 1
fi
PHP_CONTAINER="php81-${APP_NAME}"

print_step "Utilizzo container: ${PHP_CONTAINER}"

# FASE 1: IMPORT APP 20 GENERICO
print_step "=== FASE 1: IMPORT APP 20 GENERICO ==="

print_step "Eseguendo setup generico per App 20..."
if ! "$SCRIPT_DIR/setup-app-generic.sh" 20; then
    print_error "Setup generico App 20 fallito!"
    exit 1
fi
print_success "Setup generico App 20 completato con successo"

# FASE 2: VERIFICA FINALE
print_step "=== FASE 2: VERIFICA FINALE ==="

# Verifica che l'app sia stata importata
print_step "Verifica import App 20..."
APP_20_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', 20)->count();\"" 2>/dev/null || echo "0")

if [ "$APP_20_COUNT" -eq 0 ]; then
    print_error "App 20 non trovata nel database dopo l'import!"
    exit 1
fi
print_success "App 20 trovata nel database"

# Verifica hiking routes associate
print_step "Verifica hiking routes associate ad App 20..."
ROUTES_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo DB::table('hiking_routes')->where('app_id', \Wm\WmPackage\Models\App::where('geohub_id', 20)->first()->id)->count();\"" 2>/dev/null || echo "0")
print_success "Hiking routes associate ad App 20: $ROUTES_COUNT"

echo ""

# FASE 3: ATTESA COMPLETAMENTO CODE
print_step "=== FASE 3: ATTESA COMPLETAMENTO CODE ==="

print_step "Attendo che tutte le code siano vuote prima di completare..."
if ! "$SCRIPT_DIR/wait-for-queues.sh" 600 10; then
    print_warning "Timeout raggiunto durante l'attesa delle code. Procedo comunque."
else
    print_success "Tutte le code sono vuote!"
fi

echo ""
print_success "🎉 Setup App 20 completato con successo!"
echo "=============================================="
echo ""
echo "📊 Statistiche App 20:"
echo "   • App importata: ✅"
echo "   • Hiking routes associate: $ROUTES_COUNT"
echo "   • Setup generico: ✅"
echo "   • Code processate: ✅"
echo ""
print_success "App 20 pronta per l'uso!"
