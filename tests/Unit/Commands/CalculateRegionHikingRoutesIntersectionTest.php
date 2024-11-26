<?php

namespace Tests\Unit\Commands;

use App\Services\IntersectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CalculateRegionHikingRoutesIntersectionTest extends TestCase
{
    use RefreshDatabase;

    protected $intersectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intersectionService = Mockery::mock(IntersectionService::class);
        $this->app->instance(IntersectionService::class, $this->intersectionService);
    }

    /** @test */
    public function it_calculates_intersections_successfully()
    {
        //configure mock
        $this->intersectionService
            ->shouldReceive('calculateIntersections')
            ->once();

        //execute command
        $this->artisan('osm2cai:calculate-region-hiking-routes-intersection')
            ->expectsOutput('Start calculating intersections...')
            ->expectsOutput('Calculating intersections completed successfully.')
            ->assertSuccessful();
    }

    /** @test */
    public function it_handles_intersection_service_exception()
    {
        $errorMessage = 'Test error message';

        // Configure mock to throw exception
        $this->intersectionService
            ->shouldReceive('calculateIntersections')
            ->once()
            ->andThrow(new \Exception($errorMessage));

        // Execute command and check exception handling
        $this->artisan('osm2cai:calculate-region-hiking-routes-intersection')
            ->expectsOutput('Start calculating intersections...')
            ->expectsOutput($errorMessage)
            ->assertFailed();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
