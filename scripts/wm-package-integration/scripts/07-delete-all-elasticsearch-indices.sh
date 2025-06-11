#!/bin/bash

# Script per cancellare tutti gli indici di Elasticsearch
# ATTENZIONE: Operazione distruttiva e irreversibile!

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# URL Elasticsearch - rileva automaticamente se eseguito dal container Docker
if [[ -f "/.dockerenv" ]]; then
    # Eseguito dall'interno del container Docker
    ELASTICSEARCH_URL="http://elasticsearch:9200"
else
    # Eseguito dall'host
    ELASTICSEARCH_URL="http://localhost:9200"
fi

# Variabili
DRY_RUN=false
FORCE=false
INCLUDE_SYSTEM=false

# Funzione di help
show_help() {
    echo -e "${BLUE}🗑️  Script Cancellazione Indici Elasticsearch${NC}"
    echo -e "${BLUE}=================================================${NC}"
    echo
    echo -e "${YELLOW}ATTENZIONE: Questo script cancella TUTTI gli indici di Elasticsearch!${NC}"
    echo -e "${RED}⚠️  OPERAZIONE IRREVERSIBILE! ⚠️${NC}"
    echo
    echo "Utilizzo: $0 [opzioni]"
    echo
    echo "Opzioni:"
    echo "  --dry-run           Mostra cosa verrà cancellato senza eseguire"
    echo "  --force             Salta le conferme di sicurezza"
    echo "  --include-system    Include anche gli indici di sistema (.*)"
    echo "  --help              Mostra questo help"
    echo
    echo "Esempi:"
    echo "  $0 --dry-run        # Test senza cancellare"
    echo "  $0                  # Cancellazione interattiva"
    echo "  $0 --force          # Cancellazione automatica"
    echo
    echo -e "${RED}⚠️  IMPORTANTE: Assicurati di avere backup prima di procedere!${NC}"
}

# Parse parametri
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --include-system)
            INCLUDE_SYSTEM=true
            shift
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Parametro sconosciuto: $1${NC}"
            show_help
            exit 1
            ;;
    esac
done

# Funzione per verificare se Elasticsearch è raggiungibile
check_elasticsearch() {
    echo -e "${BLUE}🔍 Verifica connessione Elasticsearch...${NC}"
    
    if ! curl -s "$ELASTICSEARCH_URL/_cluster/health" > /dev/null; then
        echo -e "${RED}❌ Elasticsearch non raggiungibile su $ELASTICSEARCH_URL${NC}"
        echo -e "${YELLOW}💡 Verifica che il container sia in esecuzione${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}✅ Elasticsearch raggiungibile${NC}"
}

# Funzione per ottenere la lista degli indici
get_indices() {
    if [[ "$INCLUDE_SYSTEM" == "false" ]]; then
        # Escludi indici di sistema (quelli che iniziano con .)
        curl -s "$ELASTICSEARCH_URL/_cat/indices?h=index" | \
        grep -v "^\." | \
        sort
    else
        # Include tutti gli indici
        curl -s "$ELASTICSEARCH_URL/_cat/indices?h=index" | \
        sort
    fi
}

