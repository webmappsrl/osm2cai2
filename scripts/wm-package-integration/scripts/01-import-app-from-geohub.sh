#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "📥 Import App da Geohub - OSM2CAI2"
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

# Parametri app
APP_ID=${1:-26}  # Default app ID 26, ma può essere passato come parametro

echo ""
print_step "=== IMPORT APP DA GEOHUB ==="
echo ""
print_step "App ID da importare: $APP_ID"
if [ "$APP_ID" == "26" ]; then
    print_step "🎯 Configurazione speciale per App 26: SOLO taxonomy_activity"
    print_step "⚠️  Skipperò: ec_poi, ec_track, layer, ec_media"
    print_step "📋 App 26 utilizza le hiking routes esistenti e crea layer di accatastamento"
    print_step "🔗 Dopo l'import, verranno creati layer per stati 1,2,3,4 e associate le hiking routes"
else
    print_step "🔄 Configurazione standard: tutte le dipendenze"
fi

# Verifica se esistono già app
APP_COUNT=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::count();\"" 2>/dev/null | tail -1 || echo "0")
print_step "App esistenti nel database: $APP_COUNT"

if [ "$APP_COUNT" -gt 0 ]; then
    echo ""
    print_warning "⚠️  Esistono già $APP_COUNT app nel database"
    print_warning "⚠️  L'import potrebbe creare duplicati o conflitti"
    print_step "Procedo automaticamente con l'import..."
fi

echo ""

# IMPORT: Tentativo con comando principale
print_step "=== IMPORT APP ==="

print_step "Verifica comandi disponibili..."

# Verifica quale comando è disponibile
if docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan list | grep -q 'wm-geohub:import'"; then
    IMPORT_CMD="wm-geohub:import --app=$APP_ID"
    print_step "Utilizzo comando: wm-geohub:import"
elif docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan list | grep -q 'wm:import-from-geohub'"; then
    # Configurazione specifica per app 26: solo taxonomy_activity
    if [ "$APP_ID" == "26" ]; then
        IMPORT_CMD="wm:import-from-geohub app $APP_ID --dependencies=taxonomy_activity"
        print_step "Utilizzo comando: wm:import-from-geohub"
        print_step "🎯 App 26 detected: Import configurato per importare SOLO taxonomy_activity"
        print_step "⚠️  Skipperò: ec_poi, ec_track, layer, ec_media"
    else
        IMPORT_CMD="wm:import-from-geohub app $APP_ID --dependencies=taxonomy_activity,ec_track,layer,ec_media"
        print_step "Utilizzo comando: wm:import-from-geohub"
        print_step "🔄 App $APP_ID: Import standard (tutte le dipendenze)"
    fi
else
    print_error "NESSUN COMANDO DI IMPORT DISPONIBILE!"
    print_error "I comandi 'wm-geohub:import' e 'wm:import-from-geohub' non sono disponibili"
    print_error "Verifica la configurazione di WMPackage e WMGeohub"
    print_error ""
    print_error "Debug utili:"
    print_error "• Controlla package installati: docker exec ${PHP_CONTAINER} composer show | grep wm"
    print_error "• Lista comandi disponibili: docker exec ${PHP_CONTAINER} php artisan list | grep wm"
    exit 1
fi

print_step "Esecuzione import app $APP_ID..."
print_step "Comando: $IMPORT_CMD"

# Esecuzione import - se fallisce, lo script si ferma automaticamente per strict mode
if docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan $IMPORT_CMD"; then
    print_success "Import comando eseguito con successo"
else
    print_error "Import fallito - controlla gli errori sopra"
    exit 1
fi

# VERIFICA IMPORT
print_step "=== VERIFICA IMPORT ==="

print_step "Attendendo processamento code (può richiedere alcuni minuti)..."
print_warning "L'import viene processato in background tramite Horizon"

# Aspetta un po' per il processamento
sleep 10

