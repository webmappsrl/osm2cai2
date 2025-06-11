#!/bin/bash

echo "🔍 Test API Elasticsearch OSM2CAI2"
echo "=================================="

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

# Test 1: Connessione Elasticsearch
print_step "Test 1: Connessione Elasticsearch..."
if curl -f -s localhost:9200 > /dev/null; then
    print_success "Elasticsearch raggiungibile"
else
    print_error "Elasticsearch non raggiungibile"
    exit 1
fi

# Test 2: Stato cluster
print_step "Test 2: Stato cluster..."
CLUSTER_STATUS=$(curl -s localhost:9200/_cluster/health | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
echo "Stato cluster: $CLUSTER_STATUS"

if [ "$CLUSTER_STATUS" = "green" ]; then
    print_success "Cluster in stato green"
elif [ "$CLUSTER_STATUS" = "yellow" ]; then
    print_warning "Cluster in stato yellow (normale per single-node)"
else
    print_error "Cluster in stato red"
fi

# Test 3: Indici hiking_routes
print_step "Test 3: Indici hiking_routes..."
INDICES=$(curl -s localhost:9200/_cat/indices | grep hiking_routes)
if [ ! -z "$INDICES" ]; then
    print_success "Indici hiking_routes trovati:"
    echo "$INDICES"
else
    print_error "Nessun indice hiking_routes trovato"
fi

# Test 4: Alias ec_tracks
print_step "Test 4: Alias ec_tracks..."
ALIAS_STATUS=$(curl -s localhost:9200/_alias/ec_tracks)
if echo "$ALIAS_STATUS" | grep -q "hiking_routes"; then
    print_success "Alias ec_tracks configurato correttamente"
    INDEX_NAME=$(echo "$ALIAS_STATUS" | grep -o 'hiking_routes_[0-9]*' | head -1)
    echo "Punta all'indice: $INDEX_NAME"
else
    print_error "Alias ec_tracks non configurato"
fi

# Test 5: Conteggio documenti
print_step "Test 5: Conteggio documenti..."
DOC_COUNT=$(curl -s localhost:9200/ec_tracks/_count 2>/dev/null | grep -o '"count":[0-9]*' | grep -o '[0-9]*')
if [ ! -z "$DOC_COUNT" ]; then
    print_success "Documenti indicizzati: $DOC_COUNT"
else
    print_error "Impossibile contare i documenti"
fi

# Test 6: API OSM2CAI2
print_step "Test 6: API OSM2CAI2..."
API_RESPONSE=$(curl -s "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1" 2>/dev/null | head -c 100)
if echo "$API_RESPONSE" | grep -q "id"; then
    print_success "API OSM2CAI2 funzionante"
    echo "Prima parte della risposta: ${API_RESPONSE}..."
else
    print_error "API OSM2CAI2 non funzionante"
    echo "Risposta ricevuta: $API_RESPONSE"
fi

echo ""
echo "📊 Riepilogo configurazione:"
curl -s localhost:9200/_cluster/health?pretty | grep -E "(status|number_of_nodes|active_shards|unassigned_shards)"

echo ""
print_success "Test completato!"
echo ""
echo "🔧 Se ci sono problemi, esegui:"
echo "   docker exec php81_osm2cai2 ./scripts/wm-package-integration/scripts/05-fix-elasticsearch-alias.sh" 