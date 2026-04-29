# Design: Allineamento properties GeoJSON regioni all'API v2

**Data:** 2026-04-29  
**File coinvolto:** `app/Http/Controllers/RegionController.php`

## Problema

Il metodo `geojsonComplete` in `RegionController` produce un GeoJSON con properties diverse e valori DEM errati rispetto all'API v2 della singola hiking route (`GET /api/v2/hiking-route/{id}`).

**Causa 1 — DEM nulli:** I campi DEM (`distance`, `ascent`, `descent`, ecc.) vengono letti da `osmfeatures_data['properties']` direttamente, ma il dato reale è annidato in `osmfeatures_data['properties']['dem_enrichment']`. Il modello `HikingRoute::extractDemProperties()` gestisce già questo correttamente, ma il controller lo ignora.

**Causa 2 — Properties diverse:** Le due risposte sono costruite in modo indipendente con set di campi diversi.

## Soluzione (Opzione A)

Refactoring di `geojsonComplete` per usare `$hikingRoute->properties` (colonna DB già popolata correttamente con DEM da `dem_enrichment`) invece di leggere manualmente da `osmfeatures_data`.

## Properties finali del GeoJSON (allineate all'API v2)

| Campo | Fonte |
|-------|-------|
| `id` | `hiking_routes.id` |
| `relation_id` | `osmfeatures_data.properties.osm_id` |
| `source` | `osmfeatures_data.properties.source` |
| `ref` | `hiking_routes.properties.ref` |
| `cai_scale` | `hiking_routes.properties.cai_scale` |
| `from` | `hiking_routes.properties.from` |
| `to` | `hiking_routes.properties.to` |
| `name` | `hiking_routes.properties.name` |
| `description` | `hiking_routes.properties.description` |
| `excerpt` | `hiking_routes.properties.excerpt` |
| `distance` | `hiking_routes.properties.distance` (da dem_enrichment) |
| `ascent` | `hiking_routes.properties.ascent` (da dem_enrichment) |
| `descent` | `hiking_routes.properties.descent` (da dem_enrichment) |
| `ele_from` | `hiking_routes.properties.ele_from` |
| `ele_to` | `hiking_routes.properties.ele_to` |
| `ele_max` | `hiking_routes.properties.ele_max` |
| `ele_min` | `hiking_routes.properties.ele_min` |
| `duration_forward` | `hiking_routes.properties.duration_forward` |
| `duration_backward` | `hiking_routes.properties.duration_backward` |
| `roundtrip` | `hiking_routes.properties.roundtrip` |
| `network` | `hiking_routes.properties.network` |
| `osm_id` | `hiking_routes.properties.osm_id` |
| `layers` | `hiking_routes.properties.layers` |
| `sda` | `hiking_routes.osm2cai_status` |
| `osm2cai_status` | `hiking_routes.osm2cai_status` (duplicato per retrocompatibilità) |
| `issues_status` | `hiking_routes.issues_status` |
| `issues_description` | `hiking_routes.issues_description` |
| `issues_last_update` | `hiking_routes.issues_last_update` |
| `updated_at` | `hiking_routes.updated_at` |
| `public_page` | `url('/hiking-route/id/{id}')` |
| `osm2cai` | `url('/nova/resources/hiking-routes/{id}/edit')` |
| `ref_REI` | `hikingRoute->ref_REI` (getter del modello) |
| `validation_date` | `hiking_routes.validation_date` (solo se osm2cai_status == 4) |
| `sectors` | nomi settori dalla relazione (solo nell'action regione) |
| `itinerary` | array itinerari dalla relazione |

**Campi rimossi rispetto alla versione attuale:** `old_ref`, `source_ref`, `survey_date`, `accessibility` (sostituito da `issues_status`).

## Modifiche tecniche

1. Aggiungere `hiking_routes.properties`, `hiking_routes.issues_description`, `hiking_routes.issues_last_update`, `hiking_routes.validation_date` al `selectRaw` della query.
2. Sostituire la costruzione manuale delle properties con `array_merge($hikingRoute->properties ?? [], [...campi aggiuntivi...])` — stesso pattern di `buildHikingRouteResponse`.
3. Aggiungere `sda` e `osm2cai_status` con lo stesso valore.
4. Aggiungere `validation_date` condizionale se `osm2cai_status == 4`.
5. Aggiungere `itinerary` tramite la relazione `hikingRoute->itineraries()`.
6. Mantenere `sectors` e `osm2cai` (specifici dell'action regione).

## Cosa NON cambia

- La struttura streaming (`response()->stream`) rimane invariata per performance.
- L'endpoint e il nome file di output non cambiano.
- Il filtro `osm2cai_status != 0` rimane.
