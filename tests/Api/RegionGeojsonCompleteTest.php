<?php

namespace Tests\Api;

use App\Models\HikingRoute;
use App\Models\Region;
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

    private function readStream($response): string
    {
        return $response->streamedContent();
    }
}
