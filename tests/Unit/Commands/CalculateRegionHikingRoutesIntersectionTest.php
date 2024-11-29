<?php

namespace Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateRegionHikingRoutesIntersectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_calculates_intersections_successfully()
    {

        //execute command
        $this->artisan('osm2cai:calculate-region-hiking-routes-intersection')
            ->expectsOutput('Dispatching recalculate intersections jobs...')
            ->expectsOutput('Recalculate intersections jobs dispatched successfully.')
            ->assertSuccessful();
    }

    /** @test */
    public function it_handles_intersection_service_exception()
    {
        $errorMessage = 'Test error message';

        // Execute command and check exception handling
        $this->artisan('osm2cai:calculate-region-hiking-routes-intersection')
            ->expectsOutput('Dispatching recalculate intersections jobs...')
            ->expectsOutput($errorMessage)
            ->assertFailed();
    }
}
