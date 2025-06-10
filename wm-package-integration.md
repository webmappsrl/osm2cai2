# WM-Package Integration - Documentazione e Script di Installazione

Questo documento contiene tutte le modifiche effettuate al progetto OSM2CAI2 per collegare WMPackage e fornisce script sicuri per aggiornare l'ambiente di sviluppo esistente.

## 🎯 **Workflow per Sviluppatori**:

### Opzione 1: WM-Package Integration (Sicuro) 
```bash
git pull
./wm-package-integration.sh
```
**Script per collegamento WMPackage**: Pulisce SOLO i container OSM2CAI2, non tocca altri progetti Docker.

### Opzione 2: Solo Migrazioni (Ultra Sicuro)
```bash
git pull
./wm-package-integration-migrations-only.sh  
```
**Script super sicuro**: Applica solo migrazioni e configurazioni, zero impatto su container esistenti.

## 🔒 Sicurezza Container Docker

**⚠️ ATTENZIONE**: Lo script originale è stato corretto per evitare interferenze con altri progetti Docker.

### Script Disponibili:

1. **`wm-package-integration.sh`** (Raccomandato)
   - ✅ Pulisce **SOLO** container OSM2CAI2 specifici
   - ✅ Non tocca altri progetti Docker
   - ✅ Collegamento WMPackage e ambiente completo 

2. **`wm-package-integration-migrations-only.sh`** (Ultra Sicuro)
   - ✅ Zero impatto su container esistenti
   - ✅ Solo migrazioni e configurazioni
   - ✅ Ideale se hai altri progetti attivi

### Gestione Automatica:
- ✅ **Fase 1**: Setup ambiente Docker completo
- ✅ **Fase 2**: Migrazioni database e import GeoCMS
- ✅ **Fase 3**: Configurazione servizi (MinIO, etc.)
- ✅ **Fase 4**: Apps, Layer e associazioni
- ✅ **Fase 5**: Setup Elasticsearch con indicizzazione
- ✅ **Fase 6**: Avvio servizi finali e test

## 📋 Riepilogo delle Modifiche Effettuate

### 1. Sistema di Apps e Layer
- **Nuovo modello App** (`app/Models/App.php`)
- **Nuovo modello Layer** (`app/Models/Layer.php`) 
- **Relazione polimorfa** tra Layer e HikingRoute tramite tabella `layerables`
- **Nuove migrazioni**:
  - `2025_06_04_131953_create_apps_table.php`
  - `2025_06_04_133024_create_layers_table.php`
  - `2025_01_05_000000_create_layerables_table.php`
  - `2025_01_06_000000_update_layerables_references_to_hiking_route.php`

### 2. Sistema di Tassonomie
- **Nuove tassonomie** per categorizzazione avanzata:
  - `taxonomy_activities` - Attività
  - `taxonomy_poi_types` - Tipi di POI
  - `taxonomy_targets` - Target/Obiettivi
  - `taxonomy_whens` - Quando/Stagionalità
- **Tabelle pivot** per relazioni many-to-many con entità esistenti
- **Migrazioni create**:
  - `2025_06_04_143244_create_taxonomy_activities_table.php`
  - `2025_06_04_143245_create_taxonomy_activityables_table.php`
  - `2025_06_04_143246_create_taxonomy_poi_typeables_table.php`
  - `2025_06_04_143247_create_taxonomy_poi_types_table.php`
  - `2025_06_04_143248_create_taxonomy_targetables_table.php`
  - `2025_06_04_143249_create_taxonomy_targets_table.php`
  - `2025_06_04_143250_create_taxonomy_whenables_table.php`
  - `2025_06_04_143251_create_taxonomy_whens_table.php`

### 3. Chiavi Esterne e Relazioni
- **Foreign keys** aggiunte per integrità referenziale:
  - `2025_06_04_143255_z_add_foreign_keys_to_app_layer_table.php`
  - `2025_06_04_143256_z_add_foreign_keys_to_apps_table.php`
  - `2025_06_05_105602_z_add_user_foreign_key_to_layers_table.php`
  - `2025_06_05_111420_z_add_foreign_keys_to_hiking_routes_table.php` - **Aggiunge app_id e imposta automaticamente app_id=1 per tutti i record esistenti**
  - Foreign keys per tutte le tabelle di tassonomia

### 4. Interfaccia Nova Aggiornata
- **Nuova risorsa Nova App** (`app/Nova/App.php`)
- **Nuova risorsa Nova Layer** (`app/Nova/Layer.php`)
- **Aggiornate risorse esistenti**:
  - `HikingRoute.php` - Aggiunta gestione layer e relazioni
  - `EcPoi.php` - Miglioramenti UI
  - `Municipality.php` - Ottimizzazioni
  - `OsmfeaturesResource.php` - Aggiornamenti
  - `Poles.php` - Miglioramenti

