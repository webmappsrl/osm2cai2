<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\CaiHut;
use App\Models\HikingRoute;
use App\Jobs\CheckNearbyHutsJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CheckNearbyHutsJobTest extends TestCase
{
    use DatabaseTransactions;

    public function testHandlesHikingRouteWithGeometry(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999, //setting id for passing tests in local with populated database
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
        ]);

        $caiHut = CaiHut::factory()->createQuietly([
            'id' => 999999999,
            'name' => 'Test Hut',
            'geometry' => 'SRID=4326;POINT(0.5 0.5)',
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();

        //ensure that the relation exists
        $this->assertTrue(DB::table('hiking_route_cai_hut')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function testHandlesHikingRouteWithGeometryAndFindsNearbyCaiHut(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;LINESTRING(12.4924 41.8902, 12.4925 41.8903)',
        ]);

        $caiHut = CaiHut::factory()->createQuietly([
            'id' => 999999999,
            'name' => 'Test Hut',
            'geometry' => 'SRID=4326;POINT(12.4926 41.8904)',
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();

        $this->assertTrue(DB::table('hiking_route_cai_hut')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function testLogsWarningIfGeometryIsMissing(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => null,
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');


        Log::shouldReceive('info')
            ->zeroOrMoreTimes();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) use ($hikingRoute) {
                return str_contains($message, "{$hikingRoute->getTable()} {$hikingRoute->id} has no geometry");
            });

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();
    }

    public function testNoNearbyCaiHutsFound(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();

        $this->assertFalse(DB::table('hiking_route_cai_hut')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function testZeroBufferFindsNoHuts(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;LINESTRING(12.4924 41.8902, 12.4925 41.8903)',
        ]);

        CaiHut::factory()->createQuietly([
            'id' => 999999999,
            'name' => 'Test Hut',
            'geometry' => 'SRID=4326;POINT(12.4926 41.8904)',
        ]);

        (new CheckNearbyHutsJob($hikingRoute, 0))->handle();

        $this->assertFalse(DB::table('hiking_route_cai_hut')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function testLogsWarningIfBufferIsNegative(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
        ]);

        Log::shouldReceive('info')
            ->zeroOrMoreTimes();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Buffer distance must be positive');
            });

        (new CheckNearbyHutsJob($hikingRoute, -100))->handle();
    }
}
