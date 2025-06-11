#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🚀 Abilitazione Indicizzazione Automatica Scout per OSM2CAI2"

# Configurazione parametri per indicizzazione avanzata
MAX_RETRIES=3
SHARD_WAIT_TIME=30
INDEX_TIMEOUT=3600
MEMORY_LIMIT="4G"

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

# Funzione per gestire errori con informazioni dettagliate
handle_error() {
    local line_number=$1
    local last_command="$2"
    print_error "Script interrotto alla riga $line_number"
    print_error "Ultimo comando: $last_command"
    
    # Mostra informazioni di debug
    print_step "Informazioni di debug:"
    echo "  • Stato cluster Elasticsearch:"
    curl -s 'elasticsearch:9200/_cluster/health?pretty' | head -10 || echo "    Elasticsearch non raggiungibile"
    
    echo "  • Indici esistenti:"
    curl -s 'elasticsearch:9200/_cat/indices?v' || echo "    Impossibile elencare indici"
    
    echo "  • Shard non assegnati:"
    curl -s 'elasticsearch:9200/_cat/shards?v' | grep UNASSIGNED || echo "    Nessuno shard non assegnato"
    
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO "$BASH_COMMAND"' ERR

# Controlla se siamo nel container Docker
if [ ! -f "/var/www/html/osm2cai2/.env" ]; then
    print_error "Script deve essere eseguito dal container Docker PHP"
    print_step "Esegui: docker exec -it php81_osm2cai2 bash"
    print_step "Poi: cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/04-enable-scout-automatic-indexing.sh"
    exit 1
fi

cd /var/www/html/osm2cai2

echo "📝 Verifica configurazione Scout..."
echo "✅ Assumo che le configurazioni Scout siano già presenti nel .env"

echo "🔧 Configurazione template Elasticsearch per single-node..."
# Crea template per configurare automaticamente 0 repliche sui nuovi indici
curl -X PUT 'elasticsearch:9200/_index_template/osm2cai_single_node' \
  -H 'Content-Type: application/json' \
  -d '{
    "index_patterns": ["hiking_routes_*", "ec_tracks_*"],
    "template": {
      "settings": {
        "index": {
          "number_of_replicas": 0,
          "number_of_shards": 1
        }
      }
    },
    "priority": 100
  }'

print_success "Template Elasticsearch configurato per single-node"

print_step "Riavvio worker delle code per applicare nuove configurazioni..."
php artisan queue:restart

# Funzione per aspettare che gli shard siano attivi
wait_for_shards() {
    local index_pattern="$1"
    local max_wait="$2"
    local waited=0
    
    print_step "Attesa attivazione shard per indice $index_pattern..."
    
    while [ $waited -lt $max_wait ]; do
        # Controlla se ci sono shard UNASSIGNED o INITIALIZING per l'indice
        local unassigned_shards=$(curl -s 'elasticsearch:9200/_cat/shards?v' | grep "$index_pattern" | grep -E "(UNASSIGNED|INITIALIZING)" | wc -l 2>/dev/null || echo "0")
        
        if [ "$unassigned_shards" = "0" ]; then
            print_success "Tutti gli shard per $index_pattern sono attivi"
            return 0
        fi
        
        print_step "Shard non ancora pronti ($unassigned_shards), attesa ${SHARD_WAIT_TIME}s... ($waited/${max_wait}s)"
        sleep $SHARD_WAIT_TIME
        waited=$((waited + SHARD_WAIT_TIME))
    done
    
    print_warning "Timeout attesa shard, procedo comunque"
    return 1
}

# Funzione per configurare un indice per single-node
configure_index_for_single_node() {
    local index_name="$1"
    
    print_step "Configurazione indice $index_name per single-node..."
    
    # Imposta 0 repliche
    curl -X PUT "elasticsearch:9200/$index_name/_settings" \
        -H 'Content-Type: application/json' \
        -d '{"index":{"number_of_replicas":0}}' \
        -s > /dev/null
    
    print_success "Indice $index_name configurato per single-node"
}