### 5. Sistema Layer di Accatastamento - Gestione Automatica Stati

#### 🎯 **Panoramica del Sistema**
Il sistema di layer di accatastamento permette di organizzare automaticamente i percorsi escursionistici (HikingRoute) in layer visuali basandosi sul campo `osm2cai_status`. Ogni stato ha un colore specifico per facilitare l'identificazione visiva.

#### 📊 **Stati e Colori**
Il sistema crea automaticamente 4 layer con questa configurazione:

| Stato | Colore | Codice Hex | Nome Layer |
|-------|--------|------------|------------|
| **Stato 1** | 🟣 Viola | `#8A2BE2` | "Stato Accatastamento 1" |
| **Stato 2** | 🔵 Blu | `#0000FF` | "Stato Accatastamento 2" |
| **Stato 3** | 🟢 Verde | `#008000` | "Stato Accatastamento 3" |
| **Stato 4** | 🔴 Rosso | `#FF0000` | "Stato Accatastamento 4" |

#### 🛠️ **Comandi Disponibili**

##### **1. Creazione Layer Stati**
```bash
# Creazione base (trova automaticamente l'app)
docker exec php81_osm2cai2 php artisan osm2cai:create-accatastamento-layers

# Con app specifica
docker exec php81_osm2cai2 php artisan osm2cai:create-accatastamento-layers --app=1

# Sovrascrivi layer esistenti
docker exec php81_osm2cai2 php artisan osm2cai:create-accatastamento-layers --force
```

**Cosa fa il comando:**
- Crea 4 layer con colori e nomi appropriati
- Associa i layer all'app specificata (default: trova automaticamente l'app sentieristica)
- Imposta proprietà `properties.stato_accatastamento` per identificazione
- Supporta nomi multilingue (italiano e inglese)

##### **2. Associazione HikingRoute ai Layer**
```bash
# Associazione completa (tutti gli stati)
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers

# Solo uno stato specifico
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers --status=4

# Modalità test (senza salvare)
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers --dry-run

# Con app specifica
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers --app=1
```

**Cosa fa il comando:**
- Associa ogni HikingRoute al layer corrispondente al suo `osm2cai_status`
- Rimuove associazioni esistenti prima di creare quelle nuove
- Elabora in batch per performance ottimali
- Supporta modalità test per verificare prima dell'esecuzione

#### 🚀 **Workflow Completo**

##### **Setup Iniziale**
```bash
# 1. Crea i layer (se non esistono)
docker exec php81_osm2cai2 php artisan osm2cai:create-accatastamento-layers

# 2. Associa tutte le hiking routes
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers

# 3. Verifica risultato
docker exec php81_osm2cai2 php artisan tinker --execute="
echo 'Layer creati: ' . \Wm\WmPackage\Models\Layer::where('properties->stato_accatastamento', '!=', null)->count();
echo 'Associazioni totali: ' . DB::table('layerables')->where('layerable_type', 'App\\\Models\\\HikingRoute')->count();
"
```

##### **Manutenzione e Aggiornamenti**
```bash
# Re-sincronizzazione dopo modifiche bulk
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers

# Solo percorsi con stato specifico modificato
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers --status=4

# Reset completo layer (ricrea da zero)
docker exec php81_osm2cai2 php artisan osm2cai:create-accatastamento-layers --force
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers
```

#### 🔧 **Gestione Avanzata**

##### **Verifica Configurazione**
```bash
# Lista layer di accatastamento
docker exec php81_osm2cai2 php artisan tinker --execute="
\Wm\WmPackage\Models\Layer::where('properties->stato_accatastamento', '!=', null)
->get(['id', 'name', 'color', 'properties'])
->each(function(\$layer) {
    echo 'Layer: ' . \$layer->name . ' - Colore: ' . \$layer->color . ' - Stato: ' . \$layer->properties['stato_accatastamento'] . PHP_EOL;
});
"

# Conta associazioni per stato
docker exec php81_osm2cai2 php artisan tinker --execute="
for(\$i = 1; \$i <= 4; \$i++) {
    \$count = \App\Models\HikingRoute::where('osm2cai_status', \$i)->count();
    echo 'Stato ' . \$i . ': ' . \$count . ' percorsi' . PHP_EOL;
}
"
```

##### **Risoluzione Problemi App**
```bash
# Se l'app non viene trovata automaticamente
docker exec php81_osm2cai2 php artisan tinker --execute="
echo 'App disponibili:' . PHP_EOL;
\Wm\WmPackage\Models\App::select('id', 'name', 'sku')->get()->each(function(\$app) {
    echo 'ID: ' . \$app->id . ' - Nome: ' . \$app->name . ' - SKU: ' . \$app->sku . PHP_EOL;
});
"

# Usa l'ID specifico trovato
docker exec php81_osm2cai2 php artisan osm2cai:create-accatastamento-layers --app=ID_TROVATO
```

