#!/bin/bash

# Script per migrare i media degli Hiking Routes per renderli compliant con wm-package
# Questo script esegue l'aggiornamento dei media con app_id, user_id, geometry
# e li trasferisce su AWS (wmfe disk), poi pulisce i file locali

set -e
set -o pipefail

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzioni per stampe colorate
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
    print_error "• Log container: docker-compose logs ${PHP_CONTAINER}"
    print_error "• Log Laravel: docker exec ${PHP_CONTAINER} tail -f storage/logs/laravel.log"
    exit 1
}

# Imposta trap per gestire errori
trap 'handle_error $LINENO' ERR

# Carica le variabili dal file .env se esiste
# Necessario per i messaggi di errore che contengono il nome del container
if [ -f "/var/www/html/osm2cai2/.env" ]; then
    set -o allexport
    source "/var/www/html/osm2cai2/.env"
    set +o allexport
    PHP_CONTAINER="php81-${APP_NAME}"
else
    # Fallback nel caso in cui lo script venga eseguito in un ambiente non standard
    PHP_CONTAINER="php81-osm2cai2dev"
fi

echo "🖼️ Migrazione Media Hiking Routes per WM-Package Compliance"
echo "==========================================================="
echo ""

print_step "Esecuzione all'interno del container PHP..."

# Verifica configurazione AWS S3/MinIO
print_step "Verificando configurazione storage..."
STORAGE_CHECK=$(php artisan tinker --execute="echo config('filesystems.disks.wmfe.driver');" 2>/dev/null || echo "error")

if [[ "$STORAGE_CHECK" == *"s3"* ]]; then
    print_success "Storage S3/MinIO configurato correttamente"
elif [[ "$STORAGE_CHECK" == *"local"* ]]; then
    print_warning "Storage configurato come locale, potrebbero essere necessarie configurazioni aggiuntive"
else
    print_error "Impossibile verificare la configurazione storage wmfe"
    print_error "Verifica la configurazione del disco 'wmfe' in config/filesystems.php"
    exit 1
fi

OPERATION_PARAM="${1:-}" # Parametro per la modalità non interattiva

# Menu interattivo o selezione automatica
if [ -z "$OPERATION_PARAM" ]; then
    echo ""
    print_step "Operazioni disponibili:"
    echo "1) Migra solo i media (aggiorna app_id, user_id, geometry e trasferisce su AWS)"
    echo "2) Pulisci solo i file locali (per media già migrati)"
    echo "3) Migrazione completa (migra + pulizia)"
    echo "4) Esci"
    echo ""
    read -p "Seleziona un'opzione [1-4]: " choice

    case $choice in
        1)
            OPERATION="migrate"
            ;;
        2)
            OPERATION="cleanup"
            ;;
        3)
            OPERATION="full"
            ;;
        4)
            print_step "Operazione annullata dall'utente"
            exit 0
            ;;
        *)
            print_error "Opzione non valida!"
            exit 1
            ;;
    esac
else
    OPERATION=$OPERATION_PARAM
    print_step "Modalità non interattiva, operazione selezionata: $OPERATION"
    if [[ "$OPERATION" != "migrate" && "$OPERATION" != "cleanup" && "$OPERATION" != "full" ]]; then
        print_error "Operazione non valida: $OPERATION. Usare 'migrate', 'cleanup' o 'full'."
        exit 1
    fi
fi

# Funzione per eseguire la migrazione dei media
migrate_media() {
    print_step "=== FASE 1: MIGRAZIONE MEDIA ==="
    print_step "Aggiornamento dei media con app_id, user_id, geometry e trasferimento su AWS..."
    
    # Esegue il comando di aggiornamento media
    ARTISAN_COMMAND="php artisan osm2cai:update-hiking-route-media"
    
    print_step "Eseguendo comando: $ARTISAN_COMMAND"
    if $ARTISAN_COMMAND; then
        print_success "Migrazione media completata con successo!"
    else
        print_error "Errore durante la migrazione dei media!"
        return 1
    fi
}

# Funzione per pulire i file locali
cleanup_local_media() {
    print_step "=== FASE 2: PULIZIA FILE LOCALI ==="
    print_step "Eliminazione delle cartelle locali per i media migrati su AWS..."
    
    local do_cleanup=false
    if [ -z "$OPERATION_PARAM" ]; then
        # Modalità interattiva
        echo ""
        print_warning "ATTENZIONE: Questa operazione eliminerà definitivamente i file locali dei media già migrati su AWS."
        print_warning "Assicurati che la migrazione sia stata completata con successo prima di procedere."
        echo ""
        read -p "Continuare con la pulizia? [y/N]: " confirm
        if [[ $confirm =~ ^[Yy]$ ]]; then
            do_cleanup=true
        fi
    else
        # Modalità non interattiva: la pulizia è implicita per 'full' e 'cleanup'
        if [[ "$OPERATION" == "full" || "$OPERATION" == "cleanup" ]]; then
            print_warning "Modalità non interattiva: la pulizia verrà eseguita automaticamente."
            do_cleanup=true
        fi
    fi
    
    if $do_cleanup; then
        # Esegue il comando di pulizia
        ARTISAN_COMMAND="php artisan osm2cai:cleanup-local-hr-media"
        
        print_step "Eseguendo comando: $ARTISAN_COMMAND"
        if $ARTISAN_COMMAND; then
            print_success "Pulizia file locali completata con successo!"
        else
            print_error "Errore durante la pulizia dei file locali!"
            return 1
        fi
    else
        print_step "Pulizia annullata dall'utente"
    fi
}

# Esecuzione delle operazioni richieste
case $OPERATION in
    "migrate")
        migrate_media
        ;;
    "cleanup")
        cleanup_local_media
        ;;
    "full")
        migrate_media
        echo ""
        cleanup_local_media
        ;;
esac

echo ""
print_success "🎉 Operazione completata!"
echo ""
print_step "📊 Informazioni utili:"
print_step "• Verifica log Laravel: tail -f storage/logs/laravel.log"
print_step "• Stato storage: php artisan storage:link"
print_step "• Test connessione S3: php artisan tinker --execute=\"Storage::disk('wmfe')->files();\""
echo "" 