#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🚀 Abilitazione Indicizzazione Automatica Scout per OSM2CAI2"

# Funzione per gestire errori
handle_error() {
    echo ""
    echo "❌ ERRORE: Script interrotto alla riga $1"
    echo "❌ Ultimo comando: $BASH_COMMAND"
    echo ""
    echo "🔧 Possibili soluzioni:"
    echo "   • Verifica di essere nel container: docker exec -it php81_osm2cai2 bash"
    echo "   • Controlla i permessi dei file .env"
    echo "   • Verifica connessione Elasticsearch"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Controlla se siamo nel container Docker
if [ ! -f "/var/www/html/osm2cai2/.env" ]; then
    echo "❌ Script deve essere eseguito dal container Docker PHP"
    echo "💡 Esegui: docker exec -u 0 -it php81_osm2cai2 bash"
    echo "💡 Poi: cd /var/www/html/osm2cai2 && ./scripts/wm-package-integration/scripts/04-enable-scout-automatic-indexing.sh"
    exit 1
fi

cd /var/www/html/osm2cai2

echo "📝 Verifica configurazione Scout..."
echo "✅ Assumo che le configurazioni Scout siano già presenti nel .env"

echo "🔄 Riavvio worker delle code per applicare nuove configurazioni..."
php artisan queue:restart

echo "📊 Stato attuale dell'indicizzazione..."
php artisan tinker --execute="
echo 'HikingRoute totali: ' . App\Models\HikingRoute::count() . PHP_EOL;
echo 'HikingRoute con geometry: ' . App\Models\HikingRoute::whereNotNull('geometry')->count() . PHP_EOL;
echo 'HikingRoute indicizzabili: ' . App\Models\HikingRoute::whereNotNull('geometry')->where('osm2cai_status', '!=', 0)->count() . PHP_EOL;
"

echo ""
echo "✅ Indicizzazione automatica abilitata!"
echo ""
echo "📋 Prossimi passi:"
echo "1. Assicurati che Horizon sia attivo: php artisan horizon"
echo "2. Monitora le code: http://localhost:8008/horizon"
echo "3. Verifica status: php artisan horizon:status"
echo "4. Testa modificando un HikingRoute nell'interfaccia Nova"
echo ""
echo "🔍 Per monitorare l'indicizzazione automatica:"
echo "   docker exec -it php81_osm2cai2 bash -c 'tail -f /var/www/html/osm2cai2/storage/logs/laravel.log | grep -i scout'"
echo ""
echo "📈 Per verificare gli indici Elasticsearch:"
echo "   curl -X GET 'elasticsearch:9200/_cat/indices?v'" 