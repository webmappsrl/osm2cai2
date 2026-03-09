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

### Configurazione dei Test
I test usano un DB PostgreSQL/PostGIS reale (non SQLite in-memory). La connessione DB in `phpunit.xml` è lasciata non commentata per PostGIS.
Suite di test: `Unit`, `Api`, `Feature` (sotto `tests/`).
