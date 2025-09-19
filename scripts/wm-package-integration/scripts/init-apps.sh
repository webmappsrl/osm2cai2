#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🚀 Inizializzazione App Models - OSM2CAI2"
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

# App da inizializzare (solo modelli, senza dipendenze)
APPS_TO_INIT=(26 20 58)

echo ""
print_step "=== INIZIALIZZAZIONE APP MODELS ==="
echo ""
print_step "App da inizializzare: ${APPS_TO_INIT[*]}"
print_step "🎯 Modalità: SOLO modelli app (senza dipendenze)"
print_step "⚠️  Verranno creati solo gli ID delle app, nessuna dipendenza verrà importata"
print_step "📋 Le dipendenze verranno importate successivamente durante l'import specifico di ogni app"
echo ""

# Verifica se esistono già app
APP_COUNT_BEFORE=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::count();\"" 2>/dev/null | tail -1 || echo "0")
print_step "App esistenti nel database prima dell'inizializzazione: $APP_COUNT_BEFORE"

# Verifica quale comando è disponibile
if docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan list | grep -q 'wm:import-from-geohub'"; then
    IMPORT_CMD="wm:import-from-geohub"
    print_step "Utilizzo comando: wm:import-from-geohub"
else
    print_error "COMANDO DI IMPORT NON DISPONIBILE!"
    print_error "Il comando 'wm:import-from-geohub' non è disponibile"
    print_error "Verifica la configurazione di WMPackage"
    exit 1
fi

# Inizializza ogni app
for APP_ID in "${APPS_TO_INIT[@]}"; do
    echo ""
    print_step "=== INIZIALIZZAZIONE APP $APP_ID ==="
    
    # Verifica se l'app esiste già
    APP_EXISTS=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', $APP_ID)->exists() ? 'YES' : 'NO';\"" 2>/dev/null | tail -1 || echo "NO")
    
    if [ "$APP_EXISTS" == "YES" ]; then
        print_warning "App $APP_ID esiste già nel database, salto l'inizializzazione"
        continue
    fi
    
    print_step "Import app $APP_ID (solo modello, senza dipendenze)..."
    print_step "Comando: $IMPORT_CMD app $APP_ID --skip-dependencies"
    
    # Esecuzione import - solo modello app, senza dipendenze
    if docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan $IMPORT_CMD app $APP_ID --skip-dependencies"; then
        print_success "Import modello app $APP_ID eseguito con successo"
    else
        print_error "Import modello app $APP_ID fallito - controlla gli errori sopra"
        exit 1
    fi
    
    # Attesa breve per il processamento
    sleep 5
    
    # Verifica che l'app sia stata creata
    APP_EXISTS_AFTER=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', $APP_ID)->exists() ? 'YES' : 'NO';\"" 2>/dev/null | tail -1 || echo "NO")
    
    if [ "$APP_EXISTS_AFTER" == "YES" ]; then
        print_success "App $APP_ID verificata nel database"
        
        # Mostra dettagli app
        print_step "Dettagli app $APP_ID:"
        docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"
            \\\$app = \Wm\WmPackage\Models\App::where('geohub_id', $APP_ID)->first();
            if (\\\$app) {
                echo 'ID: ' . \\\$app->id . PHP_EOL;
                echo 'Nome: ' . \\\$app->name . PHP_EOL;
                echo 'SKU: ' . \\\$app->sku . PHP_EOL;
                echo 'Cliente: ' . \\\$app->customer_name . PHP_EOL;
                echo 'Geohub ID: ' . \\\$app->geohub_id . PHP_EOL;
            }
        \"" 2>/dev/null || true
    else
        print_warning "App $APP_ID potrebbe non essere stata creata (verifica in corso...)"
    fi
done

# Verifica finale
echo ""
print_step "=== VERIFICA FINALE ==="

APP_COUNT_AFTER=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::count();\"" 2>/dev/null | tail -1 || echo "0")
print_step "App esistenti nel database dopo l'inizializzazione: $APP_COUNT_AFTER"

APPS_CREATED=$((APP_COUNT_AFTER - APP_COUNT_BEFORE))
print_step "App create durante l'inizializzazione: $APPS_CREATED"

# Verifica che tutte le app siano presenti
ALL_APPS_PRESENT=true
for APP_ID in "${APPS_TO_INIT[@]}"; do
    APP_EXISTS=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', $APP_ID)->exists() ? 'YES' : 'NO';\"" 2>/dev/null | tail -1 || echo "NO")
    if [ "$APP_EXISTS" == "NO" ]; then
        print_warning "App $APP_ID non trovata nel database"
        ALL_APPS_PRESENT=false
    fi
done

if [ "$ALL_APPS_PRESENT" = true ]; then
    print_success "Tutte le app sono presenti nel database!"
else
    print_warning "Alcune app potrebbero non essere state create correttamente"
fi

print_success "=== INIZIALIZZAZIONE COMPLETATA ==="

echo ""
echo "🎉 Inizializzazione App Models Completata!"
echo "=========================================="
echo ""
echo "📊 Statistiche:"
echo "   • App inizializzate: ${APPS_TO_INIT[*]}"
echo "   • App create: $APPS_CREATED"
echo "   • Totale app nel database: $APP_COUNT_AFTER"
echo "   • Modalità: SOLO modelli (senza dipendenze)"
echo ""
echo "🔧 Prossimi passi:"
echo "   • Le app sono ora disponibili per l'import con dipendenze"
echo "   • Ogni app può essere importata con le sue dipendenze specifiche"
echo "   • Verifica app in Nova Admin: http://localhost:8008/nova/resources/apps"
echo "   • Controlla stato Horizon: http://localhost:8008/horizon"
echo ""

print_success "Script inizializzazione completato!"
