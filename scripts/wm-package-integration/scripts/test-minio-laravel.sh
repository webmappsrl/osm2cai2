#!/bin/bash

echo "🧪 Test Integrazione Laravel ↔ MinIO"
echo "===================================="

# Test connessione MinIO
echo "🔍 Verificando MinIO..."
if curl -f -s http://localhost:9002/minio/health/live > /dev/null; then
    echo "✅ MinIO attivo"
else
    echo "❌ MinIO non raggiungibile"
    exit 1
fi

# Test Laravel Storage
echo ""
echo "📁 Test Laravel Storage con MinIO..."

docker exec -u 0 php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan tinker --execute=\"
echo 'Test scrittura file su S3...' . PHP_EOL;
try {
    Storage::disk('s3')->put('test-laravel.txt', 'Hello from Laravel! ' . date('Y-m-d H:i:s'));
    echo '✅ File scritto su S3' . PHP_EOL;
    
    if (Storage::disk('s3')->exists('test-laravel.txt')) {
        echo '✅ File esiste su S3' . PHP_EOL;
        \\\$content = Storage::disk('s3')->get('test-laravel.txt');
        echo 'Contenuto: ' . \\\$content . PHP_EOL;
    } else {
        echo '❌ File non trovato su S3' . PHP_EOL;
    }
} catch (Exception \\\$e) {
    echo '❌ Errore: ' . \\\$e->getMessage() . PHP_EOL;
}
\""

echo ""
echo "🌐 Link utili:"
echo "   • Console MinIO: http://localhost:9003 (minioadmin/minioadmin)"
echo "   • MailPit: http://localhost:8025"
echo ""
echo "💡 Per creare il bucket manualmente:"
echo "   1. Vai su http://localhost:9003"
echo "   2. Login: minioadmin / minioadmin"
echo "   3. Crea bucket 'osm2cai2-bucket'" 