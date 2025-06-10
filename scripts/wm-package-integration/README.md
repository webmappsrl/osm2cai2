# WM-Package Integration - Script e Documentazione OSM2CAI2

Questa directory contiene tutti gli script per l'integrazione di WM-Package con OSM2CAI2, inclusi script per la gestione di layer di accatastamento e associazione hiking routes.

## 📂 Struttura degli Script

### 🎯 Script Principale (Orchestratore)

**`wm-package-integration.sh`** - *Script principale completo*
- Setup ambiente Docker completo
- Migrazioni database e import app da Geohub
- Configurazione servizi (MinIO, Elasticsearch, Scout)
- Indicizzazione automatica e alias Elasticsearch
- Avvio servizi finali (Laravel + Horizon)

### 📋 Sub-script Numerati (Ordine di Esecuzione)

Gli script seguenti si trovano nella sottocartella `scripts/` e vengono eseguiti nell'ordine numerico dal script principale o possono essere lanciati singolarmente:

**`scripts/01-import-app-from-geohub.sh`** - *Import App da Geohub*
- Import automatico app da Geohub (default: app ID 26)
- Supporta ID app personalizzato come parametro
- Verifica e attesa processamento code
- Gestione errori e fallback alternativi

**`scripts/02-create-layers-app26.sh`** - *Creazione Layer di Accatastamento*
- Crea layer per gli stati di accatastamento (1, 2, 3, 4) per l'app Geohub (ID 26)
- Include anche associazione automatica hiking routes (opzionale)
- Colori predefiniti per ogni stato
- Supporto per dry-run e force

**`scripts/03-associate-routes-app26.sh`** - *Associazione Hiking Routes ai Layer*
- Associa le hiking routes ai layer corrispondenti basandosi su osm2cai_status
- Filtraggio per stato specifico (1, 2, 3, 4)
- Modalità dry-run per test
- Gestione batch per performance

**`scripts/04-enable-scout-automatic-indexing.sh`** - *Abilitazione Scout/Elasticsearch*
- Configura Laravel Scout per Elasticsearch
- Abilita indicizzazione automatica sui modelli
- Configurazione Redis per Horizon

**`scripts/05-fix-elasticsearch-alias.sh`** - *Fix Alias Elasticsearch*
- Crea alias `ec_tracks` per compatibilità API
- Risolve problemi di indicizzazione
- Verifica stato indici Elasticsearch

**`scripts/06-reset-database-from-dump.sh`** - *Reset Database da Backup*
- Cancella completamente il database attuale
- Ricarica il dump di backup
- Riavvia tutti i servizi
- Include conferma di sicurezza

## 🎨 Sistema Layer di Accatastamento

Gli script `02` e `03` gestiscono un sistema di layer colorati per gli stati di accatastamento:

| Stato | Nome | Colore | Hex | Descrizione |
|-------|------|--------|-----|-------------|
| 1 | Stato Accatastamento 1 | Giallo | #F2C511 | Sentieri con stato di accatastamento 1 |
| 2 | Stato Accatastamento 2 | Viola | #8E43AD | Sentieri con stato di accatastamento 2 |
| 3 | Stato Accatastamento 3 | Blu | #2980B9 | Sentieri con stato di accatastamento 3 |
| 4 | Stato Accatastamento 4 | Verde | #27AF60 | Sentieri con stato di accatastamento 4 |

## 🚀 Esempi di Utilizzo

### Setup Completo (Tutto in Automatico)
```bash
# Integrazione completa WM-Package
./scripts/wm-package-integration/wm-package-integration.sh
```

### Gestione Layer Manuale

**Creazione Layer (con associazione automatica):**
```bash
# Setup completo layer per app 26
./scripts/wm-package-integration/scripts/02-create-layers-app26.sh

# Solo creazione layer (senza associazione)
./scripts/wm-package-integration/scripts/02-create-layers-app26.sh --skip-association

# Ricrea layer sovrascrivendo esistenti
./scripts/wm-package-integration/scripts/02-create-layers-app26.sh --force

# Test senza salvare modifiche
./scripts/wm-package-integration/scripts/02-create-layers-app26.sh --dry-run

# Per app diversa
./scripts/wm-package-integration/scripts/02-create-layers-app26.sh --app=123
```

**Associazione Hiking Routes:**
```bash
# Associa tutte le hiking routes per app 26
./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh

# Solo hiking routes con stato specifico
./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh --status=3

# Test senza salvare
./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh --dry-run

# Combinazione parametri
./scripts/wm-package-integration/scripts/03-associate-routes-app26.sh --status=2 --dry-run
```

### Import App Specifica
```bash
# Import app 26 (Geohub) - default
./scripts/wm-package-integration/scripts/01-import-app-from-geohub.sh

# Import app con ID personalizzato
./scripts/wm-package-integration/scripts/01-import-app-from-geohub.sh 24
```

### Manutenzione Elasticsearch
```bash
# Abilita indicizzazione automatica
./scripts/wm-package-integration/scripts/04-enable-scout-automatic-indexing.sh

# Fix alias Elasticsearch
./scripts/wm-package-integration/scripts/05-fix-elasticsearch-alias.sh
```