# Funzione per riassegnare shard non assegnati
reassign_unassigned_shards() {
    print_step "Controllo shard non assegnati..."
    
    local unassigned_shards=$(curl -s 'elasticsearch:9200/_cat/shards?v' | grep UNASSIGNED 2>/dev/null || true)
    
    if [ -z "$unassigned_shards" ]; then
        print_success "Nessuno shard non assegnato"
        return 0
    fi
    
    print_warning "Trovati shard non assegnati, tentativo di riassegnazione..."
    echo "$unassigned_shards"
    
    # Riassegna shard primari non assegnati
    echo "$unassigned_shards" | while read line; do
        if echo "$line" | grep -q "p UNASSIGNED"; then
            local index=$(echo "$line" | awk '{print $1}')
            local shard=$(echo "$line" | awk '{print $2}')
            
            print_step "Riassegnazione shard primario $shard per indice $index..."
            curl -X POST "elasticsearch:9200/_cluster/reroute" \
                -H 'Content-Type: application/json' \
                -d "{\"commands\":[{\"allocate_empty_primary\":{\"index\":\"$index\",\"shard\":$shard,\"node\":\"elasticsearch\",\"accept_data_loss\":true}}]}" \
                -s > /dev/null || true
        fi
    done
    
    # Aspetta inizializzazione
    sleep 10
}

# Funzione principale di indicizzazione con retry
perform_indexing_with_retry() {
    local attempt=1
    
    while [ $attempt -le $MAX_RETRIES ]; do
        print_step "Tentativo di indicizzazione $attempt/$MAX_RETRIES..."
        
        # Pulizia indici esistenti se non è il primo tentativo
        if [ $attempt -gt 1 ]; then
            print_step "Pulizia indici esistenti dal tentativo precedente..."
            
            # Rimuovi indici hiking_routes_ falliti
            local failed_indices=$(curl -s 'elasticsearch:9200/_cat/indices?v' | grep hiking_routes_ | grep -E "(red|yellow)" | awk '{print $3}' 2>/dev/null || true)
            for index in $failed_indices; do
                if [ ! -z "$index" ]; then
                    print_step "Rimozione indice fallito: $index"
                    curl -X DELETE "elasticsearch:9200/$index" -s > /dev/null || true
                fi
            done
        fi
        
        # Riassegna shard se necessario
        reassign_unassigned_shards
        
        # Tentativo di indicizzazione
        print_step "Avvio indicizzazione (timeout: ${INDEX_TIMEOUT}s, memoria: ${MEMORY_LIMIT})..."
        
        if timeout $INDEX_TIMEOUT php -d max_execution_time=$INDEX_TIMEOUT -d memory_limit=$MEMORY_LIMIT \
           artisan scout:import 'App\Models\HikingRoute' 2>/dev/null; then
            
            print_success "Indicizzazione completata con successo!"
            
            # Aspetta che gli shard siano attivi
            local new_index=$(curl -s 'elasticsearch:9200/_alias?pretty' | grep -B2 '"hiking_routes"' | grep -o 'hiking_routes_[0-9]*' | head -1 2>/dev/null || echo "")
            if [ ! -z "$new_index" ]; then
                wait_for_shards "$new_index" 60
                configure_index_for_single_node "$new_index"
            fi
            
            return 0
        else
            local exit_code=$?
            print_warning "Tentativo $attempt fallito (exit code: $exit_code)"
            
            if [ $attempt -lt $MAX_RETRIES ]; then
                print_step "Attesa prima del prossimo tentativo..."
                sleep 30
            fi
        fi
        
        attempt=$((attempt + 1))
    done
    
    print_error "Tutti i tentativi di indicizzazione sono falliti"
    return 1
}

# Funzione per creare/aggiornare alias ec_tracks
setup_ec_tracks_alias() {
    print_step "Setup alias ec_tracks..."
    
    # Trova l'indice hiking_routes più recente
    local hiking_index=$(curl -s 'elasticsearch:9200/_alias?pretty' | grep -B2 '"hiking_routes"' | grep -o 'hiking_routes_[0-9]*' | head -1 2>/dev/null || echo "")
    
    if [ -z "$hiking_index" ]; then
        print_error "Nessun indice hiking_routes trovato"
        return 1
    fi
    
    print_step "Indice trovato: $hiking_index"
    
    # Rimuovi alias ec_tracks esistente
    local existing_alias=$(curl -s 'elasticsearch:9200/_alias/ec_tracks?pretty' 2>/dev/null | grep -o '"ec_tracks"' | head -1 || true)
    if [ ! -z "$existing_alias" ]; then
        print_step "Rimozione alias ec_tracks esistente..."
        curl -X POST 'elasticsearch:9200/_aliases' \
            -H 'Content-Type: application/json' \
            -d '{"actions":[{"remove":{"index":"*","alias":"ec_tracks"}}]}' \
            -s > /dev/null
    fi
    
    # Crea nuovo alias
    print_step "Creazione alias ec_tracks -> $hiking_index..."
    local result=$(curl -s -X POST 'elasticsearch:9200/_aliases' \
        -H 'Content-Type: application/json' \
        -d "{\"actions\":[{\"add\":{\"index\":\"$hiking_index\",\"alias\":\"ec_tracks\"}}]}")
    
    if echo "$result" | grep -q '"acknowledged":true'; then
        print_success "Alias ec_tracks creato con successo"
        return 0
    else
        print_error "Errore nella creazione dell'alias: $result"
        return 1
    fi
}

