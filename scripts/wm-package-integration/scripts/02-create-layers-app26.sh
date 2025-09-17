#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🏗️  Creazione Layer di Accatastamento OSM2CAI2 - APP 26"
echo "======================================================="

# Funzione per gestire errori
handle_error() {
    echo ""
    echo "❌ ERRORE: Script interrotto alla riga $1"
    echo "❌ Ultimo comando: $BASH_COMMAND"
    echo ""
    echo "🔧 Possibili soluzioni:"
    echo "   • Verifica che il container sia attivo: docker ps"
    echo "   • Controlla i log: docker logs $CONTAINER_NAME"
echo "   • Verifica il database: docker exec $CONTAINER_NAME php artisan migrate:status"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Carica le variabili dal file .env se esiste
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../" && pwd)"

if [ -f "$PROJECT_ROOT/.env" ]; then
    set -o allexport
    source "$PROJECT_ROOT/.env"
    set +o allexport
else
    echo "❌ File .env non trovato nella root del progetto."
    exit 1
fi

# Valori di default
APP_ID=""
FORCE_FLAG=""
SKIP_ASSOCIATION=false
DRY_RUN_FLAG=""
CONTAINER_NAME="php81-${APP_NAME}"

# Funzione di help
show_help() {
    echo ""
    echo "📋 Utilizzo: $0 [opzioni]"
    echo ""
    echo "🔧 Opzioni:"
    echo "   --app=ID              ID dell'app (default: 26)"
    echo "   --force               Sovrascrivi i layer esistenti"
    echo "   --skip-association    Crea solo i layer, salta l'associazione hiking routes"
    echo "   --dry-run             Simula l'associazione senza salvare"
    echo "   --help                Mostra questo messaggio di aiuto"
    echo ""
    echo "📝 Esempi:"
    echo "   $0                           # Crea layer per app 26 e associa hiking routes"
    echo "   $0 --force                   # Ricrea layer per app 26 e riassocia hiking routes"
    echo "   $0 --skip-association        # Crea solo i layer per app 26"
    echo "   $0 --dry-run                 # Crea layer per app 26 e simula associazione"
    echo "   $0 --app=123                 # Crea layer per l'app 123 e associa routes"
    echo ""
}

# Parsing degli argomenti
while [[ $# -gt 0 ]]; do
    case $1 in
        --app=*)
            APP_ID="${1#*=}"
            shift
            ;;
        --force)
            FORCE_FLAG="--force"
            shift
            ;;
        --skip-association)
            SKIP_ASSOCIATION=true
            shift
            ;;
        --dry-run)
            DRY_RUN_FLAG="--dry-run"
            shift
            ;;
        --help|-h)
            show_help
            exit 0
            ;;
        *)
            echo "❌ Opzione sconosciuta: $1"
            show_help
            exit 1
            ;;
    esac
done

# Controlla se il container è in esecuzione
echo "🔍 Verificando container Docker..."
CONTAINER_STATUS=$(docker ps --format "table {{.Names}}" | grep "$CONTAINER_NAME" || echo "")
if [ -z "$CONTAINER_STATUS" ]; then
    echo "❌ Container $CONTAINER_NAME non in esecuzione"
    echo "💡 Avvia l'ambiente di sviluppo: ./scripts/dev-setup.sh"
    exit 1
fi

echo "✅ Container Docker attivo"

# Costruisci il comando
ARTISAN_COMMAND="php artisan osm2cai:create-accatastamento-layers"

if [ -n "$APP_ID" ]; then
    ARTISAN_COMMAND="$ARTISAN_COMMAND --app=$APP_ID"
fi

if [ -n "$FORCE_FLAG" ]; then
    ARTISAN_COMMAND="$ARTISAN_COMMAND $FORCE_FLAG"
fi

echo ""
echo "🚀 Eseguendo comando Laravel..."
echo "   ➜ $ARTISAN_COMMAND"
echo ""

# Esegui il comando nel container
docker exec -u 0 "$CONTAINER_NAME" bash -c "cd /var/www/html/osm2cai2 && $ARTISAN_COMMAND"

LAYER_EXIT_CODE=$?

echo ""
if [ $LAYER_EXIT_CODE -eq 0 ]; then
    echo "✅ Layer creati con successo!"
    
    echo ""
    echo "🔗 Prossimi passi:"
    echo "   • Associa le hiking routes: ./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh"
    echo "   • Verifica i layer nel database o nell'interfaccia web"
else
    echo "❌ Errore durante la creazione dei layer (codice: $LAYER_EXIT_CODE)"
    echo ""
    echo "🔧 Possibili soluzioni:"
    echo "   • Verifica che il database sia accessibile"
    echo "   • Controlla i log del container: docker logs $CONTAINER_NAME"
fi

echo "" 