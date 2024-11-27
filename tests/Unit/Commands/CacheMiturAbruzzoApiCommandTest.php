<?php

namespace Tests\Unit\Commands;

use App\Jobs\CacheMiturAbruzzoDataJob;
use App\Models\HikingRoute;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CacheMiturAbruzzoApiCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_processes_hiking_routes_with_status_4()
    {
        // Create some routes with different statuses
        HikingRoute::factory()->create([
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((1 1, 2 2))', 4326)"),
        ]);
        HikingRoute::factory()->create([
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((3 3, 4 4))', 4326)"),
        ]);
        HikingRoute::factory()->create([
            'osm2cai_status' => 3,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((5 5, 6 6))', 4326)"),
        ]); // should not be processed

        $this->artisan('osm2cai:cache-mitur-abruzzo-api', ['model' => 'HikingRoute'])
            ->expectsOutput('Processing 2 HikingRoute')
            ->assertSuccessful();

        Queue::assertPushed(CacheMiturAbruzzoDataJob::class, 2);
    }

    /** @test */
    public function it_processes_specific_hiking_route_by_id()
    {
        $route = HikingRoute::factory()->create([
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((1 1, 2 2))', 4326)"),
        ]);

        $this->artisan('osm2cai:cache-mitur-abruzzo-api', [
            'model' => 'HikingRoute',
            'id' => $route->id,
        ])
            ->expectsOutput('Processing 1 HikingRoute')
            ->assertSuccessful();

        Queue::assertPushed(CacheMiturAbruzzoDataJob::class, 1);
    }

    /** @test */
    public function it_shows_error_when_no_hiking_routes_found()
    {
        $this->artisan('osm2cai:cache-mitur-abruzzo-api', ['model' => 'HikingRoute'])
            ->expectsOutput('No hiking routes found with osm2cai_status 4')
            ->assertSuccessful();

        Queue::assertNotPushed(CacheMiturAbruzzoDataJob::class);
    }

    /** @test */
    public function it_processes_all_regions()
    {
        Region::factory()->count(3)->create();

        $this->artisan('osm2cai:cache-mitur-abruzzo-api', ['model' => 'Region'])
            ->expectsOutput('Processing 3 Region')
            ->assertSuccessful();

        Queue::assertPushed(CacheMiturAbruzzoDataJob::class, 3);
    }

    /** @test */
    public function it_shows_error_for_invalid_model()
    {
        $this->artisan('osm2cai:cache-mitur-abruzzo-api', ['model' => 'InvalidModel'])
            ->expectsOutput('Target class [App\Models\InvalidModel] does not exist.')
            ->assertSuccessful();

        Queue::assertNotPushed(CacheMiturAbruzzoDataJob::class);
    }
}
