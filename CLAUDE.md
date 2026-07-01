# CLAUDE.md

Questo file fornisce istruzioni a Claude Code (claude.ai/code) quando lavora con il codice in questo repository.

## Lingua

Rispondi sempre in italiano, indipendentemente dalla lingua usata dall'utente.

## Panoramica del Progetto

Osm2cai2 è un'applicazione Laravel 11 / PHP 8.4 per la gestione dei sentieri CAI italiani con dati geospaziali. Utilizza:
- **Laravel Nova 5** come UI di amministrazione principale
- **PostGIS** per i dati spaziali (geometrie memorizzate come 3DZ)
- **Laravel Horizon** per la gestione delle code
- **OSMFeatures** per la sincronizzazione dei dati OpenStreetMap
- **Docker + Geobox** per lo sviluppo locale

## Comandi

### Esecuzione dei Test
```bash
# Tutti i test (dentro il container Docker)
php artisan test

# Suite specifica
php artisan test --testsuite=Feature
php artisan test --testsuite=Api
php artisan test --testsuite=Unit

# Singola classe o metodo di test
php artisan test --filter=HikingRouteControllerV2Test
php artisan test tests/Feature/HikingRouteControllerV2Test.php

# Test E2E
npm run test:e2e
npm run cypress:open
```

### Linting / Formattazione
```bash
# Formatta il codice PHP con Pint
composer format
# oppure
./vendor/bin/pint --ansi
```

### Asset Frontend
```bash
npm run dev    # watch in sviluppo
npm run build  # build di produzione
```

### Docker / Ambiente di Sviluppo
```bash
# Avvia l'ambiente locale (da fuori Docker)
geobox_serve osm2cai2

# Esegui artisan dentro il container
docker exec -it php81-osm2cai2 php artisan <comando>

# Accedi alla shell del container
docker exec -it php81-osm2cai2 bash

# Avvia i servizi di sviluppo (MinIO, MailPit)
docker-compose -f develop.compose.yml up -d
```

### Comandi Artisan Utili
```bash
php artisan horizon          # Avvia il queue worker
php artisan horizon:terminate  # Riavvia Horizon
php artisan config:clear && php artisan config:cache
```

## Architettura

### Dipendenze Locali (Path)
Il progetto si basa su tre pacchetti locali (path repositories in `composer.json`):
- **`wm-package/`** (`wm/wm-package`) — modelli base (`EcTrack`, `UgcPoi`, `UgcTrack`, `User`), controller base e trait condivisi. I modelli dell'app estendono questi.
- **`wm-osmfeatures/`** (`webmapp/wm-osmfeatures`) — trait di sync OSM `OsmfeaturesSyncableTrait` usato per recuperare geometria e tag da osmfeatures.webmapp.it
- **`wm-internal/`** (`wm/wm-internal`) — strumenti interni e campi Nova

### Componenti Nova (custom)
Situati in `nova-components/`:
- `osm2cai-map-multi-linestring` — campo mappa per percorsi linestring
- `SignageMap` — campo mappa per progetti di segnaletica
- `SignageArrows` — visualizzazione frecce per la segnaletica

### Modelli Principali ed Ereditarietà
I modelli dell'app spesso estendono i modelli base di WmPackage:
- `HikingRoute` estende `Wm\WmPackage\Models\EcTrack`
- `User` estende `Wm\WmPackage\Models\User`

Il modello `HikingRoute` usa `OsmfeaturesSyncableTrait` + un `OsmfeaturesGeometryUpdateTrait` locale che blocca gli aggiornamenti geometrici per i percorsi con `osm2cai_status > 3`.

### Sistema di Stato OSM2CAI
`HikingRoute.osm2cai_status` va da 0 a 4 (SDA — Stato di Accatastamento):
- 0: non rilevato
- 1–3: livelli di validazione parziale
- 4: completamente validato — gli aggiornamenti geometrici da OSM sono bloccati

### Controllo Accessi Basato sui Ruoli
Definito in `App\Enums\UserRole`. Ruoli principali:
- `Administrator`, `NationalReferent`, `RegionalReferent`, `LocalReferent`
- `ClubManager`, `ItineraryManager`, `SicaiManager`
- `Contributor`, `Editor`, `Author`, `Validator`, `Guest`

Lo scope geografico dell'utente è gestito tramite tabelle pivot: `sector_user`, `area_user`, `province_user`.

### Pannello Admin Nova
- Le risorse in `app/Nova/` rispecchiano `app/Models/`
- `App\Nova\Resource` è la base (estende `Laravel\Nova\Resource`)
- `AbstractValidationResource` estende `UgcPoi` per risorse di validazione specifiche per form (filtrate per `form_id`)
- Dashboard: `Main`, `ItalyDashboard`, `SectorsDashboard`, `AcquaSorgente`, `Percorribilità`, `SALMiturAbruzzo`, ecc.
- Le risorse Nova sono registrate in `App\Providers\NovaServiceProvider`

### Struttura API
Route REST in `routes/api.php`:
- `/api/v1/hiking-routes/...` — endpoint legacy v1
- `/api/v2/hiking-routes/...` — endpoint correnti v2
- `/api/geojson/{modelType}/{id}` — download GeoJSON generico
- `/api/csv/{modelType}/{id}`, `/api/kml/...`, `/api/shapefile/...` — export in vari formati
- `/api/v2/mitur-abruzzo/...` — integrazione Mitur Abruzzo

