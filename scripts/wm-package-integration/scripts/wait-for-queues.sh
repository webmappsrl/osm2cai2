#!/bin/bash

# Script per attendere che tutte le code Laravel siano vuote
# Interroga direttamente Redis e Horizon per verificare lo stato
# Uso: ./scripts/wait-for-queues.sh [timeout_seconds] [check_interval_seconds]

set -e

# Configurazione
TIMEOUT=${1:-300}  # Default: 5 minuti
INTERVAL=${2:-5}   # Default: 5 secondi

# Determina la directory root del progetto e carica .env
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../" && pwd)"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzioni di utilità
print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Carica le variabili dal file .env
if [ -f "$PROJECT_ROOT/.env" ]; then
    set -o allexport
    source "$PROJECT_ROOT/.env"
    set +o allexport
else
    print_error "File .env non trovato nella root del progetto."
    exit 1
fi

# Definisci il nome del container Redis
if [ -z "$APP_NAME" ]; then
    print_error "La variabile APP_NAME non è definita nel file .env."
    exit 1
fi
REDIS_CONTAINER="redis-${APP_NAME}"

print_info "Attendo che le code Laravel siano vuote..."
print_info "Timeout: ${TIMEOUT}s, Intervallo controllo: ${INTERVAL}s"

# Code da monitorare
QUEUES=("default" "geometric-computations" "aws" "pbf" "geohub-import")

start_time=$(date +%s)
elapsed=0

while [ $elapsed -lt $TIMEOUT ]; do
    all_empty=true
    queue_status=""
    
    print_info "Controllo stato code (tempo trascorso: ${elapsed}s)..."
    
    for queue in "${QUEUES[@]}"; do
        # Controlla dimensione della coda da Redis usando il prefisso corretto
        queue_prefix="${APP_NAME}_database_queues:"
        redis_size=$(docker exec "$REDIS_CONTAINER" redis-cli llen "${queue_prefix}${queue}" 2>/dev/null || echo "0")
        delayed_size=$(docker exec "$REDIS_CONTAINER" redis-cli zcard "${queue_prefix}${queue}:delayed" 2>/dev/null || echo "0")
        reserved_size=$(docker exec "$REDIS_CONTAINER" redis-cli zcard "${queue_prefix}${queue}:reserved" 2>/dev/null || echo "0")
        total_redis=$((redis_size + delayed_size + reserved_size))
        
        # Controlla status Horizon usando il prefisso corretto
        horizon_prefix="${APP_NAME}_horizon:"
        horizon_metrics=$(docker exec "$REDIS_CONTAINER" redis-cli hgetall "${horizon_prefix}metrics" 2>/dev/null || echo "")
        horizon_pending=0
        horizon_running=0
        
        if [ -n "$horizon_metrics" ]; then
            # Estrai metriche per questa coda specifica
            queue_metrics=$(echo "$horizon_metrics" | grep -A 10 -B 10 "$queue" || echo "")
            if [ -n "$queue_metrics" ]; then
                # Parse JSON per estrarre pending e running
                pending=$(echo "$queue_metrics" | grep -o '"pending":[0-9]*' | head -1 | cut -d: -f2 || echo "0")
                running=$(echo "$queue_metrics" | grep -o '"running":[0-9]*' | head -1 | cut -d: -f2 || echo "0")
                horizon_pending=$((horizon_pending + pending))
                horizon_running=$((horizon_running + running))
            fi
        fi
        
        # Controlla anche i job in processing
        processing_size=$(docker exec "$REDIS_CONTAINER" redis-cli zcard "${horizon_prefix}processing" 2>/dev/null || echo "0")
        
        total_jobs=$((total_redis + horizon_pending + horizon_running + processing_size))
        
        if [ "$total_jobs" -gt 0 ]; then
            all_empty=false
            queue_status+="  $queue: ❌ Redis:$total_redis Horizon:P:$horizon_pending R:$horizon_running Processing:$processing_size\n"
        else
            queue_status+="  $queue: ✅ vuota\n"
        fi
    done
    
    # Mostra stato
    echo -e "$queue_status"
    
    if [ "$all_empty" = true ]; then
        print_success "Tutte le code sono vuote!"
        exit 0
    fi
    
    print_info "Attendo ${INTERVAL}s..."
    sleep $INTERVAL
    elapsed=$(($(date +%s) - start_time))
done

print_error "Timeout raggiunto (${TIMEOUT}s). Alcune code potrebbero non essere ancora vuote."
exit 1
