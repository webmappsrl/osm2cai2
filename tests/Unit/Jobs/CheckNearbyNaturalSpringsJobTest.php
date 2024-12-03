<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CheckNearbyNaturalSpringsJob;
use App\Models\HikingRoute;
use App\Models\NaturalSpring;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class CheckNearbyNaturalSpringsJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('hiking_routes')->truncate();
        DB::table('natural_springs')->truncate();
    }

    public function testHandlesHikingRouteWithNearbySprings(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(12.4924 41.8902, 12.4925 41.8903)',
            'nearby_natural_springs' => json_encode([]),
        ]);

        $naturalSpring = NaturalSpring::factory()->create([
            'geometry' => 'SRID=4326;POINT(12.4926 41.8904)',
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        (new CheckNearbyNaturalSpringsJob($hikingRoute->id, $buffer))->handle();

        $hikingRoute->refresh();
        $this->assertEquals("[$naturalSpring->id]", $hikingRoute->nearby_natural_springs);
    }

    public function testLogsErrorIfHikingRouteNotFound(): void
    {
        $hikingRouteId = 99;
        $buffer = 1000;

        // Mock DB::table to return null
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('select')
            ->with(['id', 'geometry', 'nearby_natural_springs'])
            ->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->with('id', $hikingRouteId)
            ->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturn(null);
        DB::shouldReceive('table')->with('hiking_routes')->andReturn($mockQuery);

        // Mock logging
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'));

        // Execute the job
        $job = new CheckNearbyNaturalSpringsJob($hikingRouteId, $buffer);

        $job->handle();
    }

    public function testDoesNotUpdateIfNoChange(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
            'nearby_natural_springs' => json_encode([1, 2]),
        ]);

        DB::shouldReceive('select')
            ->once()
            ->andReturn([(object) ['id' => 1], (object) ['id' => 2]]);

        $buffer = config('osm2cai.hiking_route_buffer');

        (new CheckNearbyNaturalSpringsJob($hikingRoute->id, $buffer))->handle();

        $hikingRoute->refresh();
        $this->assertEquals([1, 2], json_decode($hikingRoute->nearby_natural_springs));
    }

    public function testLogsErrorIfGeometryIsMissing(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => null,
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message) use ($hikingRoute) {
                return str_contains($message, "Hiking route {$hikingRoute->id} has no geometry");
            });

        Log::shouldReceive('error')
            ->once()
            ->with('Error in CheckNearbyNaturalSpringsJob: Hiking route '.$hikingRoute->id.' has no geometry');

        (new CheckNearbyNaturalSpringsJob($hikingRoute->id, $buffer))->handle();
    }

    public function testHandlesSqlExceptionGracefully(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(0 0, 1 1)',
            'nearby_natural_springs' => json_encode([]),
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        DB::shouldReceive('select')
            ->once()
            ->andThrow(new \Exception('Database error'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message) use ($hikingRoute) {
                return str_contains($message, 'Error in CheckNearbyNaturalSpringsJob');
            });

        (new CheckNearbyNaturalSpringsJob($hikingRoute->id, $buffer))->handle();
    }

    public function testUpdatesCorrectlyWhenNearbySpringsChange(): void
    {
        $hikingRoute = HikingRoute::factory()->create([
            'geometry' => 'SRID=4326;LINESTRING(12.4924 41.8902, 12.4925 41.8903)',
            'nearby_natural_springs' => json_encode([1]),
        ]);

        $newSpring = NaturalSpring::factory()->create([
            'geometry' => 'SRID=4326;POINT(12.4926 41.8904)',
        ]);

        $buffer = config('osm2cai.hiking_route_buffer');

        DB::shouldReceive('select')
            ->once()
            ->andReturn([(object) ['id' => $newSpring->id]]);

        (new CheckNearbyNaturalSpringsJob($hikingRoute->id, $buffer))->handle();

        $hikingRoute->refresh();
        $this->assertEquals([$newSpring->id], json_decode($hikingRoute->nearby_natural_springs));
    }
}
