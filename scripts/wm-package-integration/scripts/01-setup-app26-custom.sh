#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🎯 Setup App 26 - Import e Customizzazioni"
echo "=========================================="
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

# FASE 1: IMPORT APP 26
print_step "=== FASE 1: IMPORT APP 26 DA GEOHUB ==="

print_step "Import App da Geohub con ID 26..."
if ! ./scripts/01-import-app-from-geohub.sh 26; then
    print_error "Import App da Geohub con ID 26 fallito!"
    exit 1
fi
print_success "Import App da Geohub con ID 26 completato con successo"

# FASE 2: CREAZIONE LAYER DI ACCATASTAMENTO
print_step "=== FASE 2: CREAZIONE LAYER DI ACCATASTAMENTO ==="

print_step "Creazione layer di accatastamento per app 26..."
if ! ./scripts/02-create-layers-app26.sh; then
    print_error "Creazione layer per app 26 fallita!"
    exit 1
fi
print_success "Layer di accatastamento per app 26 creati"

# FASE 3: ASSOCIAZIONE TUTTE LE HIKING ROUTES ALLA PRIMA APP
print_step "=== FASE 3: ASSOCIAZIONE TUTTE LE HIKING ROUTES ALLA PRIMA APP ==="

print_step "Associazione di tutte le hiking routes senza app_id alla prima app..."
FIRST_APP_ID=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::first()->id ?? 1;\"" 2>/dev/null || echo "1")
ROUTES_WITHOUT_APP=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo DB::table('hiking_routes')->whereNull('app_id')->count();\"" 2>/dev/null || echo "0")

if [ "$ROUTES_WITHOUT_APP" -gt 0 ]; then
    print_step "Assegnazione app_id=$FIRST_APP_ID a $ROUTES_WITHOUT_APP hiking routes senza app..."
    docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"
        \\\$count = DB::table('hiking_routes')->whereNull('app_id')->update(['app_id' => $FIRST_APP_ID]);
        echo 'Aggiornate ' . \\\$count . ' hiking routes con app_id=$FIRST_APP_ID';
    \""
    print_success "Hiking routes associate alla prima app (App 26)"
else
    print_success "Nessuna hiking route senza app_id trovata"
fi

# FASE 4: ASSOCIAZIONE HIKING ROUTES AI LAYER DI ACCATASTAMENTO
print_step "=== FASE 4: ASSOCIAZIONE HIKING ROUTES AI LAYER DI ACCATASTAMENTO ==="

print_step "Associazione hiking routes ai layer per app 26..."
if ! ./scripts/03-associate-routes-app26.sh; then
    print_error "Associazione hiking routes per app 26 fallita!"
    exit 1
fi
print_success "Hiking routes associati ai layer per app 26"

# FASE 5: POPOLAMENTO PROPRIETÀ E TASSONOMIE
print_step "=== FASE 5: POPOLAMENTO PROPRIETÀ E TASSONOMIE ==="

print_step "Popolamento proprietà e tassonomie per i percorsi..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/10-hiking-routes-properties-and-taxonomy.sh"; then
    print_error "Errore durante il popolamento delle proprietà e tassonomie dei percorsi!"
    exit 1
fi
print_success "Proprietà e tassonomie dei percorsi popolate"

# FASE 6: MIGRAZIONE MEDIA
print_step "=== FASE 6: MIGRAZIONE MEDIA ==="

print_step "Migrazione media per Hiking Routes..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/09-migrate-hiking-route-media.sh full"; then
    print_error "Errore durante la migrazione dei media!"
    exit 1
fi
print_success "Migrazione media per Hiking Routes completata"

# FASE 7: VERIFICA FINALE
print_step "=== FASE 7: VERIFICA FINALE ==="

# Verifica che l'app sia stata importata
print_step "Verifica import App 26..."
APP_26_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', 26)->count();\"" 2>/dev/null || echo "0")

if [ "$APP_26_COUNT" -eq 0 ]; then
    print_error "App 26 non trovata nel database dopo l'import!"
    exit 1
fi
print_success "App 26 trovata nel database"

# Verifica layer creati
print_step "Verifica layer creati per App 26..."
LAYER_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\Layer::where('app_id', \Wm\WmPackage\Models\App::where('geohub_id', 26)->first()->id)->count();\"" 2>/dev/null || echo "0")
print_success "Layer creati per App 26: $LAYER_COUNT"

# Verifica hiking routes associate
print_step "Verifica hiking routes associate ad App 26..."
ROUTES_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo DB::table('hiking_routes')->where('app_id', \Wm\WmPackage\Models\App::where('geohub_id', 26)->first()->id)->count();\"" 2>/dev/null || echo "0")
print_success "Hiking routes associate ad App 26: $ROUTES_COUNT"

echo ""
print_success "🎉 Setup App 26 completato con successo!"
echo "=============================================="
echo ""
echo "📊 Statistiche App 26:"
echo "   • App importata: ✅"
echo "   • Layer creati: $LAYER_COUNT"
echo "   • Hiking routes associate: $ROUTES_COUNT"
echo "   • Proprietà e tassonomie: ✅"
echo "   • Media migrati: ✅"
echo ""
print_success "App 26 pronta per l'uso!"
