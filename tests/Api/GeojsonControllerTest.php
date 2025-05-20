<?php

namespace Tests\Api;

use App\Models\Area;
use App\Models\Club;
use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeojsonControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $region;

    protected $province;

    protected $area;

    protected $sector;

    protected $club;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a complete hierarchy of test entities
        $this->region = Region::factory()->create([
            'id' => 9999,
            'name' => 'Test Region',
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->province = Province::factory()->create([
            'id' => 9999,
            'name' => 'Test Province',
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->area = Area::factory()->create([
            'id' => 9999,
            'name' => 'Test Area',
            'code' => 'T',
            'full_code' => 'T123',
            'num_expected' => 10,
            'province_id' => $this->province->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->sector = Sector::factory()->create([
            'id' => 9999,
            'name' => 'Test Sector',
            'code' => 'T',
            'num_expected' => 10,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
            'full_code' => 'TS123',
            'area_id' => $this->area->id,
        ]);

        $this->club = Club::factory()->create([
            'id' => 9999,
            'name' => 'Test Club',
            'cai_code' => 'T123',
            'geometry' => DB::raw("ST_GeomFromText('POINT(0 0)')"),
            'region_id' => $this->region->id,
        ]);

        // if hiking routes table has not osmfeatures_data column, add it
        if (! Schema::hasColumn('hiking_routes', 'osmfeatures_data')) {
            Schema::table('hiking_routes', function (Blueprint $table) {
                $table->json('osmfeatures_data')->nullable();
            });
        }
    }

    public function test_download_geojson_for_region()
    {
        $response = $this->get("/api/geojson/region/{$this->region->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Content-Disposition', "attachment; filename=\"{$this->region->id}.geojson\"")
            ->assertJsonStructure([
                'type',
                'features',
                'properties' => [
                    'name',
                    'geojson_url',
                    'shapefile_url',
                    'kml',
                ],
            ]);
    }

    public function test_download_geojson_with_invalid_model_type()
    {
        $response = $this->getJson('/api/geojson/invalid_model/1');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Model not found']);
    }

    public function test_download_geojson_with_nonexistent_model()
    {
        $response = $this->getJson('/api/geojson/region/99999');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Region 99999 not found']);
    }

    public function test_download_geojson_for_club_with_hiking_routes()
    {
        $hikingRoute = HikingRoute::factory()->create([
            'id' => 99999,
            'geometry' => DB::raw("ST_GeomFromText('LINESTRING(0 0, 1 1)')"),
            'osmfeatures_data' => [
                'properties' => [
                    'osm_id' => 12345,
                    'ref' => 'TEST123',
                    'cai_scale' => 'T',
                    'source_ref' => 'TEST123',
                    'from' => 'Start',
                    'to' => 'End',
                ],
            ],
        ]);

        if (! $this->club->hikingRoutes()->where('hiking_route_id', $hikingRoute->id)->exists()) {
            $this->club->hikingRoutes()->attach($hikingRoute->id);
        }

        $response = $this->get("/api/geojson/club/{$this->club->id}");

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
                            'name',
                            'user',
                            'relation_id',
                            'ref',
                            'source_ref',
                            'difficulty',
                            'from',
                            'to',
                            'regions',
                            'provinces',
                            'areas',
                            'sector',
                            'clubs',
                            'last_updated',
                        ],
                    ],
                ],
                'properties' => [
                    'id',
                    'name',
                    'region',
                    'geojson_url',
                    'shapefile_url',
                    'kml',
                ],
            ]);
    }

    public function test_geojson_contains_valid_geometry()
    {
        $response = $this->get("/api/geojson/region/{$this->region->id}");
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('FeatureCollection', $content['type']);
        $this->assertIsArray($content['features']);

        if (! empty($content['features'])) {
            foreach ($content['features'] as $feature) {
                $this->assertArrayHasKey('geometry', $feature);
                $this->assertNotNull($feature['geometry']);
                $this->assertArrayHasKey('type', $feature['geometry']);
                $this->assertArrayHasKey('coordinates', $feature['geometry']);
            }
        }
    }

    public function test_download_geojson_for_province()
    {
        $response = $this->get("/api/geojson/province/{$this->province->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features',
                'properties' => [
                    'name',
                    'region',
                    'geojson_url',
                    'shapefile_url',
                    'kml',
                ],
            ]);
    }

    public function test_download_geojson_for_area()
    {
        $response = $this->get("/api/geojson/area/{$this->area->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features',
                'properties' => [
                    'name',
                    'province',
                    'region',
                    'geojson_url',
                    'shapefile_url',
                    'kml',
                ],
            ]);
    }
}
