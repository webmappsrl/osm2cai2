<?php

namespace Tests\Api;

use App\Models\HikingRoute;
use App\Models\Region;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HikingRoutesRegionControllerV1Test extends TestCase
{
    use RefreshDatabase;

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
            'osmfeatures_data' => [
                'properties' => [
                    'osm_id' => $osm_id,
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

    public function test_hiking_route_list_by_region()
    {
        // Prepara i dati di test
        $region = $this->createTestRegion('L');
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        // Crea la relazione nella tabella pivot
        DB::table('hiking_route_region')->insert([
            'hiking_route_id' => $hikingRoute->id,
            'region_id' => $region->id,
        ]);

        // Esegui la richiesta
        $response = $this->get('/api/v1/hiking-routes/region/L/4');

        // Verifica la risposta
        $response->assertStatus(200)
            ->assertJson([$hikingRoute->id]);
    }

    public function test_hiking_route_list_by_region_not_found()
    {
        $response = $this->get('/api/v1/hiking-routes/region/X/4');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Region not found with code X']);
    }

    public function test_hiking_route_list_by_region_no_routes()
    {
        $region = $this->createTestRegion('L');

        $response = $this->get('/api/v1/hiking-routes/region/L/4');

        $response->assertStatus(404)
            ->assertJson(['error' => 'No hiking routes found for region L and SDA 4']);
    }

    public function test_hiking_route_osm_list_by_region()
    {
        // Prepara i dati di test
        $region = $this->createTestRegion('L');
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        // Crea la relazione nella tabella pivot
        DB::table('hiking_route_region')->insert([
            'hiking_route_id' => $hikingRoute->id,
            'region_id' => $region->id,
        ]);

        // Aggiungi osmfeatures_data
        $hikingRoute->osmfeatures_data = [
            'properties' => [
                'osm_id' => 12345,
            ],
        ];
        $hikingRoute->save();

        // Esegui la richiesta
        $response = $this->get('/api/v1/hiking-routes-osm/region/L/4');

        // Verifica la risposta
        $response->assertStatus(200)
            ->assertJson([12345]);
    }

    public function test_hiking_route_by_id()
    {
        // Prepara i dati di test
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        // Esegui la richiesta
        $response = $this->get('/api/v1/hiking-route/'.$hikingRoute->id);

        // Verifica la risposta
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
                ],
                'geometry',
            ]);
    }

    public function test_hiking_route_by_id_not_found()
    {
        DB::table('hiking_routes')->truncate();

        $nonExistentId = 999;
        $response = $this->get('/api/v1/hiking-route/'.$nonExistentId);

        $response->assertStatus(404)
            ->assertSee('No Hiking Route found with this id');
    }

    public function test_hiking_route_by_osm_id()
    {
        // Prepara i dati di test
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        // Esegui la richiesta
        $response = $this->get('/api/v1/hiking-route-osm/'.$hikingRoute->osmfeatures_data['properties']['osm_id']);

        // Verifica la risposta
        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'properties',
                'geometry',
            ]);
    }

    public function test_hiking_route_by_osm_id_not_found()
    {
        $response = $this->get('/api/v1/hiking-route-osm/999');

        $response->assertStatus(404)
            ->assertSee('No Hiking Route found with this OSMid');
    }

    public function test_hiking_route_list_by_bounding_box()
    {
        // Prepara i dati di test
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        // Esegui la richiesta con un bounding box che contiene il percorso
        $response = $this->get('/api/v1/hiking-routes/bb/5,5,25,25/4');

        // Verifica la risposta
        $response->assertStatus(200)
            ->assertJson([$hikingRoute->id]);
    }

    public function test_hiking_route_osm_list_by_bounding_box()
    {
        // Prepara i dati di test
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        // Esegui la richiesta con un bounding box che contiene il percorso
        $response = $this->get('/api/v1/hiking-routes-osm/bb/5,5,25,25/4');

        // Verifica la risposta
        $response->assertStatus(200)
            ->assertJson([$hikingRoute->osmfeatures_data['properties']['osm_id']]);
    }

    public function test_hiking_route_collection_by_bounding_box()
    {
        // Prepara i dati di test
        $hikingRoute = $this->createTestHikingRoute(1, 12345, 4);

        // Esegui la richiesta con un bounding box valido (area < 0.1)
        $response = $this->get('/api/v1/hiking-routes-collection/bb/10.0,45.0,10.3,45.2/4');

        // Verifica la risposta
        $response->assertStatus(200);
    }

    public function test_hiking_route_collection_too_large_bounding_box()
    {
        // Esegui la richiesta con un bounding box troppo grande
        $response = $this->get('/api/v1/hiking-routes-collection/bb/0,0,10,10/4');

        // Verifica che la risposta sia un errore
        $response->assertStatus(500)
            ->assertJson(['error' => 'Bounding box is too large']);
    }
}
