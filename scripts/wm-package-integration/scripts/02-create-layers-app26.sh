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
    echo "   • Controlla i log: docker logs php81_osm2cai2"
    echo "   • Verifica il database: docker exec php81_osm2cai2 php artisan migrate:status"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Valori di default
APP_ID="26"
FORCE_FLAG=""
SKIP_ASSOCIATION=false
DRY_RUN_FLAG=""
CONTAINER_NAME="php81_osm2cai2"

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
if ! docker ps | grep -q "$CONTAINER_NAME"; then
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
docker exec -u 0 -it "$CONTAINER_NAME" bash -c "cd /var/www/html/osm2cai2 && $ARTISAN_COMMAND"

LAYER_EXIT_CODE=$?

echo ""
if [ $LAYER_EXIT_CODE -eq 0 ]; then
    echo "✅ Layer creati con successo!"
    
    # Step 2: Associazione hiking routes (se non saltata)
    if [ "$SKIP_ASSOCIATION" = false ]; then
        echo ""
        echo "🔗 Procedendo con l'associazione hiking routes ai layer..."
        
        ASSOCIATE_COMMAND="./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh"
        
        if [ -n "$APP_ID" ]; then
            ASSOCIATE_COMMAND="$ASSOCIATE_COMMAND --app=$APP_ID"
        fi
        
        if [ -n "$DRY_RUN_FLAG" ]; then
            ASSOCIATE_COMMAND="$ASSOCIATE_COMMAND $DRY_RUN_FLAG"
        fi
        
        echo "🚀 Eseguendo: $ASSOCIATE_COMMAND"
        echo ""
        
        $ASSOCIATE_COMMAND
        
        ASSOCIATE_EXIT_CODE=$?
        
        echo ""
        if [ $ASSOCIATE_EXIT_CODE -eq 0 ]; then
            if [ -n "$DRY_RUN_FLAG" ]; then
                echo "✅ Setup completato! Layer creati e associazione simulata (DRY RUN)"
                echo ""
                echo "💡 Per applicare realmente l'associazione:"
                echo "   ./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh"
            else
                echo "🎉 Setup completato! Layer creati e hiking routes associate con successo!"
                echo ""
                echo "🔗 I layer sono ora pronti per l'uso:"
                echo "   • Stato 1: Giallo (#F2C511)"
                echo "   • Stato 2: Viola (#8E43AD)"  
                echo "   • Stato 3: Blu (#2980B9)"
                echo "   • Stato 4: Verde (#27AF60)"
            fi
        else
            echo "⚠️  Layer creati ma errore durante l'associazione hiking routes"
            echo "💡 Puoi riprovare l'associazione con: ./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh"
        fi
    else
        echo ""
        echo "🔗 Prossimi passi:"
        echo "   • Associa le hiking routes: ./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh"
        echo "   • Verifica i layer nel database o nell'interfaccia web"
    fi
else
    echo "❌ Errore durante la creazione dei layer (codice: $LAYER_EXIT_CODE)"
    echo ""
    echo "🔧 Possibili soluzioni:"
    echo "   • Verifica che il database sia accessibile"
    echo "   • Controlla i log del container: docker logs $CONTAINER_NAME"
fi

echo "" 