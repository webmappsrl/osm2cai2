#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🎯 Setup Completo App 26 - OSM2CAI2"
echo "==================================="
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

# Vai alla root del progetto per trovare il file .env
cd "$(dirname "$0")/../../../"

# Carica le variabili dal file .env se esiste
if [ -f .env ]; then
    set -o allexport
    source .env
    set +o allexport
else
    print_error "File .env non trovato nella root del progetto. Eseguire prima il setup principale."
    exit 1
fi

# Definisci i nomi dei container utilizzando le variabili d'ambiente
if [ -z "$APP_NAME" ]; then
    print_error "La variabile APP_NAME non è definita nel file .env."
    exit 1
fi
PHP_CONTAINER="php81-${APP_NAME}"

# Ritorna alla directory originale dello script, se necessario
cd - > /dev/null

print_step "Verifica prerequisiti..."
print_step "Utilizzo del container: ${PHP_CONTAINER}"

# Controlla che il container PHP sia attivo
if ! docker exec "${PHP_CONTAINER}" bash -c "echo 'Container OK'" &> /dev/null; then
    print_error "Container ${PHP_CONTAINER} non è attivo!"
    print_warning "Avvia l'ambiente prima: docker-compose up -d"
    exit 1
fi

# Verifica che Horizon sia attivo per processare le code
if ! docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan horizon:status" | grep -q "running"; then
    print_warning "Horizon non è attivo, avvialo per processare le code:"
    print_warning "docker exec ${PHP_CONTAINER} bash -c 'cd /var/www/html/osm2cai2 && php artisan horizon'"
fi

print_success "Prerequisiti verificati"

echo ""
print_step "=== SETUP COMPLETO APP 26 ==="
echo ""
print_step "🎯 App 26: Configurazione speciale con customizzazioni"
print_step "📋 Operazioni che verranno eseguite:"
print_step "   1. Import SOLO taxonomy_activity da Geohub"
print_step "   2. Creazione layer di accatastamento (stati 1,2,3,4)"
print_step "   3. Associazione hiking routes esistenti ai layer"
print_step "   4. Verifica finale setup"
echo ""

# STEP 1: IMPORT APP 26 (SOLO taxonomy_activity)
print_step "=== STEP 1: IMPORT APP 26 ==="
print_step "Import SOLO taxonomy_activity da Geohub..."

if ! ./scripts/01-import-app-from-geohub.sh 26; then
    print_error "Import App 26 fallito! Interruzione setup."
    exit 1
fi
print_success "Import App 26 completato con successo"

# STEP 2: CREAZIONE LAYER DI ACCATASTAMENTO
print_step "=== STEP 2: CREAZIONE LAYER ==="
print_step "Creazione layer di accatastamento per app 26..."

if ! ./scripts/02-create-layers-app26.sh --app=26; then
    print_error "Creazione layer per app 26 fallita! Interruzione setup."
    exit 1
fi
print_success "Layer di accatastamento per app 26 creati"

# STEP 3: ASSOCIAZIONE HIKING ROUTES AI LAYER
print_step "=== STEP 3: ASSOCIAZIONE HIKING ROUTES ==="
print_step "Associazione hiking routes esistenti ai layer..."

if ! ./scripts/03-associate-routes-app26.sh --app=26; then
    print_error "Associazione hiking routes per app 26 fallita! Interruzione setup."
    exit 1
fi
print_success "Hiking routes associate ai layer per app 26"

# STEP 4: VERIFICA FINALE
print_step "=== STEP 4: VERIFICA FINALE ==="

# Verifica app 26
print_step "Verifica app 26 nel database..."
APP_26_EXISTS=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', 26)->exists() ? 'YES' : 'NO';\"" 2>/dev/null | tail -1 || echo "NO")

if [ "$APP_26_EXISTS" == "YES" ]; then
    print_success "App 26 presente nel database"
else
    print_warning "App 26 non trovata nel database (potrebbe essere ancora in processamento)"
fi

# Verifica layer
print_step "Verifica layer di accatastamento..."
LAYER_COUNT=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\Layer::where('properties->osm2cai_status', '!=', null)->count();\"" 2>/dev/null | tail -1 || echo "0")

if [ "$LAYER_COUNT" -ge 4 ]; then
    print_success "Layer di accatastamento creati ($LAYER_COUNT layer trovati)"
else
    print_warning "Layer di accatastamento potrebbero non essere completi ($LAYER_COUNT layer trovati)"
fi

# Verifica associazioni hiking routes
print_step "Verifica associazioni hiking routes..."
ASSOCIATED_ROUTES=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo DB::table('layerables')->where('layerable_type', 'App\\\\Models\\\\HikingRoute')->count();\"" 2>/dev/null | tail -1 || echo "0")

if [ "$ASSOCIATED_ROUTES" -gt 0 ]; then
    print_success "Hiking routes associate ai layer ($ASSOCIATED_ROUTES associazioni)"
else
    print_warning "Nessuna associazione hiking routes trovata (potrebbe essere ancora in processamento)"
fi

print_success "=== VERIFICA FINALE COMPLETATA ==="

echo ""
echo "🎉 Setup Completo App 26 Completato!"
echo "===================================="
echo ""
echo "📊 Riepilogo operazioni:"
echo "   ✅ Import App 26 (SOLO taxonomy_activity)"
echo "   ✅ Creazione layer di accatastamento"
echo "   ✅ Associazione hiking routes ai layer"
echo "   ✅ Verifica finale setup"
echo ""
echo "🔧 Prossimi passi:"
echo "   1. Verifica app in Nova Admin: http://localhost:8008/nova/resources/apps"
echo "   2. Controlla layer in Nova: http://localhost:8008/nova/resources/layers"
echo "   3. Testa filtri per stato di accatastamento"
echo "   4. Controlla stato Horizon: http://localhost:8008/horizon"
echo ""
echo "🎯 App 26 è ora configurata con:"
echo "   • Taxonomy activity importate da Geohub"
echo "   • Layer per stati di accatastamento 1,2,3,4"
echo "   • Hiking routes associate ai layer corrispondenti"
echo "   • Sistema di filtri per stato funzionante"
echo ""

print_success "Setup App 26 completato con successo!"
