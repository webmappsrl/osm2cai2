<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Region;
use App\Models\HikingRoute;
use Illuminate\Support\Facades\DB;
use App\Services\IntersectionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IntersectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $intersectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intersectionService = new IntersectionService();
        Artisan::call('wm-osmfeatures:initialize-tables');
    }

    private function createRegion($name, $wkt)
    {
        return DB::statement("INSERT INTO regions (name, geometry) VALUES (?, ST_GeomFromText(?))", [$name, $wkt]);
    }

    private function createHikingRoute($osmfeaturesId, $geometry)
    {
        return HikingRoute::create([
            'osmfeatures_id' => $osmfeaturesId,
            'osmfeatures_data' => [
                'geometry' => $geometry,
                'osm2cai_status' => 'test',
                'validation_date' => now()->toDateString(),
                'issues_status' => 'none',
                'issues_last_update' => now()->toDateString(),
                'issues_user_id' => 1,
                'issues_chronology' => [],
                'issues_description' => '',
                'description_cai_it' => 'Test route'
            ]
        ]);
    }

    public function testCalculateForRegion()
    {
        // Crea una regione di test
        $this->createRegion('Test Region', 'POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))');
        $region = Region::where('name', 'Test Region')->first();

        // Crea alcuni percorsi escursionistici che intersecano la regione
        $intersectingRoute = $this->createHikingRoute('test_intersecting', [
            'type' => 'LineString',
            'coordinates' => [[0.0, 0.0], [1.0, 1.0]]
        ]);

        $nonIntersectingRoute = $this->createHikingRoute('test_non_intersecting', [
            'type' => 'LineString',
            'coordinates' => [[2, 2], [3, 3]]
        ]);

        $result = $this->intersectionService->calculateForRegion($region);

        $this->assertArrayHasKey('test_intersecting', $result);
        $this->assertArrayNotHasKey('test_non_intersecting', $result);
    }

    public function testCalculateForHikingRoute()
    {
        // Crea un percorso escursionistico di test
        $hikingRoute = $this->createHikingRoute('test_route', [
            'type' => 'LineString',
            'coordinates' => [[0.5, 0.5], [1.5, 1.5]]
        ]);

        // Crea alcune regioni che intersecano il percorso
        $this->createRegion('Intersecting Region', 'POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))');
        $this->createRegion('Non-Intersecting Region', 'POLYGON((2 2, 2 3, 3 3, 3 2, 2 2))');

        $this->intersectionService->calculateForHikingRoute($hikingRoute);

        $intersectingRegion = Region::where('name', 'Intersecting Region')->first();
        $nonIntersectingRegion = Region::where('name', 'Non-Intersecting Region')->first();

        $this->assertNotEmpty($intersectingRegion->hiking_routes_intersecting);
        $this->assertEmpty($nonIntersectingRegion->hiking_routes_intersecting);
    }

    public function testCalculateForAllRegions()
    {
        // Crea alcune regioni di test
        $this->createRegion('Region 1', 'POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))');
        $this->createRegion('Region 2', 'POLYGON((1 1, 1 2, 2 2, 2 1, 1 1))');

        // Crea alcuni percorsi escursionistici
        $this->createHikingRoute('test_route', [
            'type' => 'LineString',
            'coordinates' => [[0.5, 0.5], [1.5, 1.5]]
        ]);

        $this->intersectionService->calculateForAllRegions();

        $region1 = Region::where('name', 'Region 1')->first();
        $region2 = Region::where('name', 'Region 2')->first();

        $this->assertNotEmpty($region1->hiking_routes_intersecting);
        $this->assertNotEmpty($region2->hiking_routes_intersecting);
    }
}
