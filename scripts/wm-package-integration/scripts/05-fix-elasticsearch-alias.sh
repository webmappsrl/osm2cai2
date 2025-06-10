#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🔧 Fix Elasticsearch Alias ec_tracks -> hiking_routes"
echo "=================================================="

# Funzione per gestire errori
handle_error() {
    echo ""
    echo "❌ ERRORE: Script interrotto alla riga $1"
    echo "❌ Ultimo comando: $BASH_COMMAND"
    echo ""
    echo "🔧 Possibili soluzioni:"
    echo "   • Verifica che Elasticsearch sia attivo: curl -s localhost:9200"
    echo "   • Controlla i log Elasticsearch: docker logs elasticsearch_osm2cai2"
    echo "   • Verifica la configurazione Scout in .env"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Verifica se siamo nel container Docker
if [ ! -f "/var/www/html/osm2cai2/.env" ]; then
    print_error "Script deve essere eseguito dal container Docker PHP"
    echo "💡 Esegui: docker exec -u 0 -it php81_osm2cai2 bash"
    echo "💡 Poi: cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/05-fix-elasticsearch-alias.sh"
    exit 1
fi

cd /var/www/html/osm2cai2

# 1. Verifica stato attuale
print_step "Verifica stato attuale Elasticsearch..."

# Verifica connessione Elasticsearch
if ! curl -f -s elasticsearch:9200/_cluster/health > /dev/null; then
    print_error "Elasticsearch non raggiungibile"
    exit 1
fi

print_success "Elasticsearch raggiungibile"

# 2. Verifica indici hiking_routes esistenti
print_step "Ricerca indici hiking_routes..."
INDEX_NAME=$(curl -s 'elasticsearch:9200/_alias?pretty' | grep -B2 '"hiking_routes"' | grep -o 'hiking_routes_[0-9]*' | head -1)

if [ -z "$INDEX_NAME" ]; then
    print_warning "Nessun indice hiking_routes trovato"
    print_step "Creazione indice tramite indicizzazione..."
    
    # Esegui indicizzazione
    php -d max_execution_time=3600 -d memory_limit=2G artisan scout:import 'App\Models\HikingRoute'
    
    # Riprova a trovare l'indice
    sleep 5
    INDEX_NAME=$(curl -s 'elasticsearch:9200/_alias?pretty' | grep -B2 '"hiking_routes"' | grep -o 'hiking_routes_[0-9]*' | head -1)
    
    if [ -z "$INDEX_NAME" ]; then
        print_error "Impossibile creare indice hiking_routes"
        exit 1
    fi
fi

print_success "Indice trovato: $INDEX_NAME"

# 3. Verifica alias ec_tracks esistente
print_step "Verifica alias ec_tracks esistente..."
EXISTING_ALIAS=$(curl -s 'elasticsearch:9200/_alias/ec_tracks?pretty' | grep -o '"ec_tracks"' | head -1)

if [ ! -z "$EXISTING_ALIAS" ]; then
    print_warning "Alias ec_tracks già esistente, rimozione..."
    curl -X POST 'elasticsearch:9200/_aliases' -H 'Content-Type: application/json' -d '{"actions":[{"remove":{"index":"*","alias":"ec_tracks"}}]}'
    print_success "Alias esistente rimosso"
fi

# 4. Creazione nuovo alias
print_step "Creazione alias ec_tracks -> $INDEX_NAME..."
RESULT=$(curl -s -X POST 'elasticsearch:9200/_aliases' -H 'Content-Type: application/json' -d "{\"actions\":[{\"add\":{\"index\":\"$INDEX_NAME\",\"alias\":\"ec_tracks\"}}]}")

if echo "$RESULT" | grep -q '"acknowledged":true'; then
    print_success "Alias ec_tracks creato con successo"
else
    print_error "Errore nella creazione dell'alias: $RESULT"
    exit 1
fi

# 5. Verifica finale
print_step "Verifica finale..."

# Test alias
if curl -f -s 'elasticsearch:9200/ec_tracks/_search?size=0' > /dev/null; then
    print_success "Alias ec_tracks funzionante"
else
    print_error "Alias ec_tracks non funziona correttamente"
    exit 1
fi

# Mostra configurazione finale
echo ""
echo "📋 Configurazione finale:"
curl -X GET 'elasticsearch:9200/_alias?pretty' | grep -A5 -B5 "ec_tracks"

# Test conteggio
DOCS_COUNT=$(curl -s 'elasticsearch:9200/ec_tracks/_count' | grep -o '"count":[0-9]*' | grep -o '[0-9]*')
echo ""
print_success "Documenti indicizzati tramite alias ec_tracks: $DOCS_COUNT"

echo ""
print_success "🎉 Alias ec_tracks configurato correttamente!"
echo ""
echo "🔍 Test API:"
echo "   curl \"http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1\"" 