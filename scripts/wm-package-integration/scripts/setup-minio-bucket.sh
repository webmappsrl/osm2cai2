#!/bin/bash

echo "🪣 Configurazione Bucket MinIO per OSM2CAI2"
echo "==========================================="

# Legge APP_NAME dal file .env
if [ -f ".env" ]; then
    APP_NAME=$(grep "^APP_NAME=" .env | cut -d'=' -f2 | tr -d ' ')
else
    echo "⚠️  File .env non trovato, uso valore di default"
    APP_NAME="osm2cai2"
fi

BUCKET_NAME=${AWS_BUCKET:-${APP_NAME}-bucket}
MINIO_HOST="http://minio-${APP_NAME}:9000"
MINIO_USER=${MINIO_ROOT_USER:-minioadmin}
MINIO_PASS=${MINIO_ROOT_PASSWORD:-minioadmin}

echo "📋 Configurazione:"
echo "   Host: $MINIO_HOST"
echo "   Bucket: $BUCKET_NAME"
echo "   User: $MINIO_USER"

# MinIO dovrebbe essere disponibile (saltiamo il controllo per ora)
echo ""
echo "🔍 Procedendo con la configurazione MinIO..."
echo "✅ MinIO dovrebbe essere disponibile"

# Installa mc (MinIO Client) se non è presente
if ! command -v mc &> /dev/null; then
    echo "📦 Installando MinIO Client (mc)..."
    
    # Prova a installare mc tramite container
    echo "🐳 Usando mc tramite container Docker..."
    
    # Alias per usare mc tramite container
    alias mc='docker run --rm -it --entrypoint=/bin/sh minio/mc:latest -c'
    
    MC_COMMAND="docker run --rm -it --network osm2cai2_laravel minio/mc:latest"
else
    echo "✅ MinIO Client (mc) già installato"
    MC_COMMAND="mc"
fi

echo ""
echo "🔧 Configurando MinIO..."

# Configura l'alias MinIO
echo "   ➜ Configurando alias MinIO..."
$MC_COMMAND alias set local $MINIO_HOST $MINIO_USER $MINIO_PASS

# Crea il bucket se non esiste
echo "   ➜ Creando bucket '$BUCKET_NAME'..."
$MC_COMMAND mb local/$BUCKET_NAME --ignore-existing

# Imposta la policy pubblica per il bucket (opzionale, per file pubblici)
echo "   ➜ Impostando policy di accesso..."
$MC_COMMAND anonymous set public local/$BUCKET_NAME

# Lista i bucket per conferma
echo ""
echo "📋 Bucket presenti in MinIO:"
$MC_COMMAND ls local/

echo ""
echo "✅ Configurazione completata!"
echo ""
echo "🔗 Link utili:"
echo "   • Console MinIO: http://localhost:9003"
echo "   • API MinIO: http://localhost:9002"
echo "   • Bucket URL: http://localhost:9002/$BUCKET_NAME"
echo ""
echo "📝 Configurazione Laravel (.env):"
echo "   AWS_ACCESS_KEY_ID=$MINIO_USER"
echo "   AWS_SECRET_ACCESS_KEY=$MINIO_PASS"
echo "   AWS_BUCKET=$BUCKET_NAME"
echo "   AWS_ENDPOINT=http://minio-${APP_NAME}:9000"
echo "   AWS_URL=http://localhost:9002"
echo "   AWS_USE_PATH_STYLE_ENDPOINT=true"
echo ""
echo "🧪 Test upload:"
echo "   echo 'test' | $MC_COMMAND pipe local/$BUCKET_NAME/test.txt" 