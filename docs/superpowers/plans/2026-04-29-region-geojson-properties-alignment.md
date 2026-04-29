# Region GeoJSON Properties Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allineare le properties del GeoJSON scaricato dalla pagina regioni (`/api/geojson-complete/region/{id}`) a quelle dell'API v2 singola hiking route (`/api/v2/hiking-route/{id}`), correggendo i valori DEM nulli e aggiungendo i campi mancanti.

**Architecture:** Refactoring di `RegionController::geojsonComplete()` per costruire le properties usando `$hikingRoute->properties` (colonna DB già popolata correttamente con DEM da `dem_enrichment`) invece di leggere manualmente da `osmfeatures_data`. Pattern identico a `HikingRouteController::buildHikingRouteResponse()`.

**Tech Stack:** Laravel 11, PHP 8.4, PostGIS, PHPUnit

---

## Files

- **Modify:** `app/Http/Controllers/RegionController.php` — metodo `geojsonComplete()`
- **Create:** `tests/Api/RegionGeojsonCompleteTest.php` — test per la struttura e i valori delle properties

---

### Task 1: Scrivi il test failing

**Files:**
- Create: `tests/Api/RegionGeojsonCompleteTest.php`

- [ ] **Step 1: Crea il file di test**

```php
<?php

namespace Tests\Api;

use App\Models\HikingRoute;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegionGeojsonCompleteTest extends TestCase
{
    use DatabaseTransactions;

    protected Region $region;
    protected HikingRoute $hikingRoute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->region = Region::factory()->createQuietly([
            'id' => 8888,
            'name' => 'Test Region Complete',
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 88888,
            'osm2cai_status' => 2,
            'issues_status' => 'ok',
            'issues_description' => 'nessun problema',
            'issues_last_update' => '2024-01-01',
            'geometry' => DB::raw("ST_GeomFromText('LINESTRING(0 0, 1 1)', 4326)"),
            'osmfeatures_data' => [
                'properties' => [
                    'osm_id' => 12345,
                    'ref' => 'T123',
                    'cai_scale' => 'T',
                    'from' => 'Partenza',
                    'to' => 'Arrivo',
                    'source' => 'CAI',
                    'ref_REI' => 'REI123',
                    'dem_enrichment' => [
                        'distance' => 5.5,
                        'ascent' => 300,
                        'descent' => 150,
                        'ele_from' => 200,
                        'ele_to' => 350,
                        'ele_max' => 400,
                        'ele_min' => 180,
                        'duration_forward_hiking' => 120,
                        'duration_backward_hiking' => 90,
                    ],
                ],
            ],
            'properties' => [
                'ref' => 'T123',
                'cai_scale' => 'T',
                'name' => 'Sentiero Test',
                'from' => 'Partenza',
                'to' => 'Arrivo',
                'description' => 'Descrizione test',
                'excerpt' => 'Estratto test',
                'distance' => 5.5,
                'ascent' => 300,
                'descent' => 150,
                'ele_from' => 200,
                'ele_to' => 350,
                'ele_max' => 400,
                'ele_min' => 180,
                'duration_forward' => 120,
                'duration_backward' => 90,
                'roundtrip' => false,
                'network' => 'lwn',
                'osm_id' => 12345,
                'layers' => [],
            ],
        ]);

        $this->region->hikingRoutes()->attach($this->hikingRoute->id);
    }

    public function test_geojson_complete_returns_200(): void
    {
        $response = $this->get("/api/geojson-complete/region/{$this->region->id}");
        $response->assertStatus(200);
    }

    public function test_geojson_complete_is_feature_collection(): void
    {
        $response = $this->get("/api/geojson-complete/region/{$this->region->id}");
        $content = json_decode($this->readStream($response), true);

        $this->assertEquals('FeatureCollection', $content['type']);
        $this->assertIsArray($content['features']);
    }

    public function test_geojson_complete_has_required_properties(): void
    {
        $response = $this->get("/api/geojson-complete/region/{$this->region->id}");
        $content = json_decode($this->readStream($response), true);

        $feature = $content['features'][0];
        $props = $feature['properties'];

        // Campi presenti in API v2
        $this->assertArrayHasKey('id', $props);
        $this->assertArrayHasKey('relation_id', $props);
        $this->assertArrayHasKey('source', $props);
        $this->assertArrayHasKey('ref', $props);
        $this->assertArrayHasKey('cai_scale', $props);
        $this->assertArrayHasKey('from', $props);
        $this->assertArrayHasKey('to', $props);
        $this->assertArrayHasKey('sda', $props);
        $this->assertArrayHasKey('osm2cai_status', $props);
        $this->assertArrayHasKey('issues_status', $props);
        $this->assertArrayHasKey('issues_description', $props);
        $this->assertArrayHasKey('issues_last_update', $props);
        $this->assertArrayHasKey('updated_at', $props);
        $this->assertArrayHasKey('public_page', $props);
        $this->assertArrayHasKey('osm2cai', $props);
        $this->assertArrayHasKey('ref_REI', $props);
        $this->assertArrayHasKey('itinerary', $props);
        // DEM
        $this->assertArrayHasKey('distance', $props);
        $this->assertArrayHasKey('ascent', $props);
        $this->assertArrayHasKey('descent', $props);
        $this->assertArrayHasKey('ele_from', $props);
        $this->assertArrayHasKey('ele_to', $props);
        $this->assertArrayHasKey('ele_max', $props);
        $this->assertArrayHasKey('ele_min', $props);
        $this->assertArrayHasKey('duration_forward', $props);
        $this->assertArrayHasKey('duration_backward', $props);
        // Solo action regione
        $this->assertArrayHasKey('sectors', $props);
    }

    public function test_sda_and_osm2cai_status_have_same_value(): void
    {
        $response = $this->get("/api/geojson-complete/region/{$this->region->id}");
        $content = json_decode($this->readStream($response), true);

        $props = $content['features'][0]['properties'];
        $this->assertEquals($props['sda'], $props['osm2cai_status']);
        $this->assertEquals(2, $props['sda']);
    }

    public function test_dem_values_are_not_null(): void
    {
        $response = $this->get("/api/geojson-complete/region/{$this->region->id}");
        $content = json_decode($this->readStream($response), true);

        $props = $content['features'][0]['properties'];
        $this->assertEquals(5.5, $props['distance']);
        $this->assertEquals(300, $props['ascent']);
        $this->assertEquals(150, $props['descent']);
        $this->assertEquals(200, $props['ele_from']);
        $this->assertEquals(400, $props['ele_max']);
        $this->assertEquals(120, $props['duration_forward']);
    }

    public function test_validation_date_present_only_for_status_4(): void
    {
        // status 2: no validation_date
        $response = $this->get("/api/geojson-complete/region/{$this->region->id}");
        $content = json_decode($this->readStream($response), true);
        $props = $content['features'][0]['properties'];
        $this->assertArrayNotHasKey('validation_date', $props);

        // status 4: validation_date present
        $this->hikingRoute->update([
            'osm2cai_status' => 4,
            'validation_date' => '2024-06-01',
        ]);

        $response2 = $this->get("/api/geojson-complete/region/{$this->region->id}");
        $content2 = json_decode($this->readStream($response2), true);
        $props2 = $content2['features'][0]['properties'];
        $this->assertArrayHasKey('validation_date', $props2);
        $this->assertEquals('2024-06-01', $props2['validation_date']);
    }

    public function test_removed_fields_not_present(): void
    {
        $response = $this->get("/api/geojson-complete/region/{$this->region->id}");
        $content = json_decode($this->readStream($response), true);

        $props = $content['features'][0]['properties'];
        $this->assertArrayNotHasKey('old_ref', $props);
        $this->assertArrayNotHasKey('source_ref', $props);
        $this->assertArrayNotHasKey('survey_date', $props);
        $this->assertArrayNotHasKey('accessibility', $props);
    }

    /**
     * Legge il contenuto di una StreamedResponse.
     */
    private function readStream($response): string
    {
        ob_start();
        $response->sendContent();
        return ob_get_clean();
    }
}
```