# Funzione per mostrare gli indici che verranno cancellati
show_indices_to_delete() {
    local indices=("$@")
    
    if [[ ${#indices[@]} -eq 0 ]]; then
        echo -e "${YELLOW}⚠️  Nessun indice trovato da cancellare${NC}"
        return 1
    fi
    
    echo -e "${PURPLE}📋 Indici che verranno cancellati:${NC}"
    echo
    for index in "${indices[@]}"; do
        # Ottieni statistiche indice (semplificato, senza jq)
        local stats_response=$(curl -s "$ELASTICSEARCH_URL/$index/_stats" | grep -o '"count":[0-9]*' | head -1 | cut -d':' -f2)
        local stats=${stats_response:-"N/A"}
        echo -e "   ${RED}🗑️  $index${NC} (documenti: $stats)"
    done
    echo
    echo -e "${YELLOW}Totale indici: ${#indices[@]}${NC}"
}

# Funzione per richiedere conferma
ask_confirmation() {
    if [[ "$FORCE" == "true" ]]; then
        return 0
    fi
    
    echo -e "${RED}⚠️  ATTENZIONE: Stai per cancellare TUTTI gli indici mostrati sopra!${NC}"
    echo -e "${RED}⚠️  Questa operazione è IRREVERSIBILE!${NC}"
    echo
    
    read -p "$(echo -e ${YELLOW}Digita 'CANCELLA TUTTO' per confermare: ${NC})" confirmation
    
    if [[ "$confirmation" != "CANCELLA TUTTO" ]]; then
        echo -e "${GREEN}✅ Operazione annullata${NC}"
        exit 0
    fi
    
    echo
    echo -e "${RED}⚠️  ULTIMA CONFERMA: Sei ASSOLUTAMENTE SICURO?${NC}"
    read -p "$(echo -e ${YELLOW}Digita 'SI' per procedere: ${NC})" final_confirmation
    
    if [[ "$final_confirmation" != "SI" ]]; then
        echo -e "${GREEN}✅ Operazione annullata${NC}"
        exit 0
    fi
}

# Funzione per cancellare un indice
delete_index() {
    local index="$1"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${YELLOW}[DRY-RUN]${NC} Cancellerebbe: $index"
        return 0
    fi
    
    echo -e "${RED}🗑️  Cancellazione $index...${NC}"
    
    local response=$(curl -s -w "%{http_code}" -o /dev/null -X DELETE "$ELASTICSEARCH_URL/$index")
    
    if [[ "$response" == "200" ]]; then
        echo -e "${GREEN}✅ $index cancellato${NC}"
    else
        echo -e "${RED}❌ Errore cancellazione $index (HTTP: $response)${NC}"
    fi
}

# Funzione principale
main() {
    echo -e "${RED}🗑️  Cancellazione Indici Elasticsearch${NC}"
    echo -e "${RED}======================================${NC}"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${YELLOW}🧪 Modalità DRY-RUN attiva - nessuna modifica verrà applicata${NC}"
        echo
    fi
    
    # Verifica Elasticsearch
    check_elasticsearch
    
    # Ottieni lista indici
    echo -e "${BLUE}📋 Ricerca indici...${NC}"
    
    # Leggi gli indici in un array senza usare mapfile
    indices=()
    while IFS= read -r line; do
        [[ -n "$line" ]] && indices+=("$line")
    done < <(get_indices)
    
    # Mostra indici da cancellare
    show_indices_to_delete "${indices[@]}" || exit 0
    
    # Richiedi conferma (se non in dry-run)
    if [[ "$DRY_RUN" == "false" ]]; then
        ask_confirmation
        echo
        echo -e "${RED}🚀 Inizio cancellazione...${NC}"
        echo
    fi
    
    # Cancella ogni indice
    local deleted_count=0
    local error_count=0
    
    for index in "${indices[@]}"; do
        delete_index "$index"
        if [[ $? -eq 0 ]]; then
            ((deleted_count++))
        else
            ((error_count++))
        fi
    done
    
    echo
    echo -e "${BLUE}📊 Riepilogo:${NC}"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        echo -e "${YELLOW}   Indici che verrebbero cancellati: ${#indices[@]}${NC}"
    else
        echo -e "${GREEN}   Indici cancellati: $deleted_count${NC}"
        if [[ $error_count -gt 0 ]]; then
            echo -e "${RED}   Errori: $error_count${NC}"
        fi
    fi
    
    if [[ "$DRY_RUN" == "false" && $deleted_count -gt 0 ]]; then
        echo
        echo -e "${GREEN}✅ Cancellazione completata!${NC}"
        echo -e "${BLUE}🔍 Verifica stato cluster:${NC}"
        curl -s "$ELASTICSEARCH_URL/_cluster/health?pretty"
    fi
}

# Esegui solo se non sourcato
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi 