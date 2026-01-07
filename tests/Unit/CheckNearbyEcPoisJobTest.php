<?php

namespace Tests\Unit;

use App\Jobs\CheckNearbyEcPoisJob;
use App\Models\EcPoi;
use App\Models\HikingRoute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CheckNearbyEcPoisJobTest extends TestCase
{
    use DatabaseTransactions;

    public function test_handles_hiking_route_with_geometry(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999999, // setting id for passing tests in local with populated database
            'geometry' => DB::raw("ST_GeomFromText('LINESTRINGZ(0 0 0, 1 1 0)', 4326)"),
        ]);

        $ecPoi = EcPoi::factory()->createQuietly([
            'id' => 999999999,
            'geometry' => DB::raw("ST_GeomFromText('POINTZ(0.5 0.5 0)', 4326)"),
        ]);

        $buffer = 1000;

        (new CheckNearbyEcPoisJob($hikingRoute, $buffer))->handle();

        // ensure that the relation exists
        $this->assertTrue(DB::table('hiking_route_ec_poi')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function test_handles_hiking_route_with_geometry_and_finds_nearby_ec_poi(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999998,
            'geometry' => DB::raw("ST_GeomFromText('LINESTRINGZ(12.4924 41.8902 0, 12.4925 41.8903 0)', 4326)"),
        ]);

        $ecPoi = EcPoi::factory()->createQuietly([
            'id' => 999999998,
            'geometry' => DB::raw("ST_GeomFromText('POINTZ(12.4926 41.8904 0)', 4326)"),
        ]);

        $buffer = 1000;

        (new CheckNearbyEcPoisJob($hikingRoute, $buffer))->handle();

        $this->assertTrue(DB::table('hiking_route_ec_poi')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function test_logs_warning_if_geometry_is_missing(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999997,
            'geometry' => null,
        ]);

        $buffer = 1000;

        Log::shouldReceive('info')
            ->zeroOrMoreTimes();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) use ($hikingRoute) {
                return str_contains($message, "{$hikingRoute->getTable()} {$hikingRoute->id} has no geometry");
            });

        (new CheckNearbyEcPoisJob($hikingRoute, $buffer))->handle();
    }

    public function test_no_nearby_ec_pois_found(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999996,
            'geometry' => DB::raw("ST_GeomFromText('LINESTRINGZ(0 0 0, 1 1 0)', 4326)"),
        ]);

        $buffer = 1000;

        (new CheckNearbyEcPoisJob($hikingRoute, $buffer))->handle();

        $this->assertFalse(DB::table('hiking_route_ec_poi')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function test_zero_buffer_finds_no_ec_pois(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999995,
            'geometry' => DB::raw("ST_GeomFromText('LINESTRINGZ(12.4924 41.8902 0, 12.4925 41.8903 0)', 4326)"),
        ]);

        EcPoi::factory()->createQuietly([
            'id' => 999999995,
            'geometry' => DB::raw("ST_GeomFromText('POINTZ(12.4926 41.8904 0)', 4326)"),
        ]);

        (new CheckNearbyEcPoisJob($hikingRoute, 0))->handle();

        $this->assertFalse(DB::table('hiking_route_ec_poi')->where('hiking_route_id', $hikingRoute->id)->exists());
    }

    public function test_logs_warning_if_buffer_is_negative(): void
    {
        $hikingRoute = HikingRoute::factory()->createQuietly([
            'id' => 999999994,
            'geometry' => DB::raw("ST_GeomFromText('LINESTRINGZ(0 0 0, 1 1 0)', 4326)"),
        ]);

        Log::shouldReceive('info')
            ->zeroOrMoreTimes();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Buffer distance must be positive');
            });

        (new CheckNearbyEcPoisJob($hikingRoute, -100))->handle();
    }
}
