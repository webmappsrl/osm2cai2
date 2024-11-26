<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CheckNearbyHutsJob;
use App\Models\HikingRoute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CheckNearbyHutsJobTest extends TestCase
{
    use DatabaseTransactions;

    public function testHandlesHikingRouteWithGeometry(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
            'nearby_cai_huts' => json_encode([1]),
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        // Mock query
        DB::shouldReceive('select')
            ->once()
            ->withArgs(function ($query, $bindings) use ($hikingRoute, $buffer) {
                return $bindings['routeId'] === $hikingRoute->id && $bindings['buffer'] === $buffer;
            })
            ->andReturn([(object) ['id' => 2]]);

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();

        $hikingRoute->refresh();
        $this->assertEquals([2], json_decode($hikingRoute->nearby_cai_huts));
    }

    public function testHandlesHikingRouteWithGeometryAndFindsNearbyCaiHut(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(12.4924 41.8902, 12.4925 41.8903)',
            'nearby_cai_huts' => json_encode([]),
        ]);

        $caiHut = \App\Models\CaiHut::factory()->create([
            'name' => 'Test Hut',
            'geometry' => 'SRID=4326;POINT(12.4926 41.8904)',
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();

        $hikingRoute->refresh();

        $this->assertEquals([$caiHut->id], json_decode($hikingRoute->nearby_cai_huts));
    }

    public function testDoesNotUpdateIfNoChange(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
            'nearby_cai_huts' => json_encode([1, 2]),
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        DB::shouldReceive('select')
            ->once()
            ->andReturn([(object) ['id' => 1], (object) ['id' => 2]]);

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();

        $hikingRoute->refresh();
        $this->assertEquals([1, 2], json_decode($hikingRoute->nearby_cai_huts));
    }

    public function testLogsWarningIfGeometryIsMissing(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => null,
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) use ($hikingRoute) {
                return str_contains($message, "Hiking route {$hikingRoute->id} has no geometry");
            });

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();
    }

    public function testNoNearbyCaiHutsFound(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
            'nearby_cai_huts' => json_encode([]),
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();

        $hikingRoute->refresh();
        $this->assertEquals([], json_decode($hikingRoute->nearby_cai_huts));
    }

    public function testHandlesSqlExceptionGracefully(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
            'nearby_cai_huts' => json_encode([]),
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        DB::shouldReceive('select')
            ->once()
            ->andThrow(new \Exception('Database error'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($hikingRoute) {
                return str_contains($message, 'Error executing CheckNearbyHutsJob') &&
                    $context['route_id'] === $hikingRoute->id;
            });

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();
    }

    public function testUpdatesWithCompletelyDifferentNearbyHuts(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
            'nearby_cai_huts' => json_encode([1, 2]),
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        DB::shouldReceive('select')
            ->once()
            ->andReturn([(object) ['id' => 3], (object) ['id' => 4]]);

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();

        $hikingRoute->refresh();
        $this->assertEquals([3, 4], json_decode($hikingRoute->nearby_cai_huts));
    }

    public function testZeroBufferFindsNoHuts(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(12.4924 41.8902, 12.4925 41.8903)',
            'nearby_cai_huts' => json_encode([]),
        ]);

        $caiHut = \App\Models\CaiHut::factory()->create([
            'name' => 'Test Hut',
            'geometry' => 'SRID=4326;POINT(12.4926 41.8904)',
        ]);

        $buffer = 0;

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();

        $hikingRoute->refresh();
        $this->assertEquals([], json_decode($hikingRoute->nearby_cai_huts));
    }

    public function testLogsWarningIfBufferIsNegative(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
            'nearby_cai_huts' => json_encode([]),
        ]);

        $buffer = -100;

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Buffer distance must be positive');
            });

        (new CheckNearbyHutsJob($hikingRoute, $buffer))->handle();
    }
}
