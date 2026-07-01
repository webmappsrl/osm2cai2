<?php

namespace Tests\Unit\Traits;

use App\Models\CaiHut;
use App\Models\EcPoi;
use App\Models\HikingRoute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GeoBufferTraitTest extends TestCase
{
    use DatabaseTransactions;

    private function createHikingRouteWithGeometry(string $geometry): HikingRoute
    {
        return HikingRoute::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('{$geometry}', 4326)"),
        ]);
    }

    private function createEcPoiWithGeometry(string $geometry): EcPoi
    {
        return EcPoi::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('{$geometry}', 4326)"),
        ]);
    }

    private function createCaiHutWithGeometry(string $geometry): CaiHut
    {
        return CaiHut::createQuietly([
            'name' => 'Test Hut',
            'geometry' => DB::raw("ST_GeomFromText('{$geometry}', 4326)"),
        ]);
    }

    /** @test */
    public function it_returns_elements_within_buffer_distance()
    {
        // Create a hiking route (source)
        $hikingRoute = $this->createHikingRouteWithGeometry('LINESTRING(14.0000 37.0000, 14.0010 37.0010, 14.0020 37.0020)');

        // Create POIs: one near, one far
        $nearPoi = $this->createEcPoiWithGeometry('POINT(14.0011 37.0011)');
        $farPoi = $this->createEcPoiWithGeometry('POINT(15.0354462 37.8025841)');

        // Get elements within 1000 meters buffer
        $result = $hikingRoute->getElementsInBuffer(new EcPoi, 1000);

        $resultIds = $result->pluck('id')->toArray();

        $this->assertContains($nearPoi->id, $resultIds);
        $this->assertNotContains($farPoi->id, $resultIds);
        $this->assertTrue($result->contains($nearPoi));
        $this->assertFalse($result->contains($farPoi));
    }

    /** @test */
    public function it_returns_empty_collection_if_no_elements_within_buffer()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry('LINESTRING(14.0000 37.0000, 14.0010 37.0010, 14.0020 37.0020)');

        $farPoi = $this->createEcPoiWithGeometry('POINT(15.0354462 37.8025841)');

        $result = $hikingRoute->getElementsInBuffer(new EcPoi, 100);

        $this->assertFalse($result->contains($farPoi));
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    /** @test */
    public function it_works_with_different_model_types()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry('LINESTRING(12.4924 41.8902, 12.4925 41.8903)');

        // Test with CaiHut
        $nearHut = $this->createCaiHutWithGeometry('POINT(12.4926 41.8904)');
        $farHut = $this->createCaiHutWithGeometry('POINT(15.0354462 37.8025841)');

        $result = $hikingRoute->getElementsInBuffer(new CaiHut, 1000);

        $resultIds = $result->pluck('id')->toArray();

        $this->assertContains($nearHut->id, $resultIds);
        $this->assertNotContains($farHut->id, $resultIds);
    }

    /** @test */
    public function it_returns_empty_collection_when_source_has_no_geometry()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Model has no geometry', \Mockery::type('array'));

        $hikingRoute = HikingRoute::createQuietly([
            'geometry' => null,
        ]);

        $poi = $this->createEcPoiWithGeometry('POINT(14.0011 37.0011)');

        $result = $hikingRoute->getElementsInBuffer(new EcPoi, 1000);

        $this->assertTrue($result->isEmpty());
        $this->assertCount(0, $result);
        $this->assertFalse($result->contains($poi));
    }

    /** @test */
    public function it_respects_buffer_distance()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry('LINESTRING(14.0000 37.0000, 14.0010 37.0010)');

        // Create a POI that is at a specific distance from the route
        // This point is approximately 100-150 meters from the line
        // Using a point that's clearly outside a small buffer but inside a large one
        $poi = $this->createEcPoiWithGeometry('POINT(14.0020 37.0020)');

        // With small buffer (50 meters), should not find it (point is ~140m away)
        $resultSmall = $hikingRoute->getElementsInBuffer(new EcPoi, 50);
        $this->assertFalse($resultSmall->contains($poi), 'POI should not be found with small buffer');

        // With larger buffer, should find it
        $resultLarge = $hikingRoute->getElementsInBuffer(new EcPoi, 10000);
        $this->assertTrue($resultLarge->contains($poi), 'POI should be found with large buffer');
    }

    /** @test */
    public function it_returns_multiple_elements_within_buffer()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry('LINESTRING(14.0000 37.0000, 14.0010 37.0010, 14.0020 37.0020)');

        $poi1 = $this->createEcPoiWithGeometry('POINT(14.0011 37.0011)');
        $poi2 = $this->createEcPoiWithGeometry('POINT(14.0015 37.0015)');
        $poi3 = $this->createEcPoiWithGeometry('POINT(14.0021 37.0021)');
        $farPoi = $this->createEcPoiWithGeometry('POINT(15.0354462 37.8025841)');

        $result = $hikingRoute->getElementsInBuffer(new EcPoi, 1000);

        $resultIds = $result->pluck('id')->toArray();

        $this->assertContains($poi1->id, $resultIds);
        $this->assertContains($poi2->id, $resultIds);
        $this->assertContains($poi3->id, $resultIds);
        $this->assertNotContains($farPoi->id, $resultIds);
        $this->assertGreaterThanOrEqual(3, $result->count());
    }
}
