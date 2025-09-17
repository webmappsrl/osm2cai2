#!/bin/bash

echo "🚀 Setup Ambiente di Sviluppo OSM2CAI2 con MinIO"
echo "================================================"

# Controlla se il file .env esiste
if [ ! -f ".env" ]; then
    echo "📝 Copiando .env-example in .env..."
    cp .env-example .env
    echo "⚠️  Ricordati di modificare .env con le tue configurazioni!"
else
    echo "✅ File .env già esistente"
fi

# Crea le directory per i volumi se non esistono
echo "📁 Creando directory per i volumi Docker..."
mkdir -p docker/volumes/minio/data
mkdir -p docker/volumes/postgresql/data
mkdir -p docker/volumes/elasticsearch/data

echo "🐳 Avviando i container di sviluppo..."

# Avvia prima i servizi base
echo "   ➜ Avviando servizi base..."
docker-compose up -d

# Poi avvia i servizi di sviluppo
echo "   ➜ Avviando servizi di sviluppo (MinIO, MailPit)..."
docker-compose -f develop.compose.yml up -d

echo ""
echo "⏳ Attendendo che i servizi siano pronti..."
sleep 10

# Controlla se MinIO è raggiungibile
echo "🔍 Verificando MinIO..."
if curl -f -s http://localhost:9000/minio/health/live > /dev/null; then
    echo "✅ MinIO attivo su http://localhost:9000"
    echo "   Console Web: http://localhost:9001"
    echo "   Credenziali: minioadmin / minioadmin"
else
    echo "⚠️  MinIO non ancora pronto, potrebbe richiedere qualche secondo in più"
fi

# Controlla se MailPit è raggiungibile
echo "🔍 Verificando MailPit..."
if curl -f -s http://localhost:8025 > /dev/null; then
    echo "✅ MailPit attivo su http://localhost:8025"
else
    echo "⚠️  MailPit non ancora pronto"
fi

echo ""
echo "🎉 Ambiente di sviluppo configurato!"
echo ""
echo "📋 Servizi disponibili:"
echo "   • Applicazione: http://localhost:8008"
echo "   • MinIO Console: http://localhost:9003 (minioadmin/minioadmin)"
echo "   • MailPit: http://localhost:8025"
echo "   • Elasticsearch: http://localhost:9200"
echo "   • PostgreSQL: localhost:5508"
echo ""
echo "🚀 Avviando server Laravel..."
docker exec -d php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan serve --host 0.0.0.0"

echo "⏳ Attendendo avvio Laravel..."
sleep 5

if curl -f -s http://localhost:8008 > /dev/null; then
    echo "✅ Laravel attivo su http://localhost:8008"
else
    echo "⚠️  Laravel potrebbe richiedere qualche secondo in più per avviarsi"
fi

echo ""
echo "🔧 Prossimi passi:"
echo "   1. Testa MinIO: ./scripts/test-minio-laravel.sh"
echo "   2. Console MinIO: http://localhost:9003 (minioadmin/minioadmin)"
echo "   3. Avvia i worker: docker exec -u 0 php81_osm2cai2 bash -c 'cd /var/www/html/osm2cai2 && php artisan queue:work'"
echo ""
echo "🛑 Per fermare: docker-compose down && docker-compose -f develop.compose.yml down" 