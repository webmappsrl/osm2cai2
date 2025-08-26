#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🔄 Aggiornamento App ID per Hiking Routes Esistenti - OSM2CAI2"
echo "=============================================================="

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
APP_ID="1"
FORCE_FLAG=""
DRY_RUN_FLAG=""
CONTAINER_NAME="php81-${APP_NAME}"

# Funzione di help
show_help() {
    echo ""
    echo "📋 Utilizzo: $0 [opzioni]"
    echo ""
    echo "🔧 Opzioni:"
    echo "   --app=ID              ID dell'app da assegnare (default: 1 - App del sentierista)"
    echo "   --force               Forza l'aggiornamento senza conferma"
    echo "   --dry-run             Simula l'aggiornamento senza salvare"
    echo "   --help                Mostra questo messaggio di aiuto"
    echo ""
    echo "📝 Esempi:"
    echo "   $0                           # Aggiorna app_id per app 1 (default)"
    echo "   $0 --app=26                  # Aggiorna app_id per app 26"
    echo "   $0 --force                   # Forza aggiornamento senza conferma"
    echo "   $0 --dry-run                 # Simula aggiornamento"
    echo ""
    echo "ℹ️  Questo script aggiorna l'app_id per tutti gli hiking routes"
    echo "   esistenti che non hanno un app_id assegnato."
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
ARTISAN_COMMAND="php artisan osm2cai:update-hiking-routes-app-id --app=$APP_ID"

if [ -n "$FORCE_FLAG" ]; then
    ARTISAN_COMMAND="$ARTISAN_COMMAND --force"
fi

if [ -n "$DRY_RUN_FLAG" ]; then
    ARTISAN_COMMAND="$ARTISAN_COMMAND --dry-run"
fi

echo ""
if [ -n "$DRY_RUN_FLAG" ]; then
    echo "🧪 Modalità DRY RUN - Nessuna modifica verrà salvata"
fi

echo "🎯 Aggiornamento app_id per hiking routes esistenti"
echo "📱 App ID target: $APP_ID"

echo ""
echo "🚀 Eseguendo comando Laravel..."
echo "   ➜ $ARTISAN_COMMAND"
echo ""

# Esegui il comando nel container
docker exec -u 0 -it "$CONTAINER_NAME" bash -c "cd /var/www/html/osm2cai2 && $ARTISAN_COMMAND"

EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "✅ Script completato con successo!"
    
    if [ -z "$DRY_RUN_FLAG" ]; then
        echo ""
        echo "🎉 App_id aggiornato per hiking routes esistenti!"
        echo ""
        echo "🔗 Prossimi passi:"
        echo "   • Crea i layer: ./scripts/wm-package-integration/scripts/02-create-layers-app26.sh"
        echo "   • Associa le hiking routes: ./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh"
        echo "   • Verifica nel database o nell'interfaccia web"
    else
        echo ""
        echo "🧪 Simulazione completata - nessuna modifica è stata salvata"
        echo "💡 Rimuovi --dry-run per applicare realmente le modifiche"
    fi
else
    echo "❌ Script terminato con errore (codice: $EXIT_CODE)"
    echo ""
    echo "🔧 Possibili soluzioni:"
    echo "   • Verifica che il database sia accessibile"
    echo "   • Controlla i log del container: docker logs $CONTAINER_NAME"
    echo "   • Verifica che esistano hiking routes nel database"
fi

echo ""
