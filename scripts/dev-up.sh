#!/bin/bash

echo "🚀 Avvio Ambiente di Sviluppo OSM2CAI2 con MinIO"
echo "==============================================="

# Verifica che docker-compose.yml esista
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ File docker-compose.yml non trovato!"
    echo "💡 Esegui questo script dalla root del progetto"
    exit 1
fi

# Verifica che docker-compose.develop.yml esista
if [ ! -f "docker-compose.develop.yml" ]; then
    echo "❌ File docker-compose.develop.yml non trovato!"
    exit 1
fi

# Crea le directory per i volumi se non esistono
echo "📁 Creazione directory per i volumi..."
mkdir -p docker/volumes/minio/data
mkdir -p docker/volumes/postgresql/data
mkdir -p docker/volumes/elasticsearch/data

# Avvia i servizi base
echo "🐳 Avvio servizi base..."
docker-compose up -d

# Avvia i servizi di sviluppo
echo "🛠️  Avvio servizi di sviluppo (MinIO, MailHog)..."
docker-compose -f docker-compose.develop.yml up -d

echo ""
echo "✅ Ambiente di sviluppo avviato!"
echo ""
echo "🌐 Servizi disponibili:"
echo "   - Applicazione:     http://localhost:8008"
echo "   - MinIO Console:    http://localhost:9001 (minioadmin/minioadmin)"
echo "   - MinIO API:        http://localhost:9000"
echo "   - MailHog:          http://localhost:8025"
echo "   - Elasticsearch:    http://localhost:9200"
echo "   - PostgreSQL:       localhost:5508"
echo ""
echo "🔧 Setup iniziale MinIO:"
echo "   1. Vai su http://localhost:9001"
echo "   2. Login: minioadmin / minioadmin"
echo "   3. Crea un bucket chiamato 'osm2cai2-bucket'"
echo "   4. Configura il .env con le credenziali MinIO"
echo ""
echo "⏹️  Per fermare: ./scripts/dev-down.sh" 