#!/bin/bash

# Script per aggiornare solo le variabili d'ambiente per l'integrazione WMPackage
# Uso: ./scripts/update-env-variables.sh

# Abilita strict mode: ferma lo script in caso di errore
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

print_success() {
    echo -e "${GREEN}✅${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠️${NC} $1"
}

print_error() {
    echo -e "${RED}❌${NC} $1"
}

# Determina la directory root del progetto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../" && pwd)"

print_step "=== AGGIORNAMENTO VARIABILI D'AMBIENTE WMPACKAGE ==="
print_step "Directory progetto: $PROJECT_ROOT"

# Verifica che il file .env esista
if [ ! -f "$PROJECT_ROOT/.env" ]; then
    print_error "File .env non trovato in $PROJECT_ROOT"
    exit 1
fi

print_step "File .env trovato: $PROJECT_ROOT/.env"

# Funzione per aggiornare o aggiungere una variabile nel file .env
update_env_variable() {
    local var_name="$1"
    local var_value="$2"
    local env_file="$PROJECT_ROOT/.env"
    
    if grep -q "^${var_name}=" "$env_file"; then
        # Variabile esistente, controlla se il valore è diverso
        current_value=$(grep "^${var_name}=" "$env_file" | cut -d'=' -f2-)
        if [ "$current_value" != "$var_value" ]; then
            # Valore diverso, aggiorna
            while IFS= read -r line; do
                if [[ "$line" =~ ^${var_name}= ]]; then
                    echo "${var_name}=${var_value}"
                else
                    echo "$line"
                fi
            done < "$env_file" > "$env_file.tmp" && mv "$env_file.tmp" "$env_file"
            print_step "   ✅ Aggiornata: ${var_name}=${var_value}"
        else
            # Valore già corretto
            print_step "   ⏭️  Già corretta: ${var_name}=${var_value}"
        fi
    else
        # Variabile non esistente, aggiungi alla fine del file
        echo "${var_name}=${var_value}" >> "$env_file"
        print_step "   ✅ Aggiunta: ${var_name}=${var_value}"
    fi
}

print_step "Aggiornamento variabili .env per integrazione WMPackage..."

# Aggiorna le variabili Geohub
print_step "Configurazione variabili Geohub..."
update_env_variable "GEOHUB_DB_HOST" "138.201.184.211"
update_env_variable "GEOHUB_DB_DATABASE" "geohub"
update_env_variable "GEOHUB_DB_USERNAME" "root"
update_env_variable "GEOHUB_DB_PASSWORD" "root"
update_env_variable "GEOHUB_DB_PORT" "5432"

# Aggiorna le variabili EC Track
print_step "Configurazione variabili EC Track..."
update_env_variable "EC_TRACK_TABLE" "hiking_routes"
update_env_variable "EC_TRACK_MODEL" "App\\Models\\HikingRoute"

# Aggiorna le variabili Laravel Scout (Elasticsearch)
print_step "Configurazione variabili Laravel Scout..."
update_env_variable "SCOUT_DRIVER" "Matchish\ScoutElasticSearch\\Engines\ElasticSearchEngine"
update_env_variable "ELASTICSEARCH_HOST" "elasticsearch:9200"

# Rimuovi il file di backup se esiste
if [ -f "$PROJECT_ROOT/.env.bak" ]; then
    rm "$PROJECT_ROOT/.env.bak"
    print_step "   🧹 File di backup rimosso"
fi

print_success "File .env aggiornato con successo"

# Verifica finale delle variabili
print_step "Verifica finale delle variabili aggiornate..."
echo ""
echo "📋 Variabili aggiornate:"
echo "   • GEOHUB_DB_HOST=138.201.184.211"
echo "   • GEOHUB_DB_DATABASE=geohub"
echo "   • GEOHUB_DB_USERNAME=root"
echo "   • GEOHUB_DB_PASSWORD=root"
echo "   • GEOHUB_DB_PORT=5432"
echo "   • EC_TRACK_TABLE=hiking_routes"
echo "   • EC_TRACK_MODEL=App\\Models\\HikingRoute"
echo "   • SCOUT_DRIVER=Matchish\\ScoutElasticSearch\\Engines\\ElasticSearchEngine"
echo "   • ELASTICSEARCH_HOST=elasticsearch:9200"
echo ""

print_success "=== AGGIORNAMENTO COMPLETATO ==="
print_step "🌐 Per applicare le modifiche, esegui:"
print_step "   docker exec php81-osm2cai2uat php artisan config:clear"
print_step "   docker exec php81-osm2cai2uat php artisan cache:clear"
echo ""
print_step "📁 File .env aggiornato: $PROJECT_ROOT/.env"