#### 📈 **Monitoraggio e Statistiche**

##### **Statistiche Layer**
```bash
# Report completo stati e associazioni
docker exec php81_osm2cai2 php artisan tinker --execute="
echo '=== REPORT LAYER DI ACCATASTAMENTO ===' . PHP_EOL;
for(\$i = 1; \$i <= 4; \$i++) {
    \$layer = \Wm\WmPackage\Models\Layer::where('properties->stato_accatastamento', \$i)->first();
    \$routes_count = \App\Models\HikingRoute::where('osm2cai_status', \$i)->count();
    \$associations_count = \$layer ? \$layer->layerables()->where('layerable_type', 'App\\\Models\\\HikingRoute')->count() : 0;
    
    echo 'STATO ' . \$i . ':' . PHP_EOL;
    echo '  Layer: ' . (\$layer ? \$layer->name . ' (' . \$layer->color . ')' : 'NON TROVATO') . PHP_EOL;
    echo '  HikingRoute con stato: ' . \$routes_count . PHP_EOL;
    echo '  Associazioni attive: ' . \$associations_count . PHP_EOL;
    echo '  Sincronizzazione: ' . (\$routes_count == \$associations_count ? 'OK' : 'DA AGGIORNARE') . PHP_EOL;
    echo PHP_EOL;
}
"
```

##### **Performance e Ottimizzazioni**
```bash
# Indicizzazione per query veloci sui layer
docker exec php81_osm2cai2 php artisan tinker --execute="
echo 'Indici database per performance:' . PHP_EOL;
DB::statement('CREATE INDEX IF NOT EXISTS idx_hiking_routes_osm2cai_status ON hiking_routes(osm2cai_status)');
DB::statement('CREATE INDEX IF NOT EXISTS idx_layerables_type_id ON layerables(layerable_type, layerable_id)');
echo 'Indici creati con successo.' . PHP_EOL;
"
```

#### 🔗 **Integrazione con Sistema Layer**

##### **Relazioni Database**
- **Tabella `layers`**: Contiene i layer di accatastamento
- **Tabella `layerables`**: Relazione polimorfa Many-to-Many
- **Campo `properties`**: JSON con `stato_accatastamento` per identificazione
- **Campo `osm2cai_status`**: Chiave per associazione automatica

##### **Codice di Implementazione**
- **`CreateAccatastamentoLayersCommand`**: `app/Console/Commands/CreateAccatastamentoLayersCommand.php`
- **`AssociateHikingRoutesToLayersCommand`**: `app/Console/Commands/AssociateHikingRoutesToLayersCommand.php`
- **Modello Layer**: `app/Models/Layer.php` con relazioni polimorfiche
- **Migrazione layerables**: `database/migrations/create_layerables_table.php`

#### ❗ **Note Tecniche Importanti**

##### **Comportamento Sistema**
- I layer vengono identificati tramite `properties.stato_accatastamento`
- Le associazioni vengono salvate in `layerables` con relazione polimorfa
- Processing in batch per gestire grandi quantità di dati
- Nomi layer multilingue (supporto internazionalizzazione)

##### **Manutenzione Raccomandata**
- Eseguire associazione dopo import massivi di percorsi
- Verificare sincronizzazione dopo modifiche bulk a `osm2cai_status`
- Monitorare performance con molte migliaia di associazioni
- Backup before/after operazioni di reset layer

### 6. Configurazione Storage MinIO
- **Configurazione filesystems** (`config/filesystems.php`)
  - Aggiunto driver MinIO per object storage
  - Configurazioni bucket per sviluppo e produzione
- **Script setup MinIO** (`scripts/setup-minio-bucket.sh`)
- **Script test MinIO** (`scripts/test-minio-laravel.sh`)

### 7. Elasticsearch e Scout - Sistema di Ricerca e Indicizzazione

#### 🔍 **Panoramica del Sistema**
Il sistema integra Elasticsearch per fornire funzionalità di ricerca avanzata sui percorsi escursionistici (HikingRoute). La configurazione gestisce automaticamente l'indicizzazione e la compatibilità API.

#### 📊 **Problema di Compatibilità Risolto**
- `App\Models\HikingRoute` crea l'indice `hiking_routes`
- `Wm\WmPackage\Models\EcTrack` e API cercano l'indice `ec_tracks`
- **Soluzione**: Alias Elasticsearch `ec_tracks` → `hiking_routes`

#### ⚙️ **Configurazione Automatica**
- **Script abilitazione**: `scripts/enable-scout-automatic-indexing.sh`
- **Indicizzazione automatica** via queue Laravel
- **Alias automatico** `ec_tracks` per compatibilità API
- **Variables .env**: `SCOUT_QUEUE=true`, `SCOUT_DRIVER=elasticsearch`

