<?php

namespace Tests\Unit\Commands;

use App\Jobs\RecalculateIntersectionsJob;
use App\Models\Region;
use App\Models\HikingRoute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Mockery;

class CalculateRegionHikingRoutesIntersectionTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_calculates_intersections_successfully()
    {
        $this->artisan('osm2cai:calculate-region-hiking-routes-intersection')
            ->expectsOutput('Dispatching recalculate intersections jobs...')
            ->expectsOutput('Recalculate intersections jobs dispatched successfully.')
            ->assertSuccessful();
    }
}
