#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🔗 Associazione Hiking Routes ai Layer - Geohub APP 26"
echo "======================================================="

# Funzione per gestire errori
handle_error() {
    echo ""
    echo "❌ ERRORE: Script interrotto alla riga $1"
    echo "❌ Ultimo comando: $BASH_COMMAND"
    echo ""
    echo "🔧 Possibili soluzioni:"
    echo "   • Verifica che il container sia attivo: docker ps"
    echo "   • Controlla i log: docker logs php81-osm2cai2"
    echo "   • Verifica che i layer esistano nel database"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Valori di default
APP_ID="1"
STATUS=""
DRY_RUN_FLAG=""
CONTAINER_NAME="php81-osm2cai2"

# Funzione di help
show_help() {
    echo ""
    echo "📋 Utilizzo: $0 [opzioni]"
    echo ""
    echo "🔧 Opzioni:"
    echo "   --status=N   Solo un specifico osm2cai_status (1, 2, 3, 4)"
    echo "   --app=ID     ID dell'app (default: 26 - Geohub)"
    echo "   --dry-run    Esegui senza salvare le modifiche"
    echo "   --help       Mostra questo messaggio di aiuto"
    echo ""
    echo "📝 Esempi:"
    echo "   $0                        # Associa tutte le hiking routes ai layer app 26"
    echo "   $0 --status=3             # Associa solo hiking routes con status 3 per app 26"
    echo "   $0 --dry-run              # Simula l'associazione senza salvare per app 26"
    echo "   $0 --app=123              # Associa per l'app con ID 123"
    echo "   $0 --status=2 --dry-run   # Simula associazione solo per status 2"
    echo ""
    echo "ℹ️  Gli stati di accatastamento sono:"
    echo "   1 = Stato Accatastamento 1 (Giallo)"
    echo "   2 = Stato Accatastamento 2 (Viola)"
    echo "   3 = Stato Accatastamento 3 (Blu)"
    echo "   4 = Stato Accatastamento 4 (Verde)"
    echo ""
}

# Parsing degli argomenti
while [[ $# -gt 0 ]]; do
    case $1 in
        --status=*)
            STATUS="${1#*=}"
            # Valida che sia un numero tra 1 e 4
            if ! [[ "$STATUS" =~ ^[1-4]$ ]]; then
                echo "❌ Status deve essere un numero tra 1 e 4, ricevuto: $STATUS"
                exit 1
            fi
            shift
            ;;
        --app=*)
            APP_ID="${1#*=}"
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
ARTISAN_COMMAND="php artisan osm2cai:associate-hiking-routes-to-layers"

if [ -n "$STATUS" ]; then
    ARTISAN_COMMAND="$ARTISAN_COMMAND --status=$STATUS"
fi

if [ -n "$APP_ID" ]; then
    ARTISAN_COMMAND="$ARTISAN_COMMAND --app=$APP_ID"
fi

if [ -n "$DRY_RUN_FLAG" ]; then
    ARTISAN_COMMAND="$ARTISAN_COMMAND $DRY_RUN_FLAG"
fi

echo ""
if [ -n "$DRY_RUN_FLAG" ]; then
    echo "🧪 Modalità DRY RUN - Nessuna modifica verrà salvata"
fi

if [ -n "$STATUS" ]; then
    echo "🎯 Processando solo hiking routes con osm2cai_status = $STATUS"
else
    echo "🎯 Processando tutte le hiking routes (status 1, 2, 3, 4)"
fi

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
        echo "🎉 Le hiking routes sono state associate ai layer corrispondenti!"
        echo ""
        echo "🔗 Prossimi passi:"
        echo "   • Verifica le associazioni nel database"
        echo "   • Testa la visualizzazione dei layer nell'interfaccia web"
        echo "   • Controlla che i filtri per stato funzionino correttamente"
    else
        echo ""
        echo "🧪 Simulazione completata - nessuna modifica è stata salvata"
        echo "💡 Rimuovi --dry-run per applicare realmente le modifiche"
    fi
else
    echo "❌ Script terminato con errore (codice: $EXIT_CODE)"
    echo ""
    echo "🔧 Possibili soluzioni:"
    echo "   • Assicurati che i layer siano stati creati: ./scripts/create-layers.sh"
    echo "   • Verifica che il database sia accessibile"
    echo "   • Controlla i log del container: docker logs $CONTAINER_NAME"
    echo "   • Verifica che esistano hiking routes con lo status specificato"
fi

echo "" 