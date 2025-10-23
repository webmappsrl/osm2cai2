#!/bin/bash

# Script backup-db.sh - Esegue backup del database e crea link simbolico
# Autore: Sistema di backup osm2cai2
# Data: $(date)

echo "=== Script backup-db.sh - Backup Database ==="
echo "Inizio esecuzione: $(date)"
echo ""

# Esegui il backup del database
echo "1. Esecuzione backup database..."
docker exec php81_osm2cai2 php artisan wm:backup-run --only-db

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

# Crea il link simbolico
echo "3. Creazione link simbolico..."
# Rimuovi il link esistente se presente
if [ -L "storage/app/backups/dump.sql.gz" ]; then
    rm storage/app/backups/dump.sql.gz
    echo "   Link esistente rimosso"
fi

# Crea il nuovo link simbolico
ln -s ../../backups/last_dump.sql.gz storage/app/backups/dump.sql.gz

if [ $? -eq 0 ]; then
    echo "✅ Link simbolico creato: storage/app/backups/dump.sql.gz -> storage/backups/last_dump.sql.gz"
else
    echo "❌ Errore nella creazione del link simbolico"
    exit 1
fi

echo ""
echo "=== Script completato con successo ==="
echo "Fine esecuzione: $(date)"
echo ""
echo "File disponibili:"
echo "- Backup originale: storage/backups/last_dump.sql.gz"
echo "- Link simbolico: storage/app/backups/dump.sql.gz"