### Job in Coda
I job in `app/Jobs/` gestiscono operazioni asincrone:
- `CalculateIntersectionsJob` — intersezioni spaziali tra percorsi e unità amministrative
- `CheckNearby*Job` — controlli di prossimità (rifugi, sorgenti, sentieri, EC pois)
- `GeneratePdfJob` — generazione PDF del rilevamento sentiero
- `SyncClubHikingRouteRelationJob` — sincronizzazione club-percorso

### Observer
- `HikingRouteObserver` — attiva i controlli di intersezione/prossimità al salvataggio
- `EcPoiObserver`, `TrailSurveyObserver`

### Note Geospaziali
- Le geometrie sono memorizzate come PostGIS 3DZ (dimensione Z impostata a 0 per dati 2D)
- `SpatialDataTrait` fornisce helper condivisi per query spaziali
- `GeoBufferTrait` per operazioni di buffer di prossimità
- Utilità di conversione geometria in `app/Services/GeometryService.php`

## Decisioni architetturali

### Fix sync WMFE/PBF per figli SiHikingRoute aggiornati via saveQuietly (oc:8197)
- `HikingRouteObserver::syncOsm2caiStatusToChildSiHikingRoutes()` propaga `osm2cai_status`/`validator_id`/`validation_date` ai figli con `saveQuietly()`, che bypassa gli Observer e salta il dispatch di `UpdateEcTrackAwsJob` (WMFE) e la rigenerazione PBF — causa di desincronizzazione DB↔WMFE↔PBF su SiHikingRoute figlie.
- Fix: dispatch esplicito di `UpdateEcTrackAwsJob` dopo ogni `saveQuietly()` di un figlio effettivamente cambiato, e rigenerazione PBF solo se il figlio ha anche `wasChanged('geometry')` (oggi sempre `false` in questo metodo, guardia per estensibilità futura).
- Non risolti in questo ciclo (deliberatamente fuori scope): altri path `saveQuietly()`/`updateQuietly()` esistenti (sync osmfeatures bulk, `ForceUpdateOsmFeaturesFromTo`, SignageMap, cleanup SI) possono generare lo stesso sintomo; il servizio centralizzato `EcTrackSyncService` proposto come soluzione a medio termine; i dati storici già desincronizzati (tracciati in oc:8200).
- Verificato: `UpdateEcTrackAwsJob` ricarica sempre il modello come `config('wm-package.ec_track_model')` (= `HikingRoute`), mai come `SiHikingRoute` — ma non impatta il JSON reale su WMFE perché `EcTrackResource` usa `getGeojson()`/relazione `ecPois` diretta, non `getFeatureCollectionMap()` (l'override specifico di `SiHikingRoute`).
- Duplicazione nota (non risolta): la logica di dispatch WMFE/PBF è ripetuta manualmente sia in `saved()` (per il parent) sia in `syncOsm2caiStatusToChildSiHikingRoutes()` (per i figli) — refactor futuro possibile: estrarre in un metodo condiviso.

### Fix valori campi DEM export Excel (oc:7982)
- `EcTrackExcelExporter` legge i valori top-level (`properties.ascent` ecc.) non le sub-sorgenti (`dem_data`, `osm_data`). Nova invece usa `classifyField` (trait `HasDemClassification`). I due sistemi divergono se i valori top-level non sono aggiornati — il command `osm2cai:cleanup-si-hiking-routes-manual-data` li allinea.
- La condizione `osmid !== null` va verificata prima di leggere `osm_data` per replicare esattamente `classifyField` — senza questo check i record senza osmid userebbero erroneamente i valori OSM.
- `safeArray()` duplicato nel command per evitare dipendenze dal trait Nova (`HasDemClassification`) in un Artisan command.
- Root cause strutturale (exporter che legge top-level invece di `classifyField`) tracciata in oc:7984.

### Cleanup manual_data SiHikingRoute (oc:7954)
- Nei command che modificano record durante l'iterazione usare sempre `chunkById()` invece di `chunk()`: `chunk()` usa LIMIT/OFFSET e salta record quando la result set cambia sotto di lui.
- L'operatore `?` di PostgreSQL (esistenza chiave JSONB) va scritto `??` dentro `whereRaw()` per evitare che PDO lo interpreti come placeholder di bind.
- `--verbose` è riservato da Symfony Console: usare `$this->output->isVerbose()` attivato dal flag nativo `-v` invece di dichiarare un'opzione custom.

## Feature disponibili

| Feature | Ticket | Moduli toccati | Note |
|---|---|---|---|
| Fix sync WMFE/PBF per figli SiHikingRoute | oc:8197 | `app/Observers/HikingRouteObserver.php` | Dispatch esplicito di `UpdateEcTrackAwsJob`/PBF dopo `saveQuietly()` sui figli, per evitare desincronizzazione DB↔WMFE↔PBF. Vedi anche oc:8200 (rigenerazione forzata dati storici). |
| Fix valori campi DEM export Excel | oc:7982 | `app/Console/Commands/CleanupSiHikingRoutesManualDataCommand.php` | Estende il command a tutti i record app_id=2: rimuove `manual_data` se presente e ripristina i valori top-level DEM con priorità OSM→DEM→null. Idempotente. |
| Cleanup manual_data SiHikingRoute | oc:7954 | `app/Console/Commands/CleanupSiHikingRoutesManualDataCommand.php` | Rimuove `properties->manual_data` dalle SiHikingRoute (app_id=2, layer_id=6) per ripristinare DEM come current value. Idempotente, supporta `--dry-run` e `-v`. |

### Configurazione dei Test
I test usano un DB PostgreSQL/PostGIS reale (non SQLite in-memory). La connessione DB in `phpunit.xml` è lasciata non commentata per PostGIS.
Suite di test: `Unit`, `Api`, `Feature` (sotto `tests/`).