#### 🚀 **Funzionalità Implementate**

##### **Indicizzazione Automatica**
Le seguenti operazioni attivano l'indicizzazione automatica:
- Creazione di un nuovo HikingRoute
- Modifica dei campi indicizzabili (nome, geometry, proprietà, taxonomie)
- Eliminazione di un HikingRoute
- Associazione/rimozione layer

##### **Campi Indicizzati**
```json
{
  "id": 123,
  "name": "Nome del percorso", 
  "start": [lng, lat],
  "end": [lng, lat],
  "app_id": 1,
  "taxonomyWheres": ["luogo1", "luogo2"],
  "taxonomyActivities": ["hiking", "mountain_bike"],
  "layers": [1, 2, 3],
  "searchable": "testo ricercabile combinato"
}
```

##### **Filtri di Indicizzazione**
Solo i HikingRoute che rispettano questi criteri vengono indicizzati:
- `geometry` non nullo e valido
- `osm2cai_status != 0` (percorsi attivi)
- Metodo `shouldBeSearchable()` in `HikingRoute.php`

#### 🛠️ **Comandi di Gestione**

##### **Indicizzazione Completa**
```bash
# Tutti i record validi
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php -d max_execution_time=3600 -d memory_limit=2G artisan scout:import 'App\\Models\\HikingRoute'"

# Con filtri specifici
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan scout:import 'App\\Models\\HikingRoute' --query='osm2cai_status = 4'"
```

##### **Gestione Indici**
```bash
# Flush completo (rimuove tutto)
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan scout:flush 'App\\Models\\HikingRoute'"

# Verifica stato indici
docker exec php81_osm2cai2 bash -c "curl -X GET 'elasticsearch:9200/_alias?pretty'"

# Verifica record indicizzati
docker exec php81_osm2cai2 bash -c "curl -X GET 'elasticsearch:9200/hiking_routes/_count'"
```

##### **Gestione Alias ec_tracks**
```bash
# Creazione automatica alias (fatto dallo script)
./scripts/fix-elasticsearch-alias.sh

# Creazione manuale alias
INDEX_NAME="hiking_routes_1234567890"
docker exec php81_osm2cai2 bash -c "curl -X POST 'elasticsearch:9200/_aliases' -H 'Content-Type: application/json' -d '{\"actions\":[{\"add\":{\"index\":\"$INDEX_NAME\",\"alias\":\"ec_tracks\"}}]}'"
```

#### 🔧 **Re-indicizzazione Mirata**

##### **Singolo Record**
```bash
# Re-indicizza record specifico (ID=123)
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php -r \"require 'bootstrap/app.php'; \\\$route = App\\\\Models\\\\HikingRoute::find(123); \\\$route->searchable();\""
```

##### **Record con Condizioni**
```bash
# Solo percorsi con stato specifico
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan scout:import 'App\\\\Models\\\\HikingRoute' --query='osm2cai_status = 4'"

# Solo percorsi modificati di recente
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan scout:import 'App\\\\Models\\\\HikingRoute' --query='updated_at > \"2024-01-01\"'"
```

#### 🧪 **Test e Verifica**

##### **Test API Elasticsearch**
```bash
# Test base API
curl -X GET "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1"

# Test con parametri di ricerca
curl -X GET "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1&query=hiking"

# Test alias ec_tracks
curl -X GET "http://localhost:9200/ec_tracks/_search?size=5"
```

##### **Verifica Configurazione**
```bash
# Stato cluster Elasticsearch
curl -X GET "http://localhost:9200/_cluster/health?pretty"

# Lista indici e alias
curl -X GET "http://localhost:9200/_alias?pretty"

# Conta record in indice
curl -X GET "http://localhost:9200/hiking_routes/_count"
curl -X GET "http://localhost:9200/ec_tracks/_count"
```

#### 📈 **Monitoraggio e Performance**

##### **Monitoraggio Indicizzazione**
```bash
# Processi Scout attivi
docker exec php81_osm2cai2 bash -c "ps aux | grep scout"

# Stato code Laravel
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan queue:work --once"

# Log Elasticsearch
docker logs elasticsearch_osm2cai2 --tail=50
```

##### **Performance e Ottimizzazioni**
- **Memory limit**: 2GB per indicizzazione completa
- **Timeout**: 3600 secondi per operazioni massive
- **Batch size**: Configurabile in `config/scout.php`
- **Queue**: Indicizzazione asincrona per performance migliori

#### ❗ **Note Importanti e Troubleshooting**

