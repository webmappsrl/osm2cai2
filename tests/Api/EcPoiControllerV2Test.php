<?php

namespace Tests\Api;

use App\Models\EcPoi;
use App\Models\HikingRoute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EcPoiControllerV2Test extends TestCase
{
    use DatabaseTransactions;

    // app_id delle tappe Sentiero Italia (SiHikingRoute, SiMTBRoute)
    private const SI_APP_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function createTestHikingRoute(int $osmId, string $wkt, int $appId = HikingRoute::OSM2CAI_APP_ID): HikingRoute
    {
        return HikingRoute::createQuietly([
            'app_id' => $appId,
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
            'osmfeatures_data' => ['properties' => ['osm_id' => $osmId]],
        ]);
    }

    private function createTestEcPoi(string $wkt): EcPoi
    {
        return EcPoi::createQuietly([
            'app_id' => HikingRoute::OSM2CAI_APP_ID,
            'name' => ['it' => 'Test POI'],
            'geometry' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
            'osmfeatures_data' => ['properties' => ['osm_type' => 'N']],
        ]);
    }

    public function test_ecpois_by_osm_id_uses_trail_geometry_not_si_stage()
    {
        $osmId = 999000021;
        // La tappa SI va creata prima del sentiero: senza filtro su app_id la query restituirebbe la prima riga trovata.
        // Tappa e sentiero sono lontani fra loro: il POI è entro 1 km solo dal sentiero.
        $this->createTestHikingRoute($osmId, 'LINESTRING(10 10, 10.1 10)', self::SI_APP_ID);
        $this->createTestHikingRoute($osmId, 'LINESTRING(12 12, 12.1 12)');
        $poi = $this->createTestEcPoi('POINT(12.05 12.001)');

        $response = $this->get("/api/v2/ecpois/osm/{$osmId}/N");

        $ids = collect($response->assertStatus(200)->json())->pluck('id');
        $this->assertTrue($ids->contains($poi->id));
    }
}