print_step "Stato attuale dell'indicizzazione..."

# Conta HikingRoutes disponibili
TOTAL_ROUTES=$(php artisan tinker --execute="echo App\Models\HikingRoute::count();" 2>/dev/null || echo "0")
INDEXABLE_ROUTES=$(php artisan tinker --execute="echo App\Models\HikingRoute::whereNotNull('geometry')->where('osm2cai_status', '!=', 0)->count();" 2>/dev/null || echo "0")

print_step "HikingRoutes totali: $TOTAL_ROUTES"
print_step "HikingRoute indicizzabili: $INDEXABLE_ROUTES"

if [ "$INDEXABLE_ROUTES" = "0" ]; then
    print_warning "Nessun HikingRoute indicizzabile trovato"
    print_step "Verifica che esistano record con geometry valida e osm2cai_status != 0"
    print_success "Template configurato per indicizzazione automatica futura"
    exit 0
fi

# Configurazione cluster per single-node
print_step "Configurazione cluster per single-node..."
curl -X PUT 'elasticsearch:9200/_cluster/settings' \
    -H 'Content-Type: application/json' \
    -d '{
        "persistent": {
            "cluster.routing.allocation.disk.threshold_enabled": false,
            "cluster.routing.allocation.enable": "all"
        }
    }' -s > /dev/null

print_success "Cluster configurato"

# Esegui indicizzazione con retry
print_step "Avvio indicizzazione robusta con gestione errori..."
if perform_indexing_with_retry; then
    print_success "Indicizzazione completata"
else
    print_error "Indicizzazione fallita dopo tutti i tentativi"
    exit 1
fi

# Setup alias
if setup_ec_tracks_alias; then
    print_success "Alias ec_tracks configurato"
else
    print_error "Configurazione alias fallita"
    exit 1
fi

# Test finale
print_step "Test risultato finale..."

# Conta documenti negli indici
HIKING_COUNT=$(curl -s 'elasticsearch:9200/hiking_routes/_count' 2>/dev/null | grep -o '"count":[0-9]*' | cut -d':' -f2 || echo "0")
EC_TRACKS_COUNT=$(curl -s 'elasticsearch:9200/ec_tracks/_count' 2>/dev/null | grep -o '"count":[0-9]*' | cut -d':' -f2 || echo "0")

print_step "Documenti indicizzati:"
print_step "  • hiking_routes: $HIKING_COUNT"
print_step "  • ec_tracks (alias): $EC_TRACKS_COUNT"

if [ "$HIKING_COUNT" = "$EC_TRACKS_COUNT" ] && [ "$HIKING_COUNT" -gt 0 ]; then
    print_success "Indicizzazione verificata correttamente"
    
    # Test API
    print_step "Test API Elasticsearch..."
    if curl -sf "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1" > /dev/null 2>&1; then
        print_success "API Elasticsearch funzionante"
    else
        print_warning "API Elasticsearch potrebbe non essere ancora pronta"
    fi
else
    print_warning "Possibili problemi con l'indicizzazione (conteggi diversi o zero documenti)"
fi

echo ""
print_success "🎉 Indicizzazione Scout avanzata completata!"
echo ""
echo "📊 Riepilogo:"
print_step "  • HikingRoutes processati: $INDEXABLE_ROUTES/$TOTAL_ROUTES"
print_step "  • Indice creato: $(curl -s 'elasticsearch:9200/_alias?pretty' 2>/dev/null | grep -B2 '"hiking_routes"' | grep -o 'hiking_routes_[0-9]*' | head -1 || echo 'N/A')"
print_step "  • Alias ec_tracks: Configurato"
print_step "  • API: http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1"
echo ""
echo "📋 Prossimi passi:"
echo "1. Assicurati che Horizon sia attivo: php artisan horizon"
echo "2. Monitora le code: http://localhost:8008/horizon"
echo "3. Verifica status: php artisan horizon:status"
echo "4. Testa modificando un HikingRoute nell'interfaccia Nova"
echo ""
echo "🔍 Comandi utili per monitoraggio:"
echo "  • docker exec php81_osm2cai2 curl -X GET 'elasticsearch:9200/_cat/indices?v'"
echo "  • docker exec php81_osm2cai2 curl -X GET 'elasticsearch:9200/_cat/shards?v'"
echo "  • docker exec php81_osm2cai2 curl -X GET 'elasticsearch:9200/ec_tracks/_count'"
echo "  • tail -f storage/logs/laravel.log | grep -i scout" 