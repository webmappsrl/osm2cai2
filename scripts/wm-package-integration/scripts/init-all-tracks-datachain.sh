#!/bin/bash

# Script per inizializzare la data chain di tutti gli EcTrack
# Questo script lancia initDataChain per ogni hiking route nel database

set -e
set -o pipefail

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

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
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

print_step "=== INIZIALIZZAZIONE DATA CHAIN HIKING ROUTES ==="
print_info "Questo processo lancerà initDataChain per tutti gli EcTrack nel database"
print_info "La catena include: OSM data, DEM, manual data, 3D DEM, slope, taxonomy, charts, AWS, related POI"
echo ""

# Determina se siamo in container o su host
if [ -f "/var/www/html/osm2cai2/.env" ]; then
    # Siamo nel container
    IN_CONTAINER=true
    WORKING_DIR="/var/www/html/osm2cai2"
    print_info "Esecuzione dal container Docker"
else
    # Siamo sull'host
    IN_CONTAINER=false
    PHP_CONTAINER="php81-osm2cai2"
    print_info "Esecuzione dall'host"
fi

# Funzione per eseguire comandi PHP
run_php() {
    local php_code="$1"
    if [ "$IN_CONTAINER" = true ]; then
        php -r "$php_code"
    else
        docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php -r '$php_code'"
    fi
}

# Funzione per eseguire script PHP temporanei
run_php_script() {
    local script_content="$1"
    if [ "$IN_CONTAINER" = true ]; then
        echo "$script_content" > /tmp/init_tracks_datachain.php
        php /tmp/init_tracks_datachain.php
        rm /tmp/init_tracks_datachain.php
    else
        docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && cat > /tmp/init_tracks_datachain.php << 'PHPEOF'
$script_content
PHPEOF
php /tmp/init_tracks_datachain.php
rm /tmp/init_tracks_datachain.php"
    fi
}

# Step 1: Recupera il conteggio totale degli EcTrack
print_step "Recupero conteggio totale degli hiking routes..."

TRACK_COUNT=$(run_php_script "<?php
require_once 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\$trackModel = config('wm-package.ec_track_model', 'App\Models\EcTrack');
\$count = \$trackModel::count();
echo \$count;
")

if [ -z "$TRACK_COUNT" ] || [ "$TRACK_COUNT" -eq 0 ]; then
    print_warning "Nessun hiking route trovato nel database!"
    exit 0
fi

print_success "Trovati $TRACK_COUNT hiking routes da processare"
echo ""

# Step 2: Avviso se il numero è molto alto (modalità automatica, nessuna conferma)
if [ "$TRACK_COUNT" -gt 1000 ]; then
    print_warning "⚠️  ATTENZIONE: Ci sono $TRACK_COUNT hiking routes da processare!"
    print_warning "   Questo processo potrebbe richiedere molto tempo e risorse"
    print_warning "   Ogni track lancerà una catena di job (OSM, DEM, calcoli, AWS, etc.)"
    print_info "Modalità automatica: procedo senza conferma"
    echo ""
fi

# Step 3: Lancia initDataChain per tutti gli EcTrack
print_step "Lancio initDataChain per tutti gli hiking routes..."
print_info "Progress: processing tracks in batches..."
echo ""

# Script PHP che processa tutti i track
PHP_SCRIPT='<?php
require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

use Wm\WmPackage\Services\Models\EcTrackService;

$trackModel = config("wm-package.ec_track_model", "App\Models\EcTrack");
$service = EcTrackService::make();

$totalTracks = $trackModel::count();
$processedCount = 0;
$errorCount = 0;
$batchSize = 50;

echo "Inizio processamento di {$totalTracks} tracks...\n";

// Processa i track in batch per evitare problemi di memoria
$trackModel::chunk($batchSize, function ($tracks) use ($service, &$processedCount, &$errorCount, $totalTracks) {
    foreach ($tracks as $track) {
        try {
            // Lancia la catena di job per questo track
            $service->initDataChain($track);
            $processedCount++;
            
            // Progress ogni 10 tracks
            if ($processedCount % 10 === 0) {
                $percentage = round(($processedCount / $totalTracks) * 100, 1);
                echo "Progress: {$processedCount}/{$totalTracks} ({$percentage}%) - Track ID: {$track->id}\n";
            }
        } catch (Exception $e) {
            $errorCount++;
            echo "ERROR: Track ID {$track->id} - " . $e->getMessage() . "\n";
        }
    }
});

echo "\n";
echo "=== RIEPILOGO ===\n";
echo "Total tracks: {$totalTracks}\n";
echo "Processed: {$processedCount}\n";
echo "Errors: {$errorCount}\n";
echo "Success rate: " . round(($processedCount / $totalTracks) * 100, 1) . "%\n";

if ($errorCount > 0) {
    exit(1);
}
'

# Esegui lo script PHP
if [ "$IN_CONTAINER" = true ]; then
    echo "$PHP_SCRIPT" > /tmp/init_tracks_datachain.php
    if php /tmp/init_tracks_datachain.php; then
        RESULT_CODE=0
    else
        RESULT_CODE=$?
    fi
    rm /tmp/init_tracks_datachain.php
else
    if docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && cat > /tmp/init_tracks_datachain.php << 'PHPEOF'
$PHP_SCRIPT
PHPEOF
php /tmp/init_tracks_datachain.php
rm /tmp/init_tracks_datachain.php"; then
        RESULT_CODE=0
    else
        RESULT_CODE=$?
    fi
fi

echo ""
if [ $RESULT_CODE -eq 0 ]; then
    print_success "=== INIZIALIZZAZIONE DATA CHAIN COMPLETATA ==="
    print_info "Tutti i job sono stati accodati in Horizon"
    print_info "Puoi monitorare l'esecuzione attraverso:"
    print_info "  • Dashboard Horizon: http://localhost:8008/horizon"
    print_info "  • Logs Laravel: docker exec php81-osm2cai2 tail -f storage/logs/laravel.log"
    exit 0
else
    print_error "=== INIZIALIZZAZIONE DATA CHAIN COMPLETATA CON ERRORI ==="
    print_warning "Alcuni track non sono stati processati correttamente"
    print_info "Controlla i log per maggiori dettagli"
    exit 1
fi