### Reset Database (Attenzione!)
```bash
# Reset completo da backup (con conferma)
./scripts/wm-package-integration/scripts/06-reset-database-from-dump.sh
```

## 🔧 Prerequisiti

- **Docker**: Container OSM2CAI2 in esecuzione (`php81_osm2cai2`)
- **Database**: PostgreSQL con dati OSM2CAI2
- **Servizi**: Elasticsearch, Redis attivi
- **App 26**: App Geohub deve esistere per script layer specifici
- **Configurazione Geohub**: Database Geohub configurato nel `.env`

Per avviare l'ambiente:
```bash
./scripts/dev-setup.sh
```

### 🗄️ Configurazione Database Geohub

Per gli script di import da Geohub (script `01`), è necessario configurare la connessione al database Geohub nel file `.env`:

**Variabili richieste:**
```bash
# Geohub Database Configuration
GEOHUB_DB_HOST=your-geohub-host
GEOHUB_DB_PORT=5432
GEOHUB_DB_DATABASE=geohub
GEOHUB_DB_USERNAME=your-username
GEOHUB_DB_PASSWORD=your-password
```

**⚠️ IMPORTANTE per ambiente locale:**
Se esegui il progetto in locale con Docker, usa:
```bash
GEOHUB_DB_HOST=host.docker.internal
```

**Dopo la configurazione:**
```bash
# Pulisci cache configurazione
docker exec php81_osm2cai2 php artisan config:clear
docker exec php81_osm2cai2 php artisan config:cache

# Riavvia Horizon
docker exec php81_osm2cai2 php artisan horizon:terminate
docker exec -d php81_osm2cai2 php artisan horizon
```

**Test connessione:**
```bash
docker exec php81_osm2cai2 php artisan tinker --execute="try { DB::connection('geohub')->getPdo(); echo 'Connessione geohub OK'; } catch(Exception \$e) { echo 'Errore: ' . \$e->getMessage(); }"
```

## 📊 Workflow Completo Raccomandato

```bash
# 1. Avvia ambiente di sviluppo
./scripts/dev-setup.sh

# 2. Integrazione WM-Package completa
./scripts/wm-package-integration/wm-package-integration.sh

# 3. (Opzionale) Gestione manuale layer se necessario
./scripts/wm-package-integration/scripts/02-create-layers-app26.sh --force

# 4. (Opzionale) Verifica/Fix Elasticsearch
./scripts/wm-package-integration/scripts/05-fix-elasticsearch-alias.sh
```

## ⚠️ Note Importanti

### Sicurezza e Backup
- **Backup automatico**: Gli script mantengono backup automatici
- **Conferme utente**: Script critici richiedono conferma esplicita
- **Dry-run**: Usa `--dry-run` per testare senza modifiche

### App ID e Configurazioni
- **App Geohub**: ID predefinito 26 negli script layer
- **Personalizzazione**: Usa `--app=ID` per app diverse
- **Layer**: Vengono creati automaticamente con colori predefiniti

### Performance
- **Batch processing**: Gli script gestiscono grandi quantità di dati
- **Background**: Import e indicizzazione avvengono in background tramite Horizon
- **Timeout**: Script con timeout adeguati per operazioni lunghe

## 🐛 Troubleshooting

### 🛑 Gestione Errori (IMPORTANTE)

**Tutti gli script utilizzano strict mode** (`set -e`) e si **fermano immediatamente** in caso di errore:

- ✅ **Comportamento sicuro**: Nessun comando viene eseguito se uno precedente fallisce
- ✅ **Errori visibili**: Segnalazione immediata con riga e comando dell'errore
- ✅ **Stato consistente**: Il sistema rimane in uno stato valido

**Se uno script si ferma:**
1. 📖 **Leggi il messaggio di errore** completo mostrato
2. 🔧 **Risolvi la causa** dell'errore prima di riprovare  
3. ❌ **NON ignorare** gli errori - potrebbero compromettere l'integrità

### Problemi Comuni

**"Container non in esecuzione":**
```bash
./scripts/dev-setup.sh
```

**"App non trovata":**
```bash
# Verifica che l'app 26 esista o usa un ID diverso
./scripts/wm-package-integration/scripts/01-import-app-from-geohub.sh 26
```

**"Layer già esistenti":**
```bash
# Usa --force per sovrascrivere
./scripts/wm-package-integration/scripts/02-create-layers-app26.sh --force
```

**Problemi Elasticsearch:**
```bash
# Fix alias e indici
./scripts/wm-package-integration/scripts/05-fix-elasticsearch-alias.sh
```

### Log e Monitoraggio
```bash
# Log Laravel
docker exec php81_osm2cai2 tail -f storage/logs/laravel.log

# Status Horizon
docker exec php81_osm2cai2 php artisan horizon:status

# Stato container
docker ps | grep osm2cai2
```

## 📚 Documentazione Aggiuntiva

- **`link-wmpackage-to-osm2cai2.md`**: Documentazione tecnica completa (34KB)
- **Script help**: Ogni script ha `--help` per opzioni specifiche
- **Log dettagliati**: Output colorato con progresso di ogni fase

---

💡 **Suggerimento**: Per un primo setup completo, esegui semplicemente `./scripts/wm-package-integration/wm-package-integration.sh` che orchestrerà tutto automaticamente! 