- [ ] **Step 2: Esegui il test per verificare che fallisca**

```bash
docker exec -it php81-osm2cai2 php artisan test tests/Api/RegionGeojsonCompleteTest.php --verbose
```

Expected: I test falliscono perché le properties attuali non corrispondono.

---

### Task 2: Implementa il fix in `RegionController::geojsonComplete()`

**Files:**
- Modify: `app/Http/Controllers/RegionController.php`

- [ ] **Step 1: Aggiorna il metodo `geojsonComplete()`**

Sostituisci il metodo esistente (da riga 79 in poi) con:

```php
public function geojsonComplete(string $id): StreamedResponse|JsonResponse
{
    try {
        $region = Region::findOrFail($id);
    } catch (ModelNotFoundException $e) {
        return response()->json(['error' => 'Region not found'], 404);
    }

    $headers = [
        'Content-Type' => 'application/json',
        'Content-Disposition' => 'attachment; filename="osm2cai_'.date('Ymd').'_regione_complete_'.$region->name.'.geojson"',
        'X-Accel-Buffering' => 'no',
    ];

    return response()->stream(function () use ($region) {
        $query = $region->hikingRoutes()
            ->where('osm2cai_status', '!=', 0)
            ->selectRaw('
                hiking_routes.id,
                hiking_routes.name,
                hiking_routes.osmfeatures_data,
                hiking_routes.properties,
                hiking_routes.issues_status,
                hiking_routes.issues_description,
                hiking_routes.issues_last_update,
                hiking_routes.osm2cai_status,
                hiking_routes.validation_date,
                hiking_routes.created_at,
                hiking_routes.updated_at,
                ST_AsGeoJSON(geometry) as geom_geojson
            ');

        echo '{"type":"FeatureCollection","features":[';

        $first = true;
        foreach ($query->cursor() as $hikingRoute) {
            $sectors = $hikingRoute->sectors()->pluck('name')->toArray();
            $osmProps = $hikingRoute->osmfeatures_data['properties'] ?? [];

            // Base properties da colonna properties (contiene DEM corretto da dem_enrichment)
            $properties = array_merge($hikingRoute->properties ?? [], [
                'id'                  => $hikingRoute->id,
                'relation_id'         => $osmProps['osm_id'] ?? null,
                'source'              => $osmProps['source'] ?? null,
                'ref_REI'             => $osmProps['ref_REI'] ?? null,
                'sda'                 => $hikingRoute->osm2cai_status,
                'osm2cai_status'      => $hikingRoute->osm2cai_status,
                'issues_status'       => $hikingRoute->issues_status ?? '',
                'issues_description'  => $hikingRoute->issues_description ?? '',
                'issues_last_update'  => $hikingRoute->issues_last_update ?? '',
                'updated_at'          => $hikingRoute->updated_at,
                'public_page'         => url('/hiking-route/id/'.$hikingRoute->id),
                'osm2cai'             => url('/nova/resources/hiking-routes/'.$hikingRoute->id.'/edit'),
                'itinerary'           => $this->getItineraryArray($hikingRoute),
                'sectors'             => $sectors,
            ]);

            if ($hikingRoute->osm2cai_status == 4) {
                $properties['validation_date'] = $hikingRoute->validation_date
                    ? \Carbon\Carbon::parse($hikingRoute->validation_date)->format('Y-m-d')
                    : null;
            }

            $feature = [
                'type'       => 'Feature',
                'properties' => $properties,
                'geometry'   => json_decode($hikingRoute->geom_geojson, true),
            ];

            if (! $first) {
                echo ',';
            }
            echo json_encode($feature);
            $first = false;

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }

        echo ']}';
    }, 200, $headers);
}
```

