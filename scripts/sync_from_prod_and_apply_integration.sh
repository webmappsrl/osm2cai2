#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

echo "🔄 Sync da Produzione e Applicazione Integrazione WMPackage"
echo "=========================================================="
echo "📅 Avviato: $(date '+%Y-%m-%d %H:%M:%S')"
echo "🤖 Modalità: Automatica (Cronjob)"
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
    print_error ""
    print_error "📞 Per assistenza controlla:"
print_error "• Connessione SSH a osm2caiProd"
print_error "• File dump in storage/app/backups/"
print_error "• Stato container: docker ps -a"
print_error "• Verifica: ssh osm2caiProd 'ls -la html/osm2cai2/storage/backups/'"
    exit 1
}

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

# FASE 3: IMPORT APP SPECIFICHE
print_step "=== FASE 3: IMPORT APP SPECIFICHE ==="

# FASE 3A: IMPORT APP 26 CON CUSTOMIZZAZIONI
print_step "=== FASE 3A: IMPORT APP 26 CON CUSTOMIZZAZIONI ==="
print_step "🎯 App 26: Import + layer + associazione hiking routes + proprietà + media"

# Import App 26
if ! bash "$SCRIPT_DIR/wm-package-integration/scripts/01-import-app-from-geohub.sh" 26; then
    print_error "Import App 26 fallito! Interruzione setup."
    exit 1
fi

# Setup layer per App 26
if ! bash "$SCRIPT_DIR/wm-package-integration/scripts/02-create-layers-app26.sh"; then
    print_error "Setup layer App 26 fallito! Interruzione setup."
    exit 1
fi

# Associazione hiking routes per App 26
if ! bash "$SCRIPT_DIR/wm-package-integration/scripts/03-associate-routes-app26.sh"; then
    print_error "Associazione routes App 26 fallita! Interruzione setup."
    exit 1
fi

# Proprietà e media per App 26
if ! bash "$SCRIPT_DIR/wm-package-integration/scripts/10-hiking-routes-properties-and-taxonomy.sh"; then
    print_error "Setup proprietà e media App 26 fallito! Interruzione setup."
    exit 1
fi

print_success "=== FASE 3A COMPLETATA: App 26 configurata con customizzazioni ==="

# FASE 3B: IMPORT APP 20
print_step "=== FASE 3B: IMPORT APP 20 ==="
print_step "🎯 App 20: Import generico"

if ! bash "$SCRIPT_DIR/wm-package-integration/scripts/01-import-app-from-geohub.sh" 20; then
    print_error "Import App 20 fallito! Interruzione setup."
    exit 1
fi
print_success "=== FASE 3B COMPLETATA: App 20 configurata ==="

# FASE 3C: IMPORT APP 58
print_step "=== FASE 3C: IMPORT APP 58 ==="
print_step "🎯 App 58: Import generico"

if ! bash "$SCRIPT_DIR/wm-package-integration/scripts/01-import-app-from-geohub.sh" 58; then
    print_error "Import App 58 fallito! Interruzione setup."
    exit 1
fi
print_success "=== FASE 3C COMPLETATA: App 58 configurata ==="

print_success "=== FASE 3 COMPLETATA: Tutte le app importate ==="

# FASE 4: Applicazione Integrazione WMPackage Produzione
print_step "=== FASE 4: APPLICAZIONE INTEGRAZIONE WMPACKAGE PRODUZIONE ==="

print_step "Eseguendo script di integrazione WMPackage produzione..."
if bash "$SCRIPT_DIR/wm-package-integration/wm-package-prod-integration.sh"; then
    print_success "Integrazione WMPackage produzione completata con successo"
else
    print_error "Errore durante l'integrazione WMPackage produzione"
    exit 1
fi

print_success "=== FASE 4 COMPLETATA ==="

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
print_step "   ✅ App 26 importata con customizzazioni"
print_step "   ✅ App 20 importata (generico)"
print_step "   ✅ App 58 importata (generico)"
print_step "   ✅ Integrazione WMPackage produzione applicata"
print_step "   ✅ Verifica finale completata"
echo ""
print_step "🌐 L'applicazione dovrebbe essere accessibile su: http://127.0.0.1:8008"
print_step "📊 Horizon dovrebbe essere attivo per la gestione delle code"
echo "" 