##### **Record Limitati**
Se vedi solo ~53 record indicizzati invece di 24.000+, è **normale**:
- Solo HikingRoute con `geometry` valida vengono indicizzati
- Solo quelli con `osm2cai_status != 0`
- Filtro applicato dal metodo `shouldBeSearchable()`

##### **Problemi Comuni**

**API restituisce errore 500:**
```bash
# Verifica alias ec_tracks
curl -X GET 'elasticsearch:9200/_alias/ec_tracks'

# Controlla log container
docker logs php81_osm2cai2 --tail=50
```

**Indicizzazione si blocca:**
```bash
# Aumenta memoria e timeout
php -d max_execution_time=7200 -d memory_limit=4G artisan scout:import 'App\\Models\\HikingRoute'

# Verifica spazio disco
docker exec elasticsearch_osm2cai2 df -h
```

**Alias perso dopo restart:**
```bash
# Ricrea alias automaticamente
./scripts/fix-elasticsearch-alias.sh
```

#### 🔄 **Backup e Restore Indici**

##### **Backup Configurazione**
```bash
# Salva mapping indice
curl -X GET 'elasticsearch:9200/hiking_routes/_mapping' > backup_mapping.json

# Salva settings
curl -X GET 'elasticsearch:9200/hiking_routes/_settings' > backup_settings.json
```

##### **Restore dopo Problemi**
```bash
# Re-indicizzazione completa
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan scout:flush 'App\\Models\\HikingRoute'"
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php -d max_execution_time=3600 -d memory_limit=2G artisan scout:import 'App\\Models\\HikingRoute'"

# Ricrea alias
./scripts/fix-elasticsearch-alias.sh
```

### 8. Docker Compose per Sviluppo
- **Nuovo file compose** (`develop.compose.yml`)
- **Servizi aggiuntivi**: MinIO, MailPit
- **Script automatizzati**:
  - `scripts/dev-setup.sh` - Setup ambiente di sviluppo
  - `scripts/dev-up.sh` - Avvio veloce

### 9. Aggiornamenti Configurazione
- **File .env-example** aggiornato con nuove variabili
- **Route Service Provider** (`app/Providers/RouteServiceProvider.php`)
- **Configurazione VSCode** (`.vscode/launch.json`)

## 🚀 Script di WM-Package Integration

**Scenario di utilizzo**: Dopo aver fatto `git pull` delle nuove modifiche, lo script:

### **Fase 1: Setup Ambiente Docker**
- Pulizia selettiva container OSM2CAI2
- Avvio container base e servizi
- Attesa che PostgreSQL sia completamente pronto
- Verifica servizi essenziali

### **Fase 2: Database e Migrazioni** 
- Applicazione nuove migrazioni al database
- **Import App dal GeoCMS** (wm-geohub:import --app=26)

### **Fase 3: Configurazione Servizi**
- Setup bucket MinIO

### **Fase 4: Apps e Layer**
- Verifica/creazione App di default (ID=1)
- Creazione layer di accatastamento
- Associazione hiking routes ai layer

### **Fase 5: Setup Elasticsearch**
- Abilitazione indicizzazione automatica Scout
- Indicizzazione iniziale dati
- Creazione alias ec_tracks per compatibilità API

### **Fase 6: Servizi Finali**
- Avvio server Laravel
- Avvio worker delle code
- Test finale servizi

