#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🔍 Verifica Finale Servizi"
echo "=========================="
echo ""

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

# Funzione per gestire errori
handle_error() {
    print_error "ERRORE: Script interrotto alla riga $1"
    print_error "Ultimo comando: $BASH_COMMAND"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Determina la directory root del progetto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../" && pwd)"

# Carica le variabili dal file .env
if [ -f "$PROJECT_ROOT/.env" ]; then
    print_step "Caricamento delle variabili dal file .env..."
    set -o allexport
    source "$PROJECT_ROOT/.env"
    set +o allexport
    print_success "Variabili d'ambiente caricate."
else
    print_error "File .env non trovato. Impossibile continuare senza configurazione."
    exit 1
fi

# Definisci i nomi dei container
if [ -z "$APP_NAME" ]; then
    print_error "La variabile APP_NAME non è definita nel file .env."
    exit 1
fi
PHP_CONTAINER="php81-${APP_NAME}"

print_step "Utilizzo container: ${PHP_CONTAINER}"

# Verifica Laravel serve
print_step "Verifica Laravel serve..."
if curl -f -s http://localhost:8008 &> /dev/null; then
    print_success "Laravel serve attivo su http://localhost:8008"
else
    print_error "Laravel serve non è accessibile!"
    exit 1
fi

# Verifica Horizon
print_step "Verifica Horizon..."
if docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && php artisan horizon:status" | grep -q "running"; then
    print_success "Horizon attivo"
else
    print_error "Horizon non è attivo!"
    exit 1
fi

# Test API Elasticsearch
print_step "Test API Elasticsearch..."
if curl -f -s "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1" &> /dev/null; then
    print_success "API Elasticsearch funzionante"
else
    print_warning "API Elasticsearch potrebbe richiedere configurazione aggiuntiva"
fi

echo ""
print_success "🎉 Verifica servizi completata!"
echo "======================================"
echo ""
echo "📋 Servizi Disponibili:"
echo "   • Applicazione: http://localhost:8008"
echo "   • Nova Admin: http://localhost:8008/nova"
echo "   • MinIO Console: http://localhost:9003 (minioadmin/minioadmin)"
echo "   • MailPit: http://localhost:8025"
echo "   • Elasticsearch: http://localhost:9200"
echo "   • PostgreSQL: localhost:5508"
echo ""
echo "🔧 Comandi Utili:"
echo "   • Accesso container PHP: docker exec -u 0 -it php81-osm2cai2 bash"
echo "   • Dashboard Horizon: http://localhost:8008/horizon"
echo "   • Riavvio Horizon: docker exec php81-osm2cai2 php artisan horizon:terminate"
echo "   • Status Horizon: docker exec php81-osm2cai2 php artisan horizon:status"
echo "   • Log Laravel: docker exec php81-osm2cai2 tail -f storage/logs/laravel.log"
echo "   • Test MinIO: ./scripts/test-minio-laravel.sh"
echo "   • Fix alias Elasticsearch: docker exec php81-osm2cai2 ./scripts/wm-package-integration/scripts/05-fix-elasticsearch-alias.sh"
echo "   • Reinstalla Xdebug: ./docker/configs/phpfpm/init-xdebug.sh"
echo ""
echo "🛑 Per fermare tutto:"
echo "   docker-compose down && docker-compose -f docker-compose.develop.yml down"
echo ""
print_success "Ambiente di sviluppo pronto per l'uso!"
