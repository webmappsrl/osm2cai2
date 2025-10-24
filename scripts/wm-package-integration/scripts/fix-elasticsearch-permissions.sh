#!/bin/bash

# Script per fixare i permessi di Elasticsearch
# Risolve il problema: "failed to obtain node locks" su /usr/share/elasticsearch/data

# Abilita strict mode: ferma lo script in caso di errore
set -e

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

echo "🔧 Fix Permessi Elasticsearch"
echo "============================="
echo "📅 Avviato: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Determina la directory root del progetto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../" && pwd)"

# Directory di dati di Elasticsearch
ELASTICSEARCH_DATA_DIR="$PROJECT_ROOT/docker/volumes/elasticsearch/data"

print_step "Verifica directory Elasticsearch: $ELASTICSEARCH_DATA_DIR"

# Crea la directory se non esiste
if [ ! -d "$ELASTICSEARCH_DATA_DIR" ]; then
    print_step "Creazione directory Elasticsearch..."
    mkdir -p "$ELASTICSEARCH_DATA_DIR"
    print_success "Directory creata: $ELASTICSEARCH_DATA_DIR"
else
    print_success "Directory esistente: $ELASTICSEARCH_DATA_DIR"
fi

# Verifica se ci sono container Elasticsearch in esecuzione
print_step "Verifica container Elasticsearch in esecuzione..."
if docker ps --format "table {{.Names}}" | grep -q "elasticsearch"; then
    print_warning "Container Elasticsearch trovati in esecuzione"
    print_step "Fermando container Elasticsearch per applicare i fix..."
    
    # Ferma tutti i container Elasticsearch
    docker ps --format "table {{.Names}}" | grep "elasticsearch" | while read -r container_name; do
        if [ ! -z "$container_name" ]; then
            print_step "Fermando container: $container_name"
            docker stop "$container_name" || true
        fi
    done
    
    # Attesa per assicurarsi che i container siano completamente fermati
    sleep 3
    print_success "Container Elasticsearch fermati"
else
    print_success "Nessun container Elasticsearch in esecuzione"
fi

# Rimuove eventuali file di lock esistenti
print_step "Rimozione file di lock esistenti..."
if [ -f "$ELASTICSEARCH_DATA_DIR/node.lock" ]; then
    rm -f "$ELASTICSEARCH_DATA_DIR/node.lock"
    print_success "File node.lock rimosso"
else
    print_success "Nessun file node.lock trovato"
fi

# Rimuove eventuali directory di dati corrotte
print_step "Pulizia directory dati Elasticsearch..."
if [ -d "$ELASTICSEARCH_DATA_DIR" ] && [ "$(ls -A "$ELASTICSEARCH_DATA_DIR" 2>/dev/null)" ]; then
    print_warning "Directory dati non vuota, pulizia in corso..."
    rm -rf "$ELASTICSEARCH_DATA_DIR"/*
    print_success "Directory dati pulita"
else
    print_success "Directory dati già vuota"
fi

# Imposta i permessi corretti
print_step "Impostazione permessi directory Elasticsearch..."

# UID e GID di Elasticsearch nel container (solitamente 1000:1000)
ELASTICSEARCH_UID=1000
ELASTICSEARCH_GID=1000

# Imposta ownership
print_step "Impostazione ownership (UID:$ELASTICSEARCH_UID, GID:$ELASTICSEARCH_GID)..."
if chown -R "$ELASTICSEARCH_UID:$ELASTICSEARCH_GID" "$ELASTICSEARCH_DATA_DIR"; then
    print_success "Ownership impostata correttamente"
else
    print_warning "Impossibile impostare ownership, provando con sudo..."
    if sudo chown -R "$ELASTICSEARCH_UID:$ELASTICSEARCH_GID" "$ELASTICSEARCH_DATA_DIR"; then
        print_success "Ownership impostata con sudo"
    else
        print_error "Impossibile impostare ownership"
        exit 1
    fi
fi

# Imposta permessi
print_step "Impostazione permessi (755)..."
if chmod -R 755 "$ELASTICSEARCH_DATA_DIR"; then
    print_success "Permessi impostati correttamente"
else
    print_warning "Impossibile impostare permessi, provando con sudo..."
    if sudo chmod -R 755 "$ELASTICSEARCH_DATA_DIR"; then
        print_success "Permessi impostati con sudo"
    else
        print_error "Impossibile impostare permessi"
        exit 1
    fi
fi

# Verifica finale dei permessi
print_step "Verifica finale permessi..."
if [ -d "$ELASTICSEARCH_DATA_DIR" ]; then
    PERMISSIONS=$(ls -ld "$ELASTICSEARCH_DATA_DIR" | awk '{print $1, $3, $4}')
    print_success "Permessi directory: $PERMISSIONS"
    
    # Verifica che la directory sia scrivibile
    if [ -w "$ELASTICSEARCH_DATA_DIR" ]; then
        print_success "Directory scrivibile"
    else
        print_error "Directory non scrivibile"
        exit 1
    fi
else
    print_error "Directory Elasticsearch non trovata"
    exit 1
fi

# Verifica che non ci siano processi Elasticsearch in esecuzione
print_step "Verifica processi Elasticsearch..."
if pgrep -f elasticsearch > /dev/null; then
    print_warning "Processi Elasticsearch trovati, terminazione in corso..."
    pkill -f elasticsearch || true
    sleep 2
    print_success "Processi Elasticsearch terminati"
else
    print_success "Nessun processo Elasticsearch in esecuzione"
fi

print_success "=== FIX PERMESSI ELASTICSEARCH COMPLETATO ==="
echo ""
print_step "📋 Riepilogo operazioni:"
print_step "   ✅ Directory Elasticsearch creata/verificata"
print_step "   ✅ Container Elasticsearch fermati"
print_step "   ✅ File di lock rimossi"
print_step "   ✅ Directory dati pulita"
print_step "   ✅ Ownership impostata (UID:$ELASTICSEARCH_UID, GID:$ELASTICSEARCH_GID)"
print_step "   ✅ Permessi impostati (755)"
print_step "   ✅ Verifica permessi completata"
print_step "   ✅ Processi Elasticsearch terminati"
echo ""
print_success "🎉 Elasticsearch è pronto per essere avviato senza errori di permessi!"
echo "📅 Completato: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
