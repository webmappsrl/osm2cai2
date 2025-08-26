#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

# Verifica che sia stato passato l'ID dell'app come parametro
if [ $# -eq 0 ]; then
    echo "❌ ERRORE: Devi specificare l'ID dell'app come parametro"
    echo "Uso: $0 <APP_ID>"
    echo "Esempio: $0 20"
    exit 1
fi

APP_ID="$1"

echo "🎯 Setup App $APP_ID - Import Generico"
echo "======================================"
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
print_step "Import App ID: ${APP_ID}"

# FASE 1: IMPORT APP
print_step "=== FASE 1: IMPORT APP $APP_ID DA GEOHUB ==="

print_step "Import App da Geohub con ID $APP_ID..."
if ! ./scripts/01-import-app-from-geohub.sh $APP_ID; then
    print_error "Import App da Geohub con ID $APP_ID fallito!"
    exit 1
fi
print_success "Import App da Geohub con ID $APP_ID completato con successo"

# FASE 2: VERIFICA FINALE
print_step "=== FASE 2: VERIFICA FINALE ==="

# Verifica che l'app sia stata importata
print_step "Verifica import App $APP_ID..."
APP_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', $APP_ID)->count();\"" 2>/dev/null || echo "0")

if [ "$APP_COUNT" -eq 0 ]; then
    print_error "App $APP_ID non trovata nel database dopo l'import!"
    exit 1
fi
print_success "App $APP_ID trovata nel database"

# Verifica hiking routes associate (se ce ne sono)
print_step "Verifica hiking routes associate ad App $APP_ID..."
ROUTES_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo DB::table('hiking_routes')->where('app_id', \Wm\WmPackage\Models\App::where('geohub_id', $APP_ID)->first()->id)->count();\"" 2>/dev/null || echo "0")
print_success "Hiking routes associate ad App $APP_ID: $ROUTES_COUNT"

echo ""
print_success "🎉 Setup App $APP_ID completato con successo!"
echo "=================================================="
echo ""
echo "📊 Statistiche App $APP_ID:"
echo "   • App importata: ✅"
echo "   • Hiking routes associate: $ROUTES_COUNT"
echo ""
print_success "App $APP_ID pronta per l'uso!"
