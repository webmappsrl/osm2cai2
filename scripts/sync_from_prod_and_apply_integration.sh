#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🔄 Sync da Produzione e Applicazione Integrazione WMPackage"
echo "=========================================================="
echo "📅 Avviato: $(date '+%Y-%m-%d %H:%M:%S')"
echo "🤖 Modalità: Automatica (Cronjob)"
echo ""
echo "📋 USAGE:"
echo "   $0                    # Importa tutte le app di default (26, 20, 58)"
echo "   $0 --help             # Mostra questo help"
echo "   $0 --apps 26 20       # Importa solo le app specificate"
echo "   $0 -a 26 20           # Forma abbreviata"
echo ""
echo "📁 App disponibili:"
echo "   • App 26: setup-app26.sh (customizzazioni complete)"
echo "   • App 20: setup-app20.sh (import generico + verifiche)"
echo "   • App 58: setup-app58.sh (import generico + customizzazioni)"
echo ""
echo "📝 Esempi:"
echo "   $0                    # Importa tutte le app"
echo "   $0 --apps 26          # Importa solo App 26"
echo "   $0 --apps 20 58       # Importa App 20 e 58"
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

# Configurazione app disponibili (compatibile con bash 3.2)
APP_IDS=("26" "20" "58")
APP_SCRIPTS=("setup-app26.sh" "setup-app20.sh" "setup-app58.sh")

# App di default da importare (tutte)
DEFAULT_APPS=("26" "20" "58")

# Funzione per ottenere la configurazione di un'app
get_app_config() {
    local app_id="$1"
    for i in "${!APP_IDS[@]}"; do
        if [[ "${APP_IDS[$i]}" == "$app_id" ]]; then
            echo "${APP_SCRIPTS[$i]}"
            return 0
        fi
    done
    return 1
}

# Funzione per verificare se un'app esiste
app_exists() {
    local app_id="$1"
    for id in "${APP_IDS[@]}"; do
        if [[ "$id" == "$app_id" ]]; then
            return 0
        fi
    done
    return 1
}

# Funzione per mostrare l'help
show_help() {
    echo "🔄 Sync da Produzione e Applicazione Integrazione WMPackage"
    echo "=========================================================="
    echo ""
    echo "📋 USAGE:"
    echo "   $0                    # Importa tutte le app di default (26, 20, 58)"
    echo "   $0 --help             # Mostra questo help"
    echo "   $0 --apps 26 20       # Importa solo le app specificate"
    echo "   $0 -a 26 20           # Forma abbreviata"
    echo ""
    echo "📁 App disponibili:"
    echo "   • App 26: setup-app26.sh (customizzazioni complete)"
    echo "   • App 20: setup-app20.sh (import generico + verifiche)"
    echo "   • App 58: setup-app58.sh (import generico + customizzazioni)"
    echo ""
    echo "📝 Esempi:"
    echo "   $0                    # Importa tutte le app"
    echo "   $0 --apps 26          # Importa solo App 26"
    echo "   $0 --apps 20 58       # Importa App 20 e 58"
    echo ""
}

# Funzione per validare gli ID delle app
validate_app_ids() {
    local app_ids=("$@")
    
    for app_id in "${app_ids[@]}"; do
        if ! app_exists "$app_id"; then
            print_error "ID app non valido: $app_id"
            echo ""
            echo "App disponibili:"
            for id in "${APP_IDS[@]}"; do
                local config=$(get_app_config "$id")
                echo "   • App $id: $config"
            done
            exit 1
        fi
    done
}

# Funzione per importare una singola app
import_app() {
    local app_id="$1"
    local script_name=$(get_app_config "$app_id")
    
    print_step "=== FASE: IMPORT APP $app_id ==="
    print_step "🎯 App $app_id: $script_name"
    
    if ! bash "$SCRIPT_DIR/wm-package-integration/scripts/$script_name"; then
        print_error "Setup App $app_id fallito! Interruzione setup."
        exit 1
    fi
    print_success "=== FASE COMPLETATA: App $app_id configurata ==="
}