```bash
#!/bin/bash

echo "🚀 Setup Link WMPackage to OSM2CAI2 - Ambiente di Sviluppo"
echo "=================================================="
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

print_success "Prerequisiti verificati"

# 1. Setup file .env
print_step "Setup file .env..."
if [ ! -f ".env" ]; then
    cp .env-example .env
    print_success "File .env creato da .env-example"
else
    print_warning "File .env già esistente"
fi

# 2. Creazione directory per volumi Docker
print_step "Creazione directory per volumi Docker..."
mkdir -p docker/volumes/minio/data
mkdir -p docker/volumes/postgresql/data
mkdir -p docker/volumes/elasticsearch/data
print_success "Directory volumi create"

# 3. Avvio container base
print_step "Avvio container base..."
docker-compose up -d
sleep 10

# 4. Avvio servizi di sviluppo
print_step "Avvio servizi di sviluppo (MinIO, MailPit)..."
docker-compose -f develop.compose.yml up -d
sleep 10

# 5. Verifica servizi
print_step "Verifica servizi..."

# PostgreSQL
if docker exec postgres_osm2cai2 pg_isready -h localhost -p 5432 &> /dev/null; then
    print_success "PostgreSQL attivo"
else
    print_warning "PostgreSQL non ancora pronto"
fi

# Elasticsearch
if curl -f -s http://localhost:9200/_cluster/health &> /dev/null; then
    print_success "Elasticsearch attivo"
else
    print_warning "Elasticsearch non ancora pronto"
fi

# MinIO
if curl -f -s http://localhost:9000/minio/health/live &> /dev/null; then
    print_success "MinIO attivo"
else
    print_warning "MinIO non ancora pronto"
fi

# 6. Ripristino database
print_step "Ripristino database da backup..."
if [ -f "storage/app/backups/dump.sql.gz" ]; then
    gunzip -c storage/app/backups/dump.sql.gz | docker exec -i postgres_osm2cai2 psql -U osm2cai2 -d osm2cai2
    print_success "Database ripristinato da backup"
else
    print_warning "File backup non trovato, skip ripristino database"
fi

# 7. Esecuzione migrazioni
print_step "Esecuzione nuove migrazioni..."
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan migrate --force"
print_success "Migrazioni eseguite"

# 8. Setup bucket MinIO
print_step "Setup bucket MinIO..."
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/setup-minio-bucket.sh"

# 9. Creazione layer di accatastamento
print_step "Creazione layer di accatastamento..."
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan osm2cai:create-accatastamento-layers"
print_success "Layer di accatastamento creati"

# 10. Associazione hiking routes ai layer
print_step "Associazione hiking routes ai layer..."
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan osm2cai:associate-hiking-routes-to-layers"
print_success "Hiking routes associati ai layer"

# 11. Setup Elasticsearch
print_step "Setup Elasticsearch e indicizzazione..."

# Aspetta che Elasticsearch sia completamente pronto
sleep 15

# Abilita indicizzazione automatica Scout
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && ./scripts/enable-scout-automatic-indexing.sh"

# Indicizzazione iniziale
docker exec php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php -d max_execution_time=3600 -d memory_limit=2G artisan scout:import 'App\\Models\\HikingRoute'"

# Creazione alias ec_tracks
INDEX_NAME=$(docker exec php81_osm2cai2 bash -c "curl -s 'elasticsearch:9200/_alias?pretty'" | grep -B2 '"hiking_routes"' | grep -o 'hiking_routes_[0-9]*' | head -1)

if [ ! -z "$INDEX_NAME" ]; then
    docker exec php81_osm2cai2 bash -c "curl -X POST 'elasticsearch:9200/_aliases' -H 'Content-Type: application/json' -d '{\"actions\":[{\"add\":{\"index\":\"$INDEX_NAME\",\"alias\":\"ec_tracks\"}}]}'"
    print_success "Alias ec_tracks creato per $INDEX_NAME"
else
    print_warning "Indice hiking_routes non trovato, alias non creato"
fi

# 12. Avvio Laravel server
print_step "Avvio server Laravel..."
docker exec -d php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan serve --host 0.0.0.0 --port 8000"
sleep 5

# 13. Avvio worker delle code
print_step "Avvio worker delle code..."
docker exec -d php81_osm2cai2 bash -c "cd /var/www/html/osm2cai2 && php artisan queue:work --daemon"

# 14. Test servizi
print_step "Test finale servizi..."

# Test Laravel
if curl -f -s http://localhost:8008 &> /dev/null; then
    print_success "Laravel attivo su http://localhost:8008"
else
    print_warning "Laravel potrebbe richiedere qualche secondo in più"
fi

# Test API Elasticsearch
if curl -f -s "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1" &> /dev/null; then
    print_success "API Elasticsearch funzionante"
else
    print_warning "API Elasticsearch potrebbe richiedere configurazione aggiuntiva"
fi

echo ""
echo "🎉 Setup Link WMPackage to OSM2CAI2 Completato!"
echo "======================================"
echo ""
echo "📋 Servizi Disponibili:"
echo "   • Applicazione: http://localhost:8008"
echo "   • Nova Admin: http://localhost:8008/nova"
echo "   • MinIO Console: http://localhost:9003 (minioadmin/minioadmin)"
echo "   • MailPit: http://localhost:8025"
echo "   • Elasticsearch: http://localhost:9200"
echo "   • PostgreSQL: localhost:5508"
echo ""
echo "🔧 Comandi Utili:"
echo "   • Accesso container PHP: docker exec -u 0 -it php81_osm2cai2 bash"
echo "   • Monitoraggio code: docker exec php81_osm2cai2 php artisan queue:monitor"
echo "   • Log Laravel: docker exec php81_osm2cai2 tail -f storage/logs/laravel.log"
echo "   • Test MinIO: ./scripts/test-minio-laravel.sh"
echo "   • Fix alias Elasticsearch: docker exec php81_osm2cai2 ./scripts/fix-elasticsearch-alias.sh"
echo ""
echo "🛑 Per fermare tutto:"
echo "   docker-compose down && docker-compose -f develop.compose.yml down"
echo ""
print_success "Ambiente di sviluppo pronto per l'uso!"
```

## 🔧 Comandi di Manutenzione

