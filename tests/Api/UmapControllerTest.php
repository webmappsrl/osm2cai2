<?php

namespace Tests\Api;

use App\Enums\ValidatedStatusEnum;
use App\Models\UgcPoi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Wm\WmPackage\Models\App;

class UmapControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable throttling for testing
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    /**
     * Helper per creare un UgcPoi con geometria
     */
    private function createUgcPoiWithGeometry(string $formId, array $rawData = [], array $attributes = []): UgcPoi
    {
        $defaultProperties = $attributes['properties'] ?? [];
        if (! isset($defaultProperties['form'])) {
            $defaultProperties['form'] = ['id' => $formId];
        } else {
            $defaultProperties['form']['id'] = $formId;
        }

        $defaultAttributes = [
            'geohub_id' => rand(100000, 999999),
            'geometry' => DB::raw('ST_GeomFromGeoJSON(\'{"type":"Point","coordinates":[10.123,45.456]}\')'),
            'raw_data' => $rawData,
            'validated' => ValidatedStatusEnum::NOT_VALIDATED->value,
            'app_id' => App::first()?->id ?? App::factory()->create()->id,
            'user_id' => User::first()?->id ?? User::factory()->create()->id,
            'properties' => $defaultProperties,
        ];

        unset($attributes['properties']); // Rimuovi properties da attributes se presente, già gestito sopra
        $poi = UgcPoi::createQuietly(array_merge($defaultAttributes, $attributes));

        // form_id non è nel fillable, quindi lo impostiamo direttamente nel database
        DB::table('ugc_pois')->where('id', $poi->id)->update(['form_id' => $formId]);
        $poi->refresh();

        return $poi;
    }

    /**
     * Helper per creare un UgcPoi senza geometria
     */
    private function createUgcPoiWithoutGeometry(string $formId, array $rawData = []): UgcPoi
    {
        $poi = UgcPoi::createQuietly([
            'geohub_id' => rand(100000, 999999),
            'geometry' => null,
            'raw_data' => $rawData,
            'app_id' => App::first()?->id ?? App::factory()->create()->id,
            'user_id' => User::first()?->id ?? User::factory()->create()->id,
            'properties' => ['form' => ['id' => $formId]],
        ]);

        // form_id non è nel fillable, quindi lo impostiamo direttamente nel database
        DB::table('ugc_pois')->where('id', $poi->id)->update(['form_id' => $formId]);
        $poi->refresh();

        return $poi;
    }

    // ========== POIS ENDPOINT TESTS ==========

    public function test_pois_endpoint_returns_feature_collection_with_valid_pois()
    {
        // Crea POI validi con waypointtype accettati
        $uniqueId = uniqid('test_');
        $floraPoi = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'flora',
            'title' => "Flora POI {$uniqueId}",
            'description' => 'Test flora description',
        ]);

        $faunaPoi = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'fauna',
            'title' => "Fauna POI {$uniqueId}",
            'description' => 'Test fauna description',
        ]);

        $habitatPoi = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'habitat',
            'title' => "Habitat POI {$uniqueId}",
            'description' => 'Test habitat description',
        ]);

        $response = $this->getJson('/api/umap/pois');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry' => [
                            'type',
                            'coordinates',
                        ],
                        'properties' => [
                            'title',
                            'description',
                            'waypointtype',
                            'validation_status',
                            'osm2cai_link',
                            'images',
                        ],
                    ],
                ],
            ]);

        $data = $response->json();
        $this->assertEquals('FeatureCollection', $data['type']);
        $this->assertGreaterThanOrEqual(3, count($data['features']));

        // Verifica che tutti i POI creati siano presenti
        $titles = collect($data['features'])->pluck('properties.title')->toArray();
        $this->assertContains("Flora POI {$uniqueId}", $titles);
        $this->assertContains("Fauna POI {$uniqueId}", $titles);
        $this->assertContains("Habitat POI {$uniqueId}", $titles);
    }

    public function test_pois_endpoint_filters_by_waypointtype()
    {
        $uniqueId = uniqid('test_');
        // POI con waypointtype valido
        $validPoi = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'flora',
            'title' => "Valid POI {$uniqueId}",
        ]);

        // POI con waypointtype non valido (non incluso)
        $invalidPoi = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'other',
            'title' => "Invalid POI {$uniqueId}",
        ]);

        // POI con waypointtype maiuscolo (dovrebbe essere incluso grazie a LOWER)
        $uppercasePoi = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'FLORA',
            'title' => "Uppercase POI {$uniqueId}",
        ]);

        $response = $this->getJson('/api/umap/pois');

        $response->assertStatus(200);
        $data = $response->json();
        $titles = collect($data['features'])->pluck('properties.title')->toArray();

        $this->assertContains("Valid POI {$uniqueId}", $titles);
        $this->assertContains("Uppercase POI {$uniqueId}", $titles);
        $this->assertNotContains("Invalid POI {$uniqueId}", $titles);
    }

    public function test_pois_endpoint_excludes_pois_without_geometry()
    {
        $uniqueId = uniqid('test_');
        // POI con geometria
        $withGeometry = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'flora',
            'title' => "With Geometry {$uniqueId}",
        ]);

        // POI senza geometria (non incluso)
        $withoutGeometry = $this->createUgcPoiWithoutGeometry('poi', [
            'waypointtype' => 'flora',
            'title' => "Without Geometry {$uniqueId}",
        ]);

        $response = $this->getJson('/api/umap/pois');

        $response->assertStatus(200);
        $data = $response->json();
        $titles = collect($data['features'])->pluck('properties.title')->toArray();

        $this->assertContains("With Geometry {$uniqueId}", $titles);
        $this->assertNotContains("Without Geometry {$uniqueId}", $titles);
    }

    public function test_pois_endpoint_uses_fallback_for_title_and_description()
    {
        $uniqueId = uniqid('test_');
        // POI con solo raw_data
        $poi1 = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'flora',
            'title' => "From Raw Data {$uniqueId}",
            'description' => 'Description from raw_data',
        ]);

        // POI con name e description nel modello (fallback)
        $poi2 = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'flora',
        ], [
            'name' => "From Model Name {$uniqueId}",
            'description' => 'Description from model',
        ]);

        $response = $this->getJson('/api/umap/pois');

        $response->assertStatus(200);
        $data = $response->json();

        $poi1Feature = collect($data['features'])->firstWhere('properties.title', "From Raw Data {$uniqueId}");
        $this->assertNotNull($poi1Feature, "POI 1 with title 'From Raw Data {$uniqueId}' should be found");
        $this->assertEquals("From Raw Data {$uniqueId}", $poi1Feature['properties']['title']);
        $this->assertEquals('Description from raw_data', $poi1Feature['properties']['description']);

        $poi2Feature = collect($data['features'])->firstWhere('properties.title', "From Model Name {$uniqueId}");
        $this->assertNotNull($poi2Feature, "POI 2 with title 'From Model Name {$uniqueId}' should be found");
        $this->assertEquals("From Model Name {$uniqueId}", $poi2Feature['properties']['title']);
        $this->assertEquals('Description from model', $poi2Feature['properties']['description']);
    }

    public function test_pois_endpoint_returns_empty_when_no_valid_pois()
    {
        $response = $this->getJson('/api/umap/pois');

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'FeatureCollection',
                'features' => [],
            ]);
    }

    public function test_pois_endpoint_includes_validation_status_and_osm2cai_link()
    {
        $uniqueId = uniqid('test_');
        $poi = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'flora',
            'title' => "Test POI {$uniqueId}",
        ], [
            'validated' => ValidatedStatusEnum::VALID->value,
        ]);

        $response = $this->getJson('/api/umap/pois');

        $response->assertStatus(200);
        $data = $response->json();
        $feature = collect($data['features'])->firstWhere('properties.title', "Test POI {$uniqueId}");

        $this->assertNotNull($feature, "POI with title 'Test POI {$uniqueId}' should be found");
        $this->assertEquals(ValidatedStatusEnum::VALID->value, $feature['properties']['validation_status']);
        $this->assertStringContainsString('/resources/ugc-pois/'.$poi->id, $feature['properties']['osm2cai_link']);
    }

    // ========== SIGNS ENDPOINT TESTS ==========

    public function test_signs_endpoint_returns_correct_structure()
    {
        $uniqueId = uniqid('test_');
        $sign = $this->createUgcPoiWithGeometry('signs', [
            'artifact_type' => 'Test Artifact',
            'title' => "Test Sign {$uniqueId}",
            'location' => 'Test Location',
            'conservation_status' => 'Good',
            'notes' => 'Test Notes',
        ]);

        $response = $this->getJson('/api/umap/signs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'title',
                            'artifact_type',
                            'location',
                            'conservation_status',
                            'notes',
                            'validation_status',
                            'osm2cai_link',
                            'images',
                        ],
                    ],
                ],
            ]);

        $data = $response->json();
        $feature = collect($data['features'])->firstWhere('properties.title', "Test Sign {$uniqueId}");
        $this->assertNotNull($feature, "Sign with title 'Test Sign {$uniqueId}' should be found");
        $this->assertEquals("Test Sign {$uniqueId}", $feature['properties']['title']);
        $this->assertEquals('Test Artifact', $feature['properties']['artifact_type']);
        $this->assertEquals('Test Location', $feature['properties']['location']);
        $this->assertEquals('Good', $feature['properties']['conservation_status']);
        $this->assertEquals('Test Notes', $feature['properties']['notes']);
    }

    public function test_signs_endpoint_excludes_signs_without_geometry()
    {
        $uniqueId = uniqid('test_');
        $withGeometry = $this->createUgcPoiWithGeometry('signs', ['title' => "With Geometry {$uniqueId}"]);
        $withoutGeometry = $this->createUgcPoiWithoutGeometry('signs', ['title' => "Without Geometry {$uniqueId}"]);

        $response = $this->getJson('/api/umap/signs');

        $response->assertStatus(200);
        $data = $response->json();
        $titles = collect($data['features'])->pluck('properties.title')->toArray();

        $this->assertContains("With Geometry {$uniqueId}", $titles);
        $this->assertNotContains("Without Geometry {$uniqueId}", $titles);
    }

    // ========== ARCHAEOLOGICAL SITES ENDPOINT TESTS ==========

    public function test_archaeological_sites_endpoint_returns_correct_structure()
    {
        $uniqueId = uniqid('test_');
        $site = $this->createUgcPoiWithGeometry('archaeological_site', [
            'title' => "Test Archaeological Site {$uniqueId}",
            'location' => 'Test Location',
            'condition' => 'Good',
            'informational_supports' => 'Signs present',
            'notes' => 'Test Notes',
        ]);

        $response = $this->getJson('/api/umap/archaeological_sites');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'title',
                            'location',
                            'condition',
                            'informational_supports',
                            'notes',
                            'validation_status',
                            'osm2cai_link',
                            'images',
                        ],
                    ],
                ],
            ]);

        $data = $response->json();
        $feature = collect($data['features'])->firstWhere('properties.title', "Test Archaeological Site {$uniqueId}");
        $this->assertNotNull($feature, "Archaeological site with title 'Test Archaeological Site {$uniqueId}' should be found");
        $this->assertEquals("Test Archaeological Site {$uniqueId}", $feature['properties']['title']);
        $this->assertEquals('Test Location', $feature['properties']['location']);
        $this->assertEquals('Good', $feature['properties']['condition']);
        $this->assertEquals('Signs present', $feature['properties']['informational_supports']);
        $this->assertEquals('Test Notes', $feature['properties']['notes']);
    }

    public function test_archaeological_sites_endpoint_excludes_sites_without_geometry()
    {
        $uniqueId = uniqid('test_');
        $withGeometry = $this->createUgcPoiWithGeometry('archaeological_site', ['title' => "With Geometry {$uniqueId}"]);
        $withoutGeometry = $this->createUgcPoiWithoutGeometry('archaeological_site', ['title' => "Without Geometry {$uniqueId}"]);

        $response = $this->getJson('/api/umap/archaeological_sites');

        $response->assertStatus(200);
        $data = $response->json();
        $titles = collect($data['features'])->pluck('properties.title')->toArray();

        $this->assertContains("With Geometry {$uniqueId}", $titles);
        $this->assertNotContains("Without Geometry {$uniqueId}", $titles);
    }

    // ========== ARCHAEOLOGICAL AREAS ENDPOINT TESTS ==========

    public function test_archaeological_areas_endpoint_returns_correct_structure()
    {
        $uniqueId = uniqid('test_');
        $area = $this->createUgcPoiWithGeometry('archaeological_area', [
            'title' => "Test Archaeological Area {$uniqueId}",
            'area_type' => 'Settlement',
            'location' => 'Test Location',
            'notes' => 'Test Notes',
        ]);

        $response = $this->getJson('/api/umap/archaeological_areas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'title',
                            'area_type',
                            'location',
                            'notes',
                            'validation_status',
                            'osm2cai_link',
                            'images',
                        ],
                    ],
                ],
            ]);

        $data = $response->json();
        $feature = collect($data['features'])->firstWhere('properties.title', "Test Archaeological Area {$uniqueId}");
        $this->assertNotNull($feature, "Archaeological area with title 'Test Archaeological Area {$uniqueId}' should be found");
        $this->assertEquals("Test Archaeological Area {$uniqueId}", $feature['properties']['title']);
        $this->assertEquals('Settlement', $feature['properties']['area_type']);
        $this->assertEquals('Test Location', $feature['properties']['location']);
        $this->assertEquals('Test Notes', $feature['properties']['notes']);
    }

    public function test_archaeological_areas_endpoint_excludes_areas_without_geometry()
    {
        $uniqueId = uniqid('test_');
        $withGeometry = $this->createUgcPoiWithGeometry('archaeological_area', ['title' => "With Geometry {$uniqueId}"]);
        $withoutGeometry = $this->createUgcPoiWithoutGeometry('archaeological_area', ['title' => "Without Geometry {$uniqueId}"]);

        $response = $this->getJson('/api/umap/archaeological_areas');

        $response->assertStatus(200);
        $data = $response->json();
        $titles = collect($data['features'])->pluck('properties.title')->toArray();

        $this->assertContains("With Geometry {$uniqueId}", $titles);
        $this->assertNotContains("Without Geometry {$uniqueId}", $titles);
    }

    // ========== GEOLOGICAL SITES ENDPOINT TESTS ==========

    public function test_geological_sites_endpoint_returns_correct_structure()
    {
        $uniqueId = uniqid('test_');
        $site = $this->createUgcPoiWithGeometry('geological_site', [
            'title' => "Test Geological Site {$uniqueId}",
            'site_type' => 'Cave',
            'vulnerability' => 'High',
            'vulnerability_reasons' => 'Tourism impact',
            'ispra_geosite' => 'Yes',
            'notes' => 'Test Notes',
        ]);

        $response = $this->getJson('/api/umap/geological_sites');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry',
                        'properties' => [
                            'title',
                            'site_type',
                            'vulnerability',
                            'vulnerability_reasons',
                            'ispra_geosite',
                            'notes',
                            'validation_status',
                            'osm2cai_link',
                            'images',
                        ],
                    ],
                ],
            ]);

        $data = $response->json();
        $feature = collect($data['features'])->firstWhere('properties.title', "Test Geological Site {$uniqueId}");
        $this->assertNotNull($feature, "Geological site with title 'Test Geological Site {$uniqueId}' should be found");
        $this->assertEquals("Test Geological Site {$uniqueId}", $feature['properties']['title']);
        $this->assertEquals('Cave', $feature['properties']['site_type']);
        $this->assertEquals('High', $feature['properties']['vulnerability']);
        $this->assertEquals('Tourism impact', $feature['properties']['vulnerability_reasons']);
        $this->assertEquals('Yes', $feature['properties']['ispra_geosite']);
        $this->assertEquals('Test Notes', $feature['properties']['notes']);
    }

    public function test_geological_sites_endpoint_excludes_sites_without_geometry()
    {
        $uniqueId = uniqid('test_');
        $withGeometry = $this->createUgcPoiWithGeometry('geological_site', ['title' => "With Geometry {$uniqueId}"]);
        $withoutGeometry = $this->createUgcPoiWithoutGeometry('geological_site', ['title' => "Without Geometry {$uniqueId}"]);

        $response = $this->getJson('/api/umap/geological_sites');

        $response->assertStatus(200);
        $data = $response->json();
        $titles = collect($data['features'])->pluck('properties.title')->toArray();

        $this->assertContains("With Geometry {$uniqueId}", $titles);
        $this->assertNotContains("Without Geometry {$uniqueId}", $titles);
    }

    // ========== GEOMETRY TESTS ==========

    public function test_all_endpoints_return_valid_geojson_geometry()
    {
        $uniqueId = uniqid('test_');
        $poi = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'flora',
            'title' => "Test POI {$uniqueId}",
        ]);

        $response = $this->getJson('/api/umap/pois');

        $response->assertStatus(200);
        $data = $response->json();
        $feature = collect($data['features'])->firstWhere('properties.title', "Test POI {$uniqueId}");

        $this->assertNotNull($feature, "POI with title 'Test POI {$uniqueId}' should be found");
        $this->assertEquals('Feature', $feature['type']);
        $this->assertIsArray($feature['geometry']);
        $this->assertEquals('Point', $feature['geometry']['type']);
        $this->assertIsArray($feature['geometry']['coordinates']);
        // Le coordinate possono avere 2 (longitude, latitude) o 3 elementi (longitude, latitude, elevation)
        $this->assertGreaterThanOrEqual(2, count($feature['geometry']['coordinates']));
        $this->assertLessThanOrEqual(3, count($feature['geometry']['coordinates']));
        // Verifica che le coordinate siano numeriche
        $this->assertIsNumeric($feature['geometry']['coordinates'][0]);
        $this->assertIsNumeric($feature['geometry']['coordinates'][1]);
    }

    // ========== EDGE CASES ==========

    public function test_endpoints_handle_missing_raw_data_gracefully()
    {
        $uniqueId = uniqid('test_');
        // Per l'endpoint POI, dobbiamo avere un waypointtype valido, ma possiamo testare il fallback per title/description
        $poi = $this->createUgcPoiWithGeometry('poi', [
            'waypointtype' => 'flora', // Necessario per essere incluso nell'endpoint POI
        ], [
            'name' => "Model Name {$uniqueId}",
            'description' => 'Model Description',
        ]);

        $response = $this->getJson('/api/umap/pois');

        $response->assertStatus(200);
        $data = $response->json();
        $feature = collect($data['features'])->firstWhere('properties.title', "Model Name {$uniqueId}");

        $this->assertNotNull($feature, "POI with title 'Model Name {$uniqueId}' should be found");
        // Il title dovrebbe venire da name (fallback) se raw_data['title'] non è presente
        $this->assertEquals("Model Name {$uniqueId}", $feature['properties']['title']);
        $this->assertEquals('Model Description', $feature['properties']['description']);
        $this->assertEquals('flora', $feature['properties']['waypointtype']);

        // Test per un endpoint che non filtra per waypointtype (signs)
        $sign = $this->createUgcPoiWithGeometry('signs', [], [
            'name' => "Sign Model Name {$uniqueId}",
            'description' => 'Sign Model Description',
        ]);

        $signsResponse = $this->getJson('/api/umap/signs');
        $signsData = $signsResponse->json();
        $signFeature = collect($signsData['features'])->firstWhere('properties.title', "Sign Model Name {$uniqueId}");

        $this->assertNotNull($signFeature, "Sign with title 'Sign Model Name {$uniqueId}' should be found");
        $this->assertEquals("Sign Model Name {$uniqueId}", $signFeature['properties']['title']);
        // I signs non hanno description nelle properties, solo title, artifact_type, location, etc.
    }

    public function test_endpoints_only_return_correct_form_id()
    {
        $uniqueId = uniqid('test_');
        // Crea POI con form_id diversi
        $poi = $this->createUgcPoiWithGeometry('poi', ['waypointtype' => 'flora', 'title' => "POI {$uniqueId}"]);
        $sign = $this->createUgcPoiWithGeometry('signs', ['title' => "Sign {$uniqueId}"]);
        $archSite = $this->createUgcPoiWithGeometry('archaeological_site', ['title' => "Arch Site {$uniqueId}"]);

        // Verifica che ogni endpoint restituisca solo i suoi dati
        $poisResponse = $this->getJson('/api/umap/pois');
        $poisData = $poisResponse->json();
        $poisTitles = collect($poisData['features'])->pluck('properties.title')->toArray();
        $this->assertContains("POI {$uniqueId}", $poisTitles);
        $this->assertNotContains("Sign {$uniqueId}", $poisTitles);
        $this->assertNotContains("Arch Site {$uniqueId}", $poisTitles);

        $signsResponse = $this->getJson('/api/umap/signs');
        $signsData = $signsResponse->json();
        $signsTitles = collect($signsData['features'])->pluck('properties.title')->toArray();
        $this->assertContains("Sign {$uniqueId}", $signsTitles);
        $this->assertNotContains("POI {$uniqueId}", $signsTitles);
        $this->assertNotContains("Arch Site {$uniqueId}", $signsTitles);
    }
}