# Funzione per gestire errori
handle_error() {
    print_error "ERRORE: Script interrotto alla riga $1"
    print_error "Ultimo comando: $BASH_COMMAND"
    print_error ""
    print_error "📞 Per assistenza controlla:"
    print_error "• Connessione SSH a osm2caiProd"
    print_error "• File dump in storage/app/backups/"
    print_error "• Stato container: docker ps -a"
    print_error "• Verifica: ssh osm2caiProd 'ls -la html/osm2cai2/storage/backups/'"
    exit 1
}

# Parsing dei parametri
APPS_TO_IMPORT=()

while [[ $# -gt 0 ]]; do
    case $1 in
        --help|-h)
            show_help
            exit 0
            ;;
        --apps|-a)
            shift
            while [[ $# -gt 0 && ! $1 =~ ^-- ]]; do
                APPS_TO_IMPORT+=("$1")
                shift
            done
            ;;
        *)
            print_error "Parametro non riconosciuto: $1"
            echo ""
            show_help
            exit 1
            ;;
    esac
done

# Se non sono state specificate app, usa quelle di default
if [ ${#APPS_TO_IMPORT[@]} -eq 0 ]; then
    APPS_TO_IMPORT=("${DEFAULT_APPS[@]}")
    print_step "Nessuna app specificata, importando tutte le app di default: ${APPS_TO_IMPORT[*]}"
else
    # Valida gli ID delle app forniti
    validate_app_ids "${APPS_TO_IMPORT[@]}"
    print_step "App da importare: ${APPS_TO_IMPORT[*]}"
fi

echo ""
echo "📁 Script per le app che verranno utilizzati:"
for app_id in "${APPS_TO_IMPORT[@]}"; do
    config=$(get_app_config "$app_id")
    echo "   • $config (App $app_id)"
done
echo ""

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Determina la directory root del progetto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../" && pwd)"

# Verifica prerequisiti
print_step "Verifica prerequisiti..."

# Controlla se Docker è installato
if ! command -v docker &> /dev/null; then
    print_error "Docker non è installato!"
    exit 1
fi

# Controlla se Docker Compose è installato
if ! command -v docker-compose &> /dev/null; then
    print_error "Docker Compose non è installato!"
    exit 1
fi

# Controlla se rsync è disponibile
if ! command -v rsync &> /dev/null; then
    print_error "rsync non è installato!"
    exit 1
fi

print_success "Prerequisiti verificati"

# Log automatico per cronjob
echo ""
print_warning "⚠️  ATTENZIONE: Questa operazione:"
print_warning "   • Scaricherà il dump da produzione (~600MB)"
print_warning "   • Cancellerà TUTTI i dati nel database locale"
print_warning "   • Applicherà l'integrazione WMPackage di produzione"
echo ""
print_step "🤖 Modalità automatica (cronjob) - procedo senza conferma utente"
echo ""

# FASE 1: Download Dump da Produzione
print_step "=== FASE 1: DOWNLOAD DUMP DA PRODUZIONE ==="

# Crea directory backups se non esiste
mkdir -p "$PROJECT_ROOT/storage/app/backups"

print_step "Scaricando dump da osm2caiProd..."
print_step "Dimensione file remoto: $(ssh osm2caiProd "du -h html/osm2cai2/storage/backups/last_dump.sql.gz" 2>/dev/null | cut -f1 || echo "non disponibile")"
print_warning "⚠️  File di ~600MB - il download potrebbe richiedere alcuni minuti"
print_step "⏱️  Timeout impostato a 10 minuti, velocità limitata a 5MB/s per stabilità"

if rsync -avz --progress --timeout=600 --partial --bwlimit=5000 osm2caiProd:html/osm2cai2/storage/backups/last_dump.sql.gz "$PROJECT_ROOT/storage/app/backups/dump.sql.gz"; then
    print_success "Dump scaricato con successo"
    print_step "Dimensione dump scaricato: $(du -h $PROJECT_ROOT/storage/app/backups/dump.sql.gz | cut -f1)"
    
    # Verifica integrità del file (controlla che non sia vuoto)
    if [ -s "$PROJECT_ROOT/storage/app/backups/dump.sql.gz" ]; then
        print_success "File scaricato correttamente (non vuoto)"
    else
        print_error "File scaricato è vuoto o corrotto"
        exit 1
    fi
else
    print_error "Errore durante il download del dump da produzione"
    exit 1
fi

print_success "=== FASE 1 COMPLETATA ==="

# FASE 2: Reset Database dal Dump
print_step "=== FASE 2: RESET DATABASE DAL DUMP ==="

print_step "Eseguendo script di reset database (modalità automatica)..."
if bash "$SCRIPT_DIR/wm-package-integration/scripts/06-reset-database-from-dump.sh" --auto; then
    print_success "Reset database completato con successo"
else
    print_error "Errore durante il reset del database"
    exit 1
fi

print_success "=== FASE 2 COMPLETATA ==="

# Carica le variabili dal file .env per le fasi successive
if [ -f "$PROJECT_ROOT/.env" ]; then
    set -o allexport
    source "$PROJECT_ROOT/.env"
    set +o allexport
    PHP_CONTAINER="php81-${APP_NAME}"
    POSTGRES_CONTAINER="postgres-${APP_NAME}"
else
    print_error "File .env non trovato per le fasi successive"
    exit 1
fi

# FASE 2.5: Esecuzione Migrazioni
print_step "=== FASE 2.5: ESECUZIONE MIGRAZIONI ==="

print_step "Eseguendo migrazioni Laravel..."
if docker exec "$PHP_CONTAINER" php artisan migrate; then
    print_success "Migrazioni completate con successo"
else
    print_error "Errore durante l'esecuzione delle migrazioni"
    exit 1
fi

print_success "=== FASE 2.5 COMPLETATA ==="

# FASE 3: FIX CAMPI TRANSLATABLE
print_step "=== FASE 3: FIX CAMPI TRANSLATABLE NULL ==="

# Fix dei campi translatable null prima degli import delle app
print_step "Fix dei campi translatable null nei modelli..."
if ! docker exec "$PHP_CONTAINER" bash -c "cd /var/www/html/osm2cai2 && ./scripts/fix-translatable-fields.sh"; then
    print_error "Errore durante il fix dei campi translatable! Interruzione setup."
    exit 1
fi
print_success "Campi translatable fixati"

print_success "=== FASE 3 COMPLETATA: Campi translatable fixati ==="

# FASE 4: IMPORT APP SPECIFICATE
print_step "=== FASE 4: IMPORT APP SPECIFICATE ==="

for app_id in "${APPS_TO_IMPORT[@]}"; do
    import_app "$app_id"
done

print_success "=== FASE 4 COMPLETATA: Tutte le app specificate importate ==="

# FASE 5: Verifica Finale
print_step "=== FASE 5: VERIFICA FINALE ==="

# Verifica che i servizi siano attivi
print_step "Verifica servizi attivi..."
if docker ps | grep -q "$PHP_CONTAINER" && docker ps | grep -q "$POSTGRES_CONTAINER"; then
    print_success "Container attivi"
else
    print_warning "Alcuni container potrebbero non essere attivi"
fi

# Test connessione database
print_step "Test connessione database finale..."
if docker exec "$POSTGRES_CONTAINER" psql -U osm2cai2 -d osm2cai2 -c "SELECT 1;" &> /dev/null; then
    print_success "Database funzionante"
else
    print_error "Problema connessione database"
    exit 1
fi

print_success "=== FASE 5 COMPLETATA ==="

echo ""
print_success "🎉 SYNC DA PRODUZIONE E INTEGRAZIONE COMPLETATA CON SUCCESSO!"
echo "📅 Completato: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
print_step "📋 Riepilogo operazioni:"
print_step "   ✅ Dump scaricato da osm2caiProd"
print_step "   ✅ Database resettato dal dump"
print_step "   ✅ Migrazioni applicate"
print_step "   ✅ Campi translatable fixati"
for app_id in "${APPS_TO_IMPORT[@]}"; do
    print_step "   ✅ App $app_id configurata"
done
print_step "   ✅ Verifica finale completata"
echo ""
print_step "📁 Script utilizzati per le app:"
for app_id in "${APPS_TO_IMPORT[@]}"; do
    config=$(get_app_config "$app_id")
    print_step "   • $config (App $app_id)"
done
echo ""
print_step "🌐 L'applicazione dovrebbe essere accessibile su: http://127.0.0.1:8008"
print_step "📊 Horizon dovrebbe essere attivo per la gestione delle code"
echo "" 