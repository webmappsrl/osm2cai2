#!/bin/bash

# Script per generare PBF ottimizzati per tutte le App
# Recupera automaticamente tutte le App dal database e lancia pbf:generate --optimized per ciascuna

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

print_step "=== GENERAZIONE PBF OTTIMIZZATI ==="
print_info "Recupero di tutte le App dal database..."
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

# Funzione per eseguire script PHP
run_php_script() {
    local script_content="$1"
    if [ "$IN_CONTAINER" = true ]; then
        echo "$script_content" > /tmp/get_apps.php
        php /tmp/get_apps.php
        rm /tmp/get_apps.php
    else
        docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && cat > /tmp/get_apps.php << 'PHPEOF'
$script_content
PHPEOF
php /tmp/get_apps.php
rm /tmp/get_apps.php"
    fi
}

# Recupera tutte le App dal database
APP_DATA=$(run_php_script "<?php
require_once 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\$appModel = config('wm-package.app_model', 'Wm\WmPackage\Models\App');
\$apps = \$appModel::all();

if (\$apps->isEmpty()) {
    echo '';
    exit(0);
}

\$ids = [];
\$names = [];
foreach (\$apps as \$appInstance) {
    \$ids[] = \$appInstance->id;
    \$names[] = \$appInstance->name ?? 'N/A';
}

echo json_encode(['ids' => \$ids, 'names' => \$names, 'count' => count(\$ids)]);
")

if [ -z "$APP_DATA" ]; then
    print_warning "Nessuna App trovata nel database!"
    exit 0
fi

# Parse JSON response
APP_COUNT=$(echo "$APP_DATA" | php -r "\$data = json_decode(file_get_contents('php://stdin'), true); echo \$data['count'] ?? 0;")

if [ "$APP_COUNT" -eq 0 ]; then
    print_warning "Nessuna App trovata nel database!"
    exit 0
fi

# Converti direttamente il JSON in array di ID
IFS=' ' read -r -a APP_IDS <<< "$(echo "$APP_DATA" | php -r "\$data = json_decode(file_get_contents('php://stdin'), true); echo implode(' ', \$data['ids'] ?? []);")"

print_success "Trovate $APP_COUNT App da processare"
print_info "App IDs: ${APP_IDS[*]}"
echo ""


# Contatori per statistiche
success_count=0
error_count=0
total_apps=${#APP_IDS[@]}

# Processa ogni app
for app_id in "${APP_IDS[@]}"; do
    print_step "=================================================="
    print_step "Processando App ID: $app_id"
    print_step "=================================================="
    echo ""
    
    # Lancia la generazione PBF ottimizzata
    print_info "🚀 Lancio generazione PBF ottimizzati per App $app_id..."
    print_info "Comando: php artisan pbf:generate $app_id --optimized"
    print_warning "Questo processo può richiedere diversi minuti..."
    echo ""
    
    if [ "$IN_CONTAINER" = true ]; then
        if php artisan pbf:generate "$app_id" --optimized; then
            print_success "✅ PBF generati con successo per App $app_id"
            success_count=$((success_count + 1))
        else
            print_error "❌ Errore durante la generazione PBF per App $app_id"
            error_count=$((error_count + 1))
        fi
    else
        if docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan pbf:generate $app_id --optimized"; then
            print_success "✅ PBF generati con successo per App $app_id"
            success_count=$((success_count + 1))
        else
            print_error "❌ Errore durante la generazione PBF per App $app_id"
            error_count=$((error_count + 1))
        fi
    fi
    
    echo ""
done

# Riepilogo finale
echo ""
print_step "================================================================"
print_step "📊 RIEPILOGO GENERAZIONE PBF"
print_step "================================================================"
print_info "Totale app processate: $total_apps"
print_success "✅ App con successo: $success_count"

if [ $error_count -gt 0 ]; then
    print_error "❌ App con errori: $error_count"
else
    print_success "🎉 Tutte le app sono state processate con successo!"
fi

echo ""

if [ $error_count -gt 0 ]; then
    print_warning "=== GENERAZIONE PBF COMPLETATA CON ERRORI ==="
    print_info "Alcune app non sono state processate correttamente"
    exit 1
else
    print_success "=== GENERAZIONE PBF COMPLETATA CON SUCCESSO ==="
    print_info "I file PBF sono stati generati e caricati su AWS"
    exit 0
fi

