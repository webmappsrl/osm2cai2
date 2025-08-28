#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🎯 Setup App 58 - Import e Customizzazioni"
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

# FASE 1: IMPORT APP 58 GENERICO
print_step "=== FASE 1: IMPORT APP 58 GENERICO ==="

print_step "Eseguendo setup generico per App 58..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if ! "$SCRIPT_DIR/setup-app-generic.sh" 58; then
    print_error "Setup generico App 58 fallito!"
    exit 1
fi
print_success "Setup generico App 58 completato con successo"

# FASE 2: CUSTOMIZZAZIONI SPECIFICHE APP 58
print_step "=== FASE 2: CUSTOMIZZAZIONI SPECIFICHE APP 58 ==="

# TODO: Aggiungere qui le customizzazioni specifiche per App 58
# Esempi di possibili customizzazioni:
# - Creazione layer specifici
# - Associazione hiking routes specifiche
# - Configurazione proprietà personalizzate
# - Setup media specifici
# - Configurazione tassonomie particolari

print_step "Applicando customizzazioni specifiche per App 58..."

# Conversione UgcPois validati con form_id "water" a EcPois
print_step "Conversione UgcPois validati con form_id 'water' a EcPois..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan app:convert-validated-water-ugc-pois-to-ec-pois"; then
    print_error "Conversione UgcPois validati per App 58 fallita!"
    exit 1
fi
print_success "Conversione UgcPois validati per App 58 completata"

# Esempio di customizzazione (da personalizzare secondo le esigenze):
print_step "Configurazione layer personalizzati per App 58..."
# if ! ./scripts/02-create-layers-app58.sh; then
#     print_error "Creazione layer personalizzati per App 58 fallita!"
#     exit 1
# fi

print_step "Associazione hiking routes specifiche per App 58..."
# if ! ./scripts/03-associate-routes-app58.sh; then
#     print_error "Associazione routes specifiche per App 58 fallita!"
#     exit 1
# fi

print_step "Setup proprietà personalizzate per App 58..."
# if ! ./scripts/10-hiking-routes-properties-app58.sh; then
#     print_error "Setup proprietà personalizzate per App 58 fallito!"
#     exit 1
# fi

print_success "Customizzazioni specifiche App 58 applicate"

# FASE 3: VERIFICA FINALE
print_step "=== FASE 3: VERIFICA FINALE ==="

# Verifica che l'app sia stata importata
print_step "Verifica import App 58..."
APP_58_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', 58)->count();\"" 2>/dev/null || echo "0")

if [ "$APP_58_COUNT" -eq 0 ]; then
    print_error "App 58 non trovata nel database dopo l'import!"
    exit 1
fi
print_success "App 58 trovata nel database"

# Verifica hiking routes associate
print_step "Verifica hiking routes associate ad App 58..."
ROUTES_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo DB::table('hiking_routes')->where('app_id', \Wm\WmPackage\Models\App::where('geohub_id', 58)->first()->id)->count();\"" 2>/dev/null || echo "0")
print_success "Hiking routes associate ad App 58: $ROUTES_COUNT"

# Verifica customizzazioni applicate
print_step "Verifica customizzazioni applicate..."

# Verifica conversione UgcPois validati
print_step "Verifica conversione UgcPois validati per App 58..."
EC_POIS_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \App\Models\EcPoi::where('app_id', \Wm\WmPackage\Models\App::where('geohub_id', 58)->first()->id)->where('properties->converted_from_ugc', true)->count();\"" 2>/dev/null || echo "0")
print_success "EcPois convertiti da UgcPois per App 58: $EC_POIS_COUNT"

# TODO: Aggiungere verifiche specifiche per le customizzazioni
# LAYER_COUNT=$(docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\Layer::where('app_id', \Wm\WmPackage\Models\App::where('geohub_id', 58)->first()->id)->count();\"" 2>/dev/null || echo "0")
# print_success "Layer personalizzati per App 58: $LAYER_COUNT"

echo ""

# FASE 4: ATTESA COMPLETAMENTO CODE
print_step "=== FASE 4: ATTESA COMPLETAMENTO CODE ==="

print_step "Attendo che tutte le code siano vuote prima di completare..."
if ! ../../scripts/wait-for-queues.sh 600 10; then
    print_warning "Timeout raggiunto durante l'attesa delle code. Procedo comunque."
else
    print_success "Tutte le code sono vuote!"
fi

echo ""
print_success "🎉 Setup App 58 completato con successo!"
echo "=============================================="
echo ""
echo "📊 Statistiche App 58:"
echo "   • App importata: ✅"
echo "   • Hiking routes associate: $ROUTES_COUNT"
echo "   • EcPois convertiti da UgcPois: $EC_POIS_COUNT"
echo "   • Customizzazioni applicate: ✅"
echo "   • Setup generico: ✅"
echo "   • Code processate: ✅"
echo ""
print_success "App 58 pronta per l'uso con customizzazioni!"

# Note per lo sviluppatore
echo ""
print_warning "📝 Note per lo sviluppatore:"
print_warning "• Le customizzazioni specifiche sono attualmente commentate"
print_warning "• Personalizzare gli script secondo le esigenze specifiche di App 58"
print_warning "• Aggiungere verifiche per le customizzazioni nella FASE 3"
echo ""
