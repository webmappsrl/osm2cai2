#!/bin/bash

# Script per processare tutte le app: generazione icone AWS e file geojson POI
# Esegue writeIconsOnAws per ogni app e genera il file pois.geojson

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzione per stampare messaggi colorati
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verifica che siamo nel container PHP
if [ ! -f "/var/www/html/osm2cai2/artisan" ]; then
    print_error "Questo script deve essere eseguito nel container PHP!"
    print_info "Esegui: docker exec -it php81-osm2cai2 bash"
    exit 1
fi

print_info "🚀 Avvio processamento di tutte le app per icone AWS e geojson POI"
print_info "================================================================"

# Recupera tutte le app dal database
print_info "📋 Recupero lista delle app dal database..."
apps=$(php artisan tinker --execute="
\$apps = \Wm\WmPackage\Models\App::all(['id', 'name']);
foreach(\$apps as \$app) {
    echo \$app->id . '|' . \$app->name . PHP_EOL;
}
")

if [ -z "$apps" ]; then
    print_error "Nessuna app trovata nel database!"
    exit 1
fi

# Conta le app
app_count=$(echo "$apps" | wc -l)
print_info "📊 Trovate $app_count app da processare"

# Processa ogni app
success_count=0
error_count=0

# Crea un array temporaneo per le app
temp_file=$(mktemp)
echo "$apps" > "$temp_file"

while IFS='|' read -r app_id app_name; do
    if [ -z "$app_id" ] || [ -z "$app_name" ]; then
        continue
    fi
    
    print_info ""
    print_info "🔄 Processando App ID: $app_id - Nome: $app_name"
    print_info "------------------------------------------------"
    
    # 1. Genera icone AWS
    print_info "📱 Generazione icone AWS per App $app_id..."
    if php artisan tinker --execute="
        try {
            \$service = new \Wm\WmPackage\Services\AppIconsService();
            \$icons = \$service->writeIconsOnAws($app_id);
            echo 'SUCCESS: ' . count(\$icons) . ' icone generate' . PHP_EOL;
        } catch (Exception \$e) {
            echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
            exit(1);
        }
    " 2>/dev/null; then
        print_success "✅ Icone AWS generate per App $app_id"
    else
        print_error "❌ Errore nella generazione icone AWS per App $app_id"
        error_count=$((error_count + 1))
        continue
    fi
    
    # 2. Genera file geojson POI
    print_info "🗺️  Generazione file pois.geojson per App $app_id..."
    if php artisan app:build-pois-geojson "$app_id" 2>/dev/null; then
        print_success "✅ File pois.geojson generato per App $app_id"
    else
        print_error "❌ Errore nella generazione pois.geojson per App $app_id"
        error_count=$((error_count + 1))
        continue
    fi
    
    print_success "🎉 App $app_id ($app_name) processata con successo!"
    success_count=$((success_count + 1))
done < "$temp_file"

# Rimuovi file temporaneo
rm "$temp_file"

print_info ""
print_info "================================================================"
print_info "📊 RIEPILOGO PROCESSAMENTO"
print_info "================================================================"
print_success "✅ App processate con successo: $success_count"
if [ $error_count -gt 0 ]; then
    print_error "❌ App con errori: $error_count"
else
    print_success "🎉 Tutte le app sono state processate senza errori!"
fi

print_info ""
print_info "🏁 Processamento completato!"
