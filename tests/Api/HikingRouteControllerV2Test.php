<?php

namespace Tests\Api;

use App\Models\HikingRoute;
use App\Models\Region;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HikingRouteControllerV2Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        //check if osmfeatures_data column exists
        if (! Schema::hasColumn('hiking_routes', 'osmfeatures_data')) {
            Schema::table('hiking_routes', function (Blueprint $table) {
                $table->json('osmfeatures_data')->nullable();
            });
        }
        // Disable throttling for testing
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function createTestHikingRoute($id, $osm_id, $status, $geometry = null)
    {
        return HikingRoute::create([
            'id' => $id,
            'osm2cai_status' => $status,
            'updated_at' => now(),
            'source' => 'survey:CAI',
            'cai_scale' => 'E',
            'from' => 'Test Start',
            'to' => 'Test End',
            'ref' => '117',
            'geometry' => DB::raw("ST_GeomFromText('LINESTRING(10 10, 20 20)', 4326)"),
            'validation_date' => now(),
            'issues_status' => 'none',
            'issues_description' => '',
            'issues_last_update' => now(),
            'tdh' => [
                'gpx_url' => url('/api/v2/hiking-routes/'.$id.'.gpx'),
                'cai_scale_string' => 'E',
                'cai_scale_description' => 'Easy',
                'from' => 'Test Start',
                'city_from' => 'Test City',
                'city_from_istat' => '001',
                'region_from' => 'Test Region',
                'region_from_istat' => '001',
                'to' => 'Test End',
                'city_to' => 'Test City',
                'city_to_istat' => '001',
                'region_to' => 'Test Region',
                'region_to_istat' => '001',
                'roundtrip' => false,
                'abstract' => 'Test Abstract',
                'distance' => 10,
                'ascent' => 10,
                'descent' => 10,
                'duration_forward' => 10,
                'duration_backward' => 10,
                'ele_from' => 10,
                'ele_to' => 10,
                'ele_max' => 10,
                'ele_min' => 10,
            ],
            'osmfeatures_data' => [
                'properties' => [
                    'osm_id' => $osm_id,
                    'from' => 'Test Start',
                    'to' => 'Test End',
                    'ref' => '117',
                ],
            ],
        ]);
    }

    private function createTestRegion($code)
    {
        return Region::create([
            'code' => $code,
            'name' => 'Test Region',
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 30, 30 30, 30 0, 0 0))', 4326)"),
        ]);
    }

    public function test_hiking_route_index()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        $response = $this->get('/api/v2/hiking-routes/list');

        $response->assertStatus(200)
            ->assertJson([
                $hikingRoute->id => $hikingRoute->updated_at->toISOString(),
            ]);
    }

    public function test_hiking_route_index_by_region()
    {
        $region = $this->createTestRegion('L');
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4, $region->geometry);

        DB::table('hiking_route_region')->insert([
            'hiking_route_id' => $hikingRoute->id,
            'region_id' => $region->id,
        ]);

        $response = $this->get('/api/v2/hiking-routes/region/'.$region->code.'/'.$hikingRoute->osm2cai_status);

        $response->assertStatus(200)
            ->assertJson([$hikingRoute->id]);
    }

    public function test_hiking_route_osm_index_by_region()
    {
        $region = $this->createTestRegion('L');
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        DB::table('hiking_route_region')->insert([
            'hiking_route_id' => $hikingRoute->id,
            'region_id' => $region->id,
        ]);

        $response = $this->get('/api/v2/hiking-routes-osm/region/'.$region->code.'/'.$hikingRoute->osm2cai_status);

        $osmId = (string) $hikingRoute->osmfeatures_data['properties']['osm_id'];

        $response->assertStatus(200)
            ->assertJsonStructure([$osmId])
            ->assertJson([
                $osmId => $hikingRoute->updated_at->format('Y-m-d H:i:s'),
            ]);
    }

    public function test_hiking_route_show()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        $response = $this->get('/api/v2/hiking-route/'.$hikingRoute->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'properties' => [
                    'id',
                    'relation_id',
                    'source',
                    'cai_scale',
                    'from',
                    'to',
                    'ref',
                    'public_page',
                    'sda',
                    'issues_status',
                    'issues_description',
                    'issues_last_update',
                    'updated_at',
                    'itinerary',
                ],
                'geometry',
            ]);
    }

    public function test_hiking_route_tdh()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        $response = $this->get('/api/v2/hiking-route-tdh/'.$hikingRoute->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'properties' => [
                        'id',
                        'created_at',
                        'updated_at',
                        'osm2cai_status',
                        'validation_date',
                        'relation_id',
                        'ref',
                        'ref_REI',
                        'gpx_url',
                        'cai_scale',
                        'cai_scale_string',
                        'cai_scale_description',
                        'survey_date',
                        'from',
                        'city_from',
                        'city_from_istat',
                        'region_from',
                        'region_from_istat',
                        'to',
                        'city_to',
                        'city_to_istat',
                        'region_to',
                        'region_to_istat',
                        'name',
                        'roundtrip',
                        'abstract',
                        'distance',
                        'ascent',
                        'descent',
                        'duration_forward',
                        'duration_backward',
                        'ele_from',
                        'ele_to',
                        'ele_max',
                        'ele_min',
                        'issues_status',
                        'issues_last_update',
                        'issues_description',
                    ],
                    'geometry',
                ],
            ]);
    }

    public function test_hiking_route_show_by_osm_id()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        $response = $this->get('/api/v2/hiking-route-osm/'.$hikingRoute->osmfeatures_data['properties']['osm_id']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'properties',
                'geometry',
            ]);
    }

    public function test_hiking_route_index_by_bounding_box()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        $response = $this->get('/api/v2/hiking-routes/bb/5,5,25,25/4');

        $response->assertStatus(200)
            ->assertJson([$hikingRoute->id => $hikingRoute->updated_at->format('Y-m-d H:i:s')]);
    }

    public function test_hiking_route_osm_index_by_bounding_box()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        $response = $this->get('/api/v2/hiking-routes-osm/bb/5,5,25,25/4');

        $response->assertStatus(200)
            ->assertJson([$hikingRoute->osmfeatures_data['properties']['osm_id'] => $hikingRoute->updated_at->format('Y-m-d H:i:s')]);
    }

    public function test_hiking_route_collection_by_bounding_box()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        $response = $this->get('/api/v2/hiking-routes-collection/bb/10.0,45.0,10.3,45.2/4');

        $response->assertStatus(200);
    }

    public function test_hiking_route_show_returns_404_when_not_found()
    {
        $response = $this->get('/api/v2/hiking-route/999999');

        $response->assertStatus(404)
            ->assertSee('No Hiking Route found with this id');
    }

    public function test_hiking_route_show_returns_404_when_no_geometry()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);
        // Rimuovi la geometria
        DB::table('hiking_routes')
            ->where('id', $hikingRoute->id)
            ->update(['geometry' => null]);

        $response = $this->get('/api/v2/hiking-route/'.$hikingRoute->id);

        $response->assertStatus(404)
            ->assertSee('No geometry found for this Hiking Route');
    }

    public function test_hiking_route_show_by_osm_id_returns_404_when_not_found()
    {
        $response = $this->get('/api/v2/hiking-route-osm/999999');

        $response->assertStatus(404)
            ->assertSee('No Hiking Route found with this id');
    }

    public function test_hiking_route_show_by_osm_id_returns_404_when_no_geometry()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);
        // Rimuovi la geometria
        DB::table('hiking_routes')
            ->where('id', $hikingRoute->id)
            ->update(['geometry' => null]);

        $response = $this->get('/api/v2/hiking-route-osm/12345');

        $response->assertStatus(404)
            ->assertSee('No geometry found for this Hiking Route');
    }

    public function test_hiking_route_collection_by_bounding_box_returns_500_when_area_too_large()
    {
        // Crea un bounding box troppo grande
        $response = $this->get('/api/v2/hiking-routes-collection/bb/0,0,180,180/4');

        $response->assertStatus(500)
            ->assertJson(['error' => 'Bounding box is too large']);
    }

    public function test_hiking_route_collection_by_bounding_box_returns_empty_collection_when_no_routes()
    {
        $response = $this->get('/api/v2/hiking-routes-collection/bb/10.0,45.0,10.1,45.1/4');

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'FeatureCollection',
                'features' => [],
            ]);
    }

    public function test_hiking_route_tdh_returns_404_when_not_found()
    {
        $response = $this->get('/api/v2/hiking-route-tdh/999999');

        $response->assertStatus(404);
    }

    public function test_hiking_route_index_by_region_returns_404_when_no_routes()
    {
        $region = $this->createTestRegion('L');

        $response = $this->get('/api/v2/hiking-routes/region/'.$region->code.'/4');

        $response->assertStatus(404);
    }

    public function test_hiking_route_osm_index_by_region_returns_empty_array_when_no_routes()
    {
        $region = $this->createTestRegion('L');

        $response = $this->get('/api/v2/hiking-routes-osm/region/'.$region->code.'/4');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    public function test_hiking_route_index_by_bounding_box_returns_empty_array_when_no_routes()
    {
        $response = $this->get('/api/v2/hiking-routes/bb/10.0,45.0,10.1,45.1/4');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    public function test_hiking_route_osm_index_by_bounding_box_returns_empty_array_when_no_routes()
    {
        $response = $this->get('/api/v2/hiking-routes-osm/bb/10.0,45.0,10.1,45.1/4');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    public function test_hiking_route_show_handles_invalid_osmfeatures_data()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        DB::table('hiking_routes')
            ->where('id', $hikingRoute->id)
            ->update(['osmfeatures_data' => ['invalid_data']]);

        $response = $this->get('/api/v2/hiking-route/'.$hikingRoute->id);

        $response->assertStatus(500)
            ->assertSee('Error processing Hiking Route');
    }

    public function test_hiking_route_show_by_osm_id_handles_invalid_osmfeatures_data()
    {
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);
        // Imposta osmfeatures_data non valido
        DB::table('hiking_routes')
            ->where('id', $hikingRoute->id)
            ->update(['osmfeatures_data' => ['invalid_data']]);

        $response = $this->get('/api/v2/hiking-route-osm/'.$hikingRoute->osmfeatures_data['properties']['osm_id']);

        $response->assertStatus(404)
            ->assertSee('No Hiking Route found with this id');
    }
}
