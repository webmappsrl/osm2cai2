<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\HikingRoute;
use App\Models\NaturalSpring;
use App\Jobs\CheckNearbyNaturalSpringsJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CheckNearbyNaturalSpringsJobTest extends TestCase
{
    use DatabaseTransactions;

    public function testHandlesHikingRouteWithGeometry(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
        ]);

        $naturalSpring = NaturalSpring::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;POINT(0.5 0.5)',
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        (new CheckNearbyNaturalSpringsJob($hikingRoute, $buffer))->handle();

        $this->assertTrue(DB::table('hiking_route_natural_spring')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function testHandlesHikingRouteWithGeometryAndFindsNearbyNaturalSpring(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;LINESTRING(12.4924 41.8902, 12.4925 41.8903)',
        ]);

        $naturalSpring = NaturalSpring::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;POINT(12.4926 41.8904)',
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        (new CheckNearbyNaturalSpringsJob($hikingRoute, $buffer))->handle();

        $this->assertTrue(DB::table('hiking_route_natural_spring')->where('hiking_route_id', $hikingRoute->id)->exists());
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

        (new CheckNearbyNaturalSpringsJob($hikingRoute, $buffer))->handle();
    }

    public function testNoNearbyNaturalSpringsFound(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        (new CheckNearbyNaturalSpringsJob($hikingRoute, $buffer))->handle();

        $this->assertFalse(DB::table('hiking_route_natural_spring')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function testZeroBufferFindsNoSprings(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;LINESTRING(12.4924 41.8902, 12.4925 41.8903)',
        ]);

        NaturalSpring::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => 'SRID=4326;POINT(12.4926 41.8904)',
        ]);

        (new CheckNearbyNaturalSpringsJob($hikingRoute, 0))->handle();

        $this->assertFalse(DB::table('hiking_route_natural_spring')->where('hiking_route_id', $hikingRoute->id)->exists());
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

        (new CheckNearbyNaturalSpringsJob($hikingRoute, -100))->handle();
    }
}