- [ ] **Step 2: Aggiungi il metodo privato `getItineraryArray()` a `RegionController`**

Questo metodo è già presente in `HikingRouteController`. Aggiungilo anche in `RegionController` (o estrailo in un trait se preferisci, ma per ora duplicarlo è sufficiente):

```php
private function getItineraryArray(HikingRoute $hikingRoute): array
{
    $itinerary_array = [];
    $itineraries = $hikingRoute->itineraries()->get();

    foreach ($itineraries as $it) {
        $edges = $it->generateItineraryEdges();
        $prevRoute = $edges[$hikingRoute->id]['prev'] ?? null;
        $nextRoute = $edges[$hikingRoute->id]['next'] ?? null;

        $itinerary_array[] = [
            'id'       => $it->id,
            'name'     => $it->name,
            'previous' => $prevRoute[0] ?? '',
            'next'     => $nextRoute[0] ?? '',
        ];
    }

    return $itinerary_array;
}
```

- [ ] **Step 3: Verifica che `HikingRoute` sia importato in `RegionController`**

Controlla l'inizio del file e aggiungi se mancante:

```php
use App\Models\HikingRoute;
use Carbon\Carbon;
```

- [ ] **Step 4: Esegui i test per verificare che passino**

```bash
docker exec -it php81-osm2cai2 php artisan test tests/Api/RegionGeojsonCompleteTest.php --verbose
```

Expected: tutti i test passano (verde).

- [ ] **Step 5: Esegui la suite completa per verificare assenza di regressioni**

```bash
docker exec -it php81-osm2cai2 php artisan test --testsuite=Api --verbose
```

Expected: nessun test precedentemente verde diventa rosso.
