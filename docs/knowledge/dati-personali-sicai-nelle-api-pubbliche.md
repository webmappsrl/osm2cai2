# Dati personali SICAI nelle API pubbliche

## Come funziona oggi

Il blocco `hiking_routes.properties->sicai`, scritto dall'import dal DB SICAI (`PopulateOsmfeaturesIdFromSicaiDbCommand`), contiene anche dati personali dei referenti. Nel DB e in Nova restano intatti; i canali pubblici devono leggerli già filtrati.

- La lista dei campi da non pubblicare è una sola: `HikingRoute::filterSicaiPropertiesForApi()` (privato). Toglie `referente.name`, `referente.email` (se `referente` resta vuoto la chiave sparisce), `referente_regionale`, `email_ref_regionale`, `sezione_ref_regionale`, `sezioni_manutenzione`.
- `HikingRoute::getPublicProperties()` restituisce `properties` con `sicai` filtrato, senza modificare il modello. Lo usano `HikingRouteController::buildHikingRouteResponse()` (`/api/v2/hiking-route/{id}` e `/api/v2/hiking-route-osm/{osm_id}`) e `ExportResource` per `HikingRoute` (`/api/v2/export/hiking-routes/{id}`).
- `enhanceHikingRouteProperties()`, cioè il GeoJSON verso WMFE/Elasticsearch, applica lo stesso filtro direttamente, per qualunque `app_id`.
- Un canale nuovo che espone `properties` di un percorso deve passare da `getPublicProperties()`: `$model->properties` e `parent::toArray()` restituiscono il blocco `sicai` intero.

Canali che espongono ancora il blocco intero, lasciati fuori per decisione (oc:8893): route wm-package `ec/track/multiple` e `ec/track/download/{id}.geojson` (sotto `/api` e `/api/v2`, usano il modello base `EcTrack` e non passano dal filtro di `HikingRoute`), `/api/geojson-complete/region/{id}` e `/sector/{id}`.

## Perché così

- **Una lista sola, nel modello** (oc:8893): due liste per canali diversi prima o poi divergono e un canale torna a pubblicare un campo.
- **Stessa lista già usata da WMFE, non solo i campi segnalati dal cliente** (oc:8893): un fix di privacy non deve ripubblicare campi che prima erano nascosti.
- **Filtro deciso dalla presenza del blocco `sicai`, non da `app_id`** (oc:8893): la condizione `app_id === 2` lasciava scoperti i percorsi con `app_id` 1 che hanno comunque il blocco (5 in locale e in produzione al 28/10/2026, elencati in [notes.md](../features/8893-osm2cai-api-restituiscono-dati-sensibili/notes.md)).

## Come ci siamo arrivati

- **Filtro solo in `getGeojson()` e solo per `app_id` 2** (superata, oc:8893): copriva WMFE ma non le API, che copiavano `properties` per intero, e saltava i percorsi SICAI con un altro `app_id`.
