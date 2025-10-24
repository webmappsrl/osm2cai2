#!/bin/bash

# Script backup-db.sh - Esegue backup del database e crea link simbolico
# Autore: Sistema di backup osm2cai2
# Data: $(date)

echo "=== Script backup-db.sh - Backup Database ==="
echo "Inizio esecuzione: $(date)"
echo ""

# Esegui il backup del database
echo "1. Esecuzione backup database..."
docker exec postgres_osm2cai2 pg_dump -U osm2cai2 osm2cai2 | gzip > storage/app/backups/dump.sql.gz

if [ $? -eq 0 ]; then
    echo "✅ Backup completato con successo"
else
    echo "❌ Errore durante il backup"
    exit 1
fi

echo ""

# Crea la cartella storage/app/backups se non esiste
echo "2. Creazione cartella storage/app/backups..."
mkdir -p storage/app/backups

if [ $? -eq 0 ]; then
    echo "✅ Cartella storage/app/backups creata/verificata"
else
    echo "❌ Errore nella creazione della cartella"
    exit 1
fi

echo ""

# Verifica che il file di backup sia stato creato
echo "3. Verifica file di backup..."
if [ -f "storage/app/backups/dump.sql.gz" ]; then
    echo "✅ File di backup creato: storage/app/backups/dump.sql.gz"
    ls -lh storage/app/backups/dump.sql.gz
else
    echo "❌ File di backup non trovato"
    exit 1
fi

echo ""
echo "=== Script completato con successo ==="
echo "Fine esecuzione: $(date)"
echo ""
echo "File disponibili:"
echo "- Backup database: storage/app/backups/dump.sql.gz"