### Gestione Layer
```bash
# Crea layer di accatastamento
docker exec php81_osm2cai2 php artisan osm2cai:create-accatastamento-layers

# Associa hiking routes ai layer
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers

# Test associazioni (dry-run)
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers --dry-run

# Processa solo uno stato specifico
docker exec php81_osm2cai2 php artisan osm2cai:associate-hiking-routes-to-layers --status=4
```

### Gestione Elasticsearch
```bash
# Re-indicizzazione completa (crea indice hiking_routes)
docker exec php81_osm2cai2 php artisan scout:import 'App\Models\HikingRoute'

# Flush indice
docker exec php81_osm2cai2 php artisan scout:flush 'App\Models\HikingRoute'

# Verifica indici
curl -X GET 'localhost:9200/_cat/indices?v'

# Verifica alias (deve esistere ec_tracks -> hiking_routes)
curl -X GET 'localhost:9200/_alias?pretty'

# Ricreare alias ec_tracks se necessario
INDEX_NAME=$(curl -s 'localhost:9200/_alias?pretty' | grep -B2 '"hiking_routes"' | grep -o 'hiking_routes_[0-9]*' | head -1)
curl -X POST 'localhost:9200/_aliases' -H 'Content-Type: application/json' -d "{\"actions\":[{\"add\":{\"index\":\"$INDEX_NAME\",\"alias\":\"ec_tracks\"}}]}"

# Test ricerca tramite alias
curl -X GET "localhost:9200/ec_tracks/_search?size=1&pretty"
```

### Gestione Database
```bash
# Esegui migrazioni
docker exec php81_osm2cai2 php artisan migrate

# Rollback migrazioni
docker exec php81_osm2cai2 php artisan migrate:rollback

# Status migrazioni
docker exec php81_osm2cai2 php artisan migrate:status
```

### Gestione Code
```bash
# Avvia worker
docker exec php81_osm2cai2 php artisan queue:work

# Riavvia worker
docker exec php81_osm2cai2 php artisan queue:restart

# Monitora code
docker exec php81_osm2cai2 php artisan queue:monitor

# Clear code fallite
docker exec php81_osm2cai2 php artisan queue:flush
```

## 📁 Struttura File Modificati

```
osm2cai2/
├── app/
│   ├── Console/Commands/
│   │   ├── AssociateHikingRoutesToLayersCommand.php [NUOVO]
│   │   └── CreateAccatastamentoLayersCommand.php [NUOVO]
│   ├── Models/
│   │   ├── App.php [NUOVO]
│   │   ├── Layer.php [NUOVO]
│   │   └── HikingRoute.php [MODIFICATO]
│   ├── Nova/
│   │   ├── App.php [NUOVO]
│   │   ├── Layer.php [NUOVO]
│   │   ├── HikingRoute.php [MODIFICATO]
│   │   ├── EcPoi.php [MODIFICATO]
│   │   ├── Municipality.php [MODIFICATO]
│   │   ├── OsmfeaturesResource.php [MODIFICATO]
│   │   └── Poles.php [MODIFICATO]
│   └── Providers/
│       ├── NovaServiceProvider.php [MODIFICATO]
│       └── RouteServiceProvider.php [MODIFICATO]
├── database/migrations/
│   ├── 2025_06_04_131953_create_apps_table.php [NUOVO]
│   ├── 2025_06_04_133024_create_layers_table.php [NUOVO]
│   ├── 2025_01_05_000000_create_layerables_table.php [NUOVO]
│   ├── 2025_01_06_000000_update_layerables_references_to_hiking_route.php [NUOVO]
│   ├── 2025_06_05_111420_z_add_foreign_keys_to_hiking_routes_table.php [NUOVO]
│   ├── [14 nuove migrazioni per tassonomie] [NUOVO]
│   └── [7 altre migrazioni foreign keys] [NUOVO]
├── config/
│   └── filesystems.php [MODIFICATO]
├── scripts/
│   ├── dev-setup.sh [NUOVO]
│   ├── dev-up.sh [NUOVO]
│   ├── enable-scout-automatic-indexing.sh [NUOVO]
│   ├── fix-elasticsearch-alias.sh [NUOVO]
│   ├── setup-minio-bucket.sh [NUOVO]
│   └── test-minio-laravel.sh [NUOVO]
├── develop.compose.yml [NUOVO]


├── .env-example [MODIFICATO]
└── README.md [MODIFICATO]
```

## 🔍 Testing e Verifica

### Verifica Layer
```bash
# Controlla layer creati
docker exec php81_osm2cai2 php artisan tinker --execute="
\Wm\WmPackage\Models\Layer::select('id', 'name', 'color')->get();
"

# Controlla associazioni
docker exec php81_osm2cai2 php artisan tinker --execute="
\App\Models\HikingRoute::with('layers')->find(1);
"
```

