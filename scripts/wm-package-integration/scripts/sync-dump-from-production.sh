#!/bin/bash

# Abilita strict mode: ferma lo script in caso di errore
set -e
set -o pipefail

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

# Funzione per mostrare l'help
show_help() {
    echo "🔄 Sync Dump da Produzione"
    echo "=========================="
    echo ""
    echo "📋 USAGE:"
    echo "   $0                    # Sincronizza dump da produzione"
    echo "   $0 --help             # Mostra questo help"
    echo ""
    echo "📝 Descrizione:"
    echo "   Scarica il dump del database da osm2caiProd e lo salva"
    echo "   in storage/app/backups/dump.sql.gz per l'uso locale."
    echo ""
    echo "⚠️  Prerequisiti:"
    echo "   • Connessione SSH configurata per osm2caiProd"
    echo "   • rsync installato"
    echo "   • ~600MB di spazio libero"
    echo ""
    echo "🔧 Configurazione SSH (se necessario):"
    echo "   Aggiungere al file ~/.ssh/config:"
    echo "   Host osm2caiProd"
    echo "       Hostname 116.202.26.149"
    echo "       User root"
    echo "       IdentityFile ~/.ssh/id_rsa"
    echo ""
    echo "   Poi configurare la chiave SSH:"
    echo "   ssh-copy-id root@116.202.26.149"
    echo ""
}

# Parsing dei parametri
while [[ $# -gt 0 ]]; do
    case $1 in
        --help|-h)
            show_help
            exit 0
            ;;
        *)
            print_error "Parametro non riconosciuto: $1"
            echo ""
            show_help
            exit 1
            ;;
    esac
done

# Determina la directory root del progetto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../" && pwd)"

# Verifica prerequisiti
print_step "Verifica prerequisiti..."

# Controlla se rsync è disponibile
if ! command -v rsync &> /dev/null; then
    print_error "rsync non è installato!"
    exit 1
fi

# Controlla se ssh è configurato per osm2caiProd
if ! ssh -o ConnectTimeout=5 osm2caiProd "echo 'Connessione SSH OK'" &> /dev/null; then
    print_error "Connessione SSH a osm2caiProd non disponibile!"
    print_error "Configurare SSH per osm2caiProd nel file ~/.ssh/config:"
    print_error "Host osm2caiProd"
    print_error "    Hostname 116.202.26.149"
    print_error "    User root"
    print_error "    IdentityFile ~/.ssh/id_rsa"
    print_error ""
    print_error "Poi configurare la chiave SSH:"
    print_error "ssh-copy-id root@116.202.26.149"
    exit 1
fi

print_success "Prerequisiti verificati"

# FASE: Download Dump da Produzione
print_step "=== FASE: DOWNLOAD DUMP DA PRODUZIONE ==="

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

print_success "=== FASE COMPLETATA: Dump da produzione sincronizzato ==="

echo ""
print_success "🎉 SYNC DUMP DA PRODUZIONE COMPLETATO CON SUCCESSO!"
echo "========================================================"
echo ""
print_step "📋 Riepilogo operazioni:"
print_step "   ✅ Dump scaricato da osm2caiProd"
print_step "   ✅ File salvato in: $PROJECT_ROOT/storage/app/backups/dump.sql.gz"
print_step "   ✅ Integrità file verificata"
echo ""
print_step "📁 Il dump è ora pronto per essere utilizzato dagli script di setup"
echo ""
