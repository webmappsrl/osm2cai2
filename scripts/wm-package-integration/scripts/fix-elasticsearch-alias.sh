#!/bin/bash

# Fix Elasticsearch Alias per OSM2CAI2
# Risolve problemi di alias per indicizzazione unificata

set -e

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

echo "🔧 Fix Elasticsearch Alias per OSM2CAI2"
echo "======================================="

# 1. Verifica connessione Elasticsearch
print_step "Verifica connessione Elasticsearch..."
if ! curl -f -s 'elasticsearch:9200/_cluster/health' > /dev/null; then
    print_error "Elasticsearch non raggiungibile!"
    exit 1
fi
print_success "Elasticsearch raggiungibile"

# 2. Verifica indici esistenti
print_step "Verifica indici hiking_routes..."
INDICES=$(curl -s 'elasticsearch:9200/_cat/indices?v' | grep hiking_routes || echo "")

if [ -z "$INDICES" ]; then
    print_error "Nessun indice hiking_routes trovato!"
    exit 1
fi

echo "📋 Indici hiking_routes trovati:"
echo "$INDICES"

# 3. Trova l'indice più recente con più documenti
print_step "Identificazione indice principale..."
LATEST_INDEX=$(curl -s 'elasticsearch:9200/_cat/indices?v' | grep hiking_routes | sort -k5 -nr | head -1 | awk '{print $3}')

if [ -z "$LATEST_INDEX" ]; then
    print_error "Impossibile identificare l'indice principale!"
    exit 1
fi

print_success "Indice principale: $LATEST_INDEX"

# 4. Verifica documenti nell'indice
DOC_COUNT=$(curl -s "elasticsearch:9200/$LATEST_INDEX/_count" | grep -o '"count":[0-9]*' | cut -d: -f2)
print_step "Documenti nell'indice: $DOC_COUNT"

if [ "$DOC_COUNT" -eq 0 ]; then
    print_warning "L'indice è vuoto!"
fi

# 5. Verifica alias corrente
print_step "Verifica alias corrente..."
CURRENT_ALIAS=$(curl -s 'elasticsearch:9200/_cat/aliases?v' | grep hiking_routes || echo "")

if [ -n "$CURRENT_ALIAS" ]; then
    echo "📋 Alias corrente:"
    echo "$CURRENT_ALIAS"
else
    print_warning "Nessun alias hiking_routes trovato"
fi

# 6. Rimuovi tutti gli alias esistenti e ricrea
print_step "Ricreazione alias hiking_routes..."

# Rimuovi tutti gli alias hiking_routes
curl -X POST 'elasticsearch:9200/_aliases' \
  -H 'Content-Type: application/json' \
  -d '{
    "actions": [
      {"remove": {"index": "*", "alias": "hiking_routes"}}
    ]
  }' > /dev/null 2>&1 || true

# Aggiungi alias al nuovo indice
curl -X POST 'elasticsearch:9200/_aliases' \
  -H 'Content-Type: application/json' \
  -d "{
    \"actions\": [
      {\"add\": {\"index\": \"$LATEST_INDEX\", \"alias\": \"hiking_routes\", \"is_write_index\": true}}
    ]
  }"

if [ $? -eq 0 ]; then
    print_success "Alias ricreato con successo"
else
    print_error "Errore nella creazione dell'alias"
    exit 1
fi

# 7. Verifica finale
print_step "Verifica finale alias..."
FINAL_COUNT=$(curl -s 'elasticsearch:9200/hiking_routes/_count' | grep -o '"count":[0-9]*' | cut -d: -f2)

if [ "$FINAL_COUNT" -gt 0 ]; then
    print_success "✅ Alias funzionante: $FINAL_COUNT documenti"
else
    print_warning "⚠️ Alias creato ma 0 documenti (potrebbe essere normale se l'indice è vuoto)"
fi

# 8. Verifica API
print_step "Test API Elasticsearch..."
API_TEST=$(curl -s 'elasticsearch:9200/hiking_routes/_search?size=1' | grep -o '"total":{"value":[0-9]*' | cut -d: -f3 || echo "0")

if [ "$API_TEST" -gt 0 ]; then
    print_success "✅ API Elasticsearch funzionante"
else
    print_warning "⚠️ API restituisce 0 risultati (verificare se l'indice è vuoto)"
fi

echo ""
print_success "🎉 Fix Elasticsearch Alias completato!"
echo "📊 Statistiche finali:"
echo "   • Indice principale: $LATEST_INDEX"
echo "   • Documenti totali: $FINAL_COUNT"
echo "   • Alias: hiking_routes"
echo "   • Write index: abilitato"
echo ""
echo "🔗 Test API:"
echo "   curl 'http://localhost:8008/api/v2/elasticsearch?app=geohub_app_2'"