### Verifica Elasticsearch
```bash
# Test API (cerca nell'alias ec_tracks che punta a hiking_routes)
curl "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1"

# Verifica indici e alias
curl -X GET 'localhost:9200/_alias?pretty'

# Verifica che l'alias ec_tracks punti all'indice hiking_routes
curl -X GET 'localhost:9200/ec_tracks/_search?size=1&pretty'

# Verifica conteggi
docker exec php81_osm2cai2 php artisan tinker --execute="
echo 'HikingRoute indicizzabili: ' . App\Models\HikingRoute::whereNotNull('geometry')->where('osm2cai_status', '!=', 0)->count();
"
```

### Verifica MinIO
```bash
# Test storage
./scripts/test-minio-laravel.sh

# Accesso console
# http://localhost:9003 (minioadmin/minioadmin)
```

## 🚨 Troubleshooting

### Container non si avvia
```bash
# Check log container
docker logs php81_osm2cai2
docker logs postgres_osm2cai2
docker logs elasticsearch_osm2cai2

# Restart completo
docker-compose down
docker-compose -f develop.compose.yml down
docker system prune -f
# Poi ri-esegui setup
```

### Elasticsearch non indicizza
```bash
# Verifica configurazione Scout
docker exec php81_osm2cai2 php artisan tinker --execute="
echo 'Scout driver: ' . config('scout.driver');
echo 'Scout queue: ' . config('scout.queue');
"

# Forza re-indicizzazione
docker exec php81_osm2cai2 php artisan scout:flush 'App\Models\HikingRoute'
docker exec php81_osm2cai2 php artisan scout:import 'App\Models\HikingRoute'
```

### API restituisce errore "ec_tracks index not found"
```bash
# Verifica che l'alias ec_tracks esista
curl -X GET 'localhost:9200/_alias/ec_tracks?pretty'

# Se l'alias non esiste, ricrealo
INDEX_NAME=$(curl -s 'localhost:9200/_alias?pretty' | grep -B2 '"hiking_routes"' | grep -o 'hiking_routes_[0-9]*' | head -1)
if [ ! -z "$INDEX_NAME" ]; then
    curl -X POST 'localhost:9200/_aliases' -H 'Content-Type: application/json' -d "{\"actions\":[{\"add\":{\"index\":\"$INDEX_NAME\",\"alias\":\"ec_tracks\"}}]}"
    echo "Alias ec_tracks creato per $INDEX_NAME"
else
    echo "Nessun indice hiking_routes trovato. Esegui prima l'indicizzazione."
fi

# Test che l'API funzioni
curl "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1"
```

### Database connection error
```bash
# Verifica PostgreSQL
docker exec postgres_osm2cai2 pg_isready
docker exec postgres_osm2cai2 psql -U osm2cai2 -d osm2cai2 -c "SELECT count(*) FROM hiking_routes;"
```

## 🔍 Gestione Alias Elasticsearch ec_tracks ↔ hiking_routes

### Problema
L'API `ElasticsearchController` cerca nell'indice `ec_tracks`, ma Laravel Scout indicizza i dati come `hiking_routes`. Per mantenere la compatibilità, usiamo un **alias Elasticsearch**.

### Soluzione Automatica
Lo script di setup crea automaticamente l'alias `ec_tracks` che punta all'indice `hiking_routes`.

### Script di Riparazione
Se l'alias si rompe, usa lo script dedicato:
```bash
docker exec php81_osm2cai2 ./scripts/fix-elasticsearch-alias.sh
```

### Verifica Manuale
```bash
# Verifica alias esistenti
curl -X GET 'localhost:9200/_alias?pretty'

# Test ricerca tramite alias
curl -X GET 'localhost:9200/ec_tracks/_search?size=1&pretty'

# Test API completa
curl "http://localhost:8008/api/v2/elasticsearch?app=geohub_app_1"
```

## 📝 Note Finali

1. **Backup automatico**: Il sistema mantiene backup automatici in `storage/app/backups/`
2. **Monitoraggio**: Usa MailPit (http://localhost:8025) per vedere le email di sistema
3. **Performance**: I worker delle code gestiscono l'indicizzazione automatica
4. **Security**: In produzione modifica le credenziali MinIO e altri servizi
5. **Logs**: Monitora sempre i log per errori: `docker exec php81_osm2cai2 tail -f storage/logs/laravel.log`
6. **App ID**: La migrazione imposta automaticamente `app_id = 1` per tutti i record `hiking_routes` esistenti durante il setup
7. **Alias Elasticsearch**: L'alias `ec_tracks → hiking_routes` è essenziale per il funzionamento dell'API

---

**🎯 Questo setup fornisce un ambiente di sviluppo completo e funzionale per OSM2CAI2 con tutte le nuove funzionalità implementate.** 