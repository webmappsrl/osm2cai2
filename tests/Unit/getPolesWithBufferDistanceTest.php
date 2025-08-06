<?php

namespace Tests\Unit;

use App\Models\HikingRoute;
use App\Models\Poles;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class getPolesWithBufferDistanceTest extends TestCase
{
    use DatabaseTransactions;

    private function createHikingRouteWithGeometry(string $geometry)
    {
        return HikingRoute::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('{$geometry}', 4326)"),
        ]);
    }

    private function createPoleWithGeometry($geometry)
    {
        return Poles::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('{$geometry}', 4326)"),
        ]);
    }

    /** @test */
    public function it_returns_poles_within_buffer_distance()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry('MULTILINESTRING(
            (14.0000 37.0000, 14.0010 37.0010, 14.0020 37.0020),
            (14.0020 37.0020, 14.0030 37.0030)
            )'
        );
        $nearPole = $this->createPoleWithGeometry('POINT(14.0011 37.0011)');
        $nearPoleId = $nearPole->id;

        $farPole = $this->createPoleWithGeometry('POINT(15.0354462 37.8025841)');
        $farPoleId = $farPole->id;

        $result = $hikingRoute->getPolesWithBuffer(10);
        $resultId = $result->pluck('id')->toArray();

        $this->assertContains($nearPoleId, $resultId);
        $this->assertNotContains($farPoleId, $resultId);
        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($nearPole));
        $this->assertFalse($result->contains($farPole));
    }

    /** @test */
    public function it_returns_empty_collection_if_no_poles_within_buffer()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry(14102, 'MULTILINESTRING(
            (14.0000 37.0000, 14.0010 37.0010, 14.0020 37.0020),
            (14.0020 37.0020, 14.0030 37.0030)
            )');

        $farPole = $this->createPoleWithGeometry(50228, 'POINT(15.0354462 37.8025841)');

        $result = $hikingRoute->getPolesWithBuffer(10);

        $this->assertFalse($result->contains($farPole));
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }
}