# Verifica se l'app è stata creata o se è già presente
for i in {1..8}; do
    NEW_APP_COUNT=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::count();\"" 2>/dev/null | tail -1 || echo "0")
    
    # Verifica se esiste un'app con l'ID richiesto o se il numero è aumentato
    APP_EXISTS=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', $APP_ID)->exists() ? 'YES' : 'NO';\"" 2>/dev/null | tail -1 || echo "NO")
    
    if [ "$NEW_APP_COUNT" -gt "$APP_COUNT" ] || [ "$APP_EXISTS" == "YES" ]; then
        print_success "✨ Import completato! App presente nel database"
        print_success "Numero totale app: $NEW_APP_COUNT"
        
        # Mostra dettagli app
        print_step "Dettagli app importata:"
        docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"
            \\\$app = \Wm\WmPackage\Models\App::where('geohub_id', $APP_ID)->first() ?: \Wm\WmPackage\Models\App::latest()->first();
            if (\\\$app) {
                echo 'ID: ' . \\\$app->id . PHP_EOL;
                echo 'Nome: ' . \\\$app->name . PHP_EOL;
                echo 'SKU: ' . \\\$app->sku . PHP_EOL;
                echo 'Cliente: ' . \\\$app->customer_name . PHP_EOL;
                if (isset(\\\$app->geohub_id)) echo 'Geohub ID: ' . \\\$app->geohub_id . PHP_EOL;
            }
        \"" 2>/dev/null || true
        
        break
    else
        print_step "Attesa processamento... ($i/8 - ${i}0 secondi)"
        sleep 10
    fi
done

# Verifica finale più intelligente
APP_EXISTS_FINAL=$(docker exec "${PHP_CONTAINER}" bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"echo \Wm\WmPackage\Models\App::where('geohub_id', $APP_ID)->exists() ? 'YES' : 'NO';\"" 2>/dev/null | tail -1 || echo "NO")

if [ "$NEW_APP_COUNT" -eq "$APP_COUNT" ] && [ "$APP_EXISTS_FINAL" == "NO" ]; then
    print_warning "TIMEOUT: L'app potrebbe non essere stata creata entro 80 secondi"
    print_warning "Possibili cause:"
    print_warning "• Import ancora in processamento (controlla Horizon)"
    print_warning "• Errore durante l'import (controlla log Laravel)"
    print_warning "• Problema di connessione con Geohub"
    print_warning "• App già esistente con ID diverso"
    print_warning ""
    print_warning "Controlli utili:"
    print_warning "• Stato Horizon: http://localhost:8008/horizon"
    print_warning "• Log Laravel: docker exec ${PHP_CONTAINER} tail -f storage/logs/laravel.log"
    print_warning "• App nel database: docker exec ${PHP_CONTAINER} php artisan tinker --execute=\"\Wm\WmPackage\Models\App::all()->pluck('name', 'id')\""
    
    # Non interrompere lo script, ma continua con un warning
    print_warning "Continuando con il setup..."
else
    print_success "App verificata nel database!"
fi

print_success "=== VERIFICA COMPLETATA ==="

echo ""

# RISULTATO FINALE
echo "🎉 Import App da Geohub Completato!"
echo "================================="
echo ""
echo "📊 Statistiche:"
echo "   • App ID importata: $APP_ID"
echo "   • Totale app nel database: $NEW_APP_COUNT"
if [ "$APP_ID" == "26" ]; then
    echo "   • Dipendenze importate: SOLO taxonomy_activity"
    echo "   • Dipendenze saltate: ec_poi, ec_track, layer, ec_media"
    echo "   • Customizzazioni: Utilizza hiking routes esistenti + crea layer accatastamento"
else
    echo "   • Dipendenze importate: TUTTE (comportamento standard)"
fi
echo ""
echo "🔧 Prossimi passi:"
echo "   1. Verifica app in Nova Admin: http://localhost:8008/nova/resources/apps"
echo "   2. Controlla stato Horizon: http://localhost:8008/horizon"
if [ "$APP_ID" == "26" ]; then
    echo "   3. 🎯 App 26: Layer di accatastamento verranno creati automaticamente"
    echo "   4. 🎯 App 26: Hiking routes verranno associate automaticamente ai layer"
    echo "   5. ⚠️  NOTA App 26: Layer NON importati da Geohub - se necessari, importa manualmente:"
    echo "      docker exec ${PHP_CONTAINER} php artisan wm:import-from-geohub app $APP_ID --dependencies=layer"
    echo "   6. ⚠️  NOTA App 26: POI/Track NON importati - se necessari, importa manualmente:"
    echo "      docker exec ${PHP_CONTAINER} php artisan wm:import-from-geohub app $APP_ID --dependencies=ec_poi,ec_track"
else
    echo "   3. Se necessario, crea layer per l'app:"
    echo "      docker exec ${PHP_CONTAINER} php artisan osm2cai:create-accatastamento-layers"
    echo "   4. Associa hiking routes ai layer:"
    echo "      docker exec ${PHP_CONTAINER} php artisan osm2cai:associate-hiking-routes-to-layers"
fi
echo ""

print_success "Script import completato!" 