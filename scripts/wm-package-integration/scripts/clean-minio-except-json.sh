#!/bin/bash

# Script per cancellare tutto il contenuto di MinIO tranne la cartella json
# ATTENZIONE: Operazione distruttiva e irreversibile!

set -e
set -o pipefail

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Configurazione
APP_NAME="osm2cai2"
BUCKET_NAME="wmfe"
BUCKET_PATH="wmfe/osm2cai2"
MINIO_HOST="http://localhost:9000"
MINIO_USER="minioadmin"
MINIO_PASS="minioadmin"
MINIO_CONTAINER="minio-${APP_NAME}"
MC_COMMAND="docker exec ${MINIO_CONTAINER} mc"

echo ""
print_step "=== PULIZIA MINIO (PRESERVA CARTELLA JSON) ==="
echo ""
print_warning "⚠️  ATTENZIONE: Questa operazione cancellerà TUTTI i dati da MinIO"
print_warning "⚠️  ad eccezione della cartella 'json'"
print_warning "⚠️  Questa operazione è IRREVERSIBILE!"
echo ""
print_info "Bucket: $BUCKET_NAME"
print_info "Path: $BUCKET_PATH"
print_info "Host: $MINIO_HOST"
echo ""

# Configura l'alias MinIO
print_step "Configurazione MinIO client..."
$MC_COMMAND alias set local $MINIO_HOST $MINIO_USER $MINIO_PASS > /dev/null 2>&1

# Verifica che il bucket esista
if ! $MC_COMMAND ls local/$BUCKET_PATH > /dev/null 2>&1; then
    print_error "Bucket path '$BUCKET_PATH' non trovato!"
    exit 1
fi

print_success "Connesso a MinIO"
echo ""

# Lista il contenuto attuale
print_step "📋 Contenuto attuale del bucket:"
echo ""
$MC_COMMAND ls local/$BUCKET_PATH/
echo ""

# Ottieni la lista di tutte le cartelle/prefissi di primo livello
print_step "🔍 Identificazione cartelle da cancellare..."
echo ""

# Ottieni tutti i prefissi di primo livello (cartelle)
FOLDERS=$($MC_COMMAND ls local/$BUCKET_PATH/ | awk '{print $NF}' | sed 's:/$::')

if [ -z "$FOLDERS" ]; then
    print_warning "Il bucket è già vuoto o contiene solo file alla radice"
    exit 0
fi

# Mostra le cartelle che verranno cancellate
FOLDERS_TO_DELETE=""
JSON_FOUND=false

while IFS= read -r folder; do
    if [ "$folder" == "json" ]; then
        print_success "✓ Cartella 'json' - PRESERVATA"
        JSON_FOUND=true
    else
        print_warning "✗ Cartella '$folder' - VERRÀ CANCELLATA"
        FOLDERS_TO_DELETE="$FOLDERS_TO_DELETE $folder"
    fi
done <<< "$FOLDERS"

echo ""

if [ -z "$FOLDERS_TO_DELETE" ]; then
    if [ "$JSON_FOUND" = true ]; then
        print_success "✅ Solo la cartella 'json' è presente, nulla da cancellare"
    else
        print_info "Nessuna cartella da cancellare"
    fi
    exit 0
fi

# Ultima conferma
print_warning "📝 Le seguenti cartelle verranno CANCELLATE:"
for folder in $FOLDERS_TO_DELETE; do
    echo "   • $folder"
done
echo ""
print_info "La cartella 'json' verrà PRESERVATA"
echo ""
print_error "Procedo con la cancellazione in 5 secondi... (Ctrl+C per annullare)"
sleep 5

echo ""
print_step "🗑️  Cancellazione in corso..."
echo ""

# Cancella ogni cartella tranne json
deleted_count=0
error_count=0

for folder in $FOLDERS_TO_DELETE; do
    print_info "Cancellazione '$folder'..."
    
    if $MC_COMMAND rm --recursive --force local/$BUCKET_PATH/$folder/ 2>&1; then
        print_success "✅ Cartella '$folder' cancellata"
        deleted_count=$((deleted_count + 1))
    else
        print_error "❌ Errore durante la cancellazione di '$folder'"
        error_count=$((error_count + 1))
    fi
done

echo ""
print_step "📋 Contenuto finale del bucket:"
echo ""
$MC_COMMAND ls local/$BUCKET_PATH/
echo ""

# Riepilogo
print_step "================================================================"
print_step "📊 RIEPILOGO OPERAZIONE"
print_step "================================================================"
print_success "✅ Cartelle cancellate: $deleted_count"

if [ $error_count -gt 0 ]; then
    print_error "❌ Errori durante la cancellazione: $error_count"
else
    print_success "🎉 Operazione completata con successo!"
fi

if [ "$JSON_FOUND" = true ]; then
    print_success "✅ Cartella 'json' preservata correttamente"
fi

echo ""

if [ $error_count -gt 0 ]; then
    exit 1
else
    exit 0
fi

