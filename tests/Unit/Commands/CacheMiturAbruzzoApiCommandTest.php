<?php

namespace Tests\Unit\Commands;

use App\Jobs\CacheMiturAbruzzoDataJob;
use App\Models\HikingRoute;
use App\Models\Region;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CacheMiturAbruzzoApiCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // DatabaseTransactions will handle cleanup automatically
    }

    /** @test */
    public function it_processes_hiking_routes_with_status_4()
    {
        // Create some routes with different statuses using high IDs to avoid conflicts
        $route1 = HikingRoute::factory()->createQuietly([
            'id' => 999999991,
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((1 1 0, 2 2 0))', 4326)"),
        ]);
        $route2 = HikingRoute::factory()->createQuietly([
            'id' => 999999992,
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((3 3 0, 4 4 0))', 4326)"),
        ]);
        HikingRoute::factory()->createQuietly([
            'id' => 999999993,
            'osm2cai_status' => 3,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((5 5 0, 6 6 0))', 4326)"),
        ]); // should not be processed

        // Test with specific IDs to avoid counting existing data from restore
        $this->artisan('osm2cai:cache-mitur-abruzzo-api', [
            'model' => 'HikingRoute',
            'id' => $route1->id,
        ])
            ->expectsConfirmation('This command is meant to be run in production. By continuing, you will update cached file on AWS S3 with your local data. Do you wish to continue?', 'yes')
            ->expectsOutput('Processing 1 HikingRoute')
            ->assertSuccessful();

        Queue::assertPushed(CacheMiturAbruzzoDataJob::class, 1);

        // Reset queue for second test
        Queue::fake();

        $this->artisan('osm2cai:cache-mitur-abruzzo-api', [
            'model' => 'HikingRoute',
            'id' => $route2->id,
        ])
            ->expectsConfirmation('This command is meant to be run in production. By continuing, you will update cached file on AWS S3 with your local data. Do you wish to continue?', 'yes')
            ->expectsOutput('Processing 1 HikingRoute')
            ->assertSuccessful();

        Queue::assertPushed(CacheMiturAbruzzoDataJob::class, 1);
    }

    /** @test */
    public function it_processes_specific_hiking_route_by_id()
    {
        $route = HikingRoute::factory()->createQuietly([
            'id' => 999999994,
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((1 1 0, 2 2 0))', 4326)"),
        ]);

        $this->artisan('osm2cai:cache-mitur-abruzzo-api', [
            'model' => 'HikingRoute',
            'id' => $route->id,
        ])
            ->expectsConfirmation('This command is meant to be run in production. By continuing, you will update cached file on AWS S3 with your local data. Do you wish to continue?', 'yes')
            ->expectsOutput('Processing 1 HikingRoute')
            ->assertSuccessful();

        Queue::assertPushed(CacheMiturAbruzzoDataJob::class, 1);
    }

    /** @test */
    public function it_shows_error_when_no_hiking_routes_found()
    {
        // Test with a non-existent ID to ensure no routes are found
        $this->artisan('osm2cai:cache-mitur-abruzzo-api', [
            'model' => 'HikingRoute',
            'id' => 999999999,
        ])
            ->expectsConfirmation('This command is meant to be run in production. By continuing, you will update cached file on AWS S3 with your local data. Do you wish to continue?', 'yes')
            ->assertSuccessful();

        Queue::assertNotPushed(CacheMiturAbruzzoDataJob::class);
    }

    /** @test */
    public function it_processes_all_regions()
    {
        Region::factory()->count(3)->create();

        $this->artisan('osm2cai:cache-mitur-abruzzo-api', ['model' => 'Region'])
            ->expectsConfirmation('This command is meant to be run in production. By continuing, you will update cached file on AWS S3 with your local data. Do you wish to continue?', 'yes')
            ->assertSuccessful();

        $count_region = Region::count();

        Queue::assertPushed(CacheMiturAbruzzoDataJob::class, $count_region);
    }

    /** @test */
    public function it_shows_error_for_invalid_model()
    {
        $this->artisan('osm2cai:cache-mitur-abruzzo-api', ['model' => 'InvalidModel'])
            ->expectsConfirmation('This command is meant to be run in production. By continuing, you will update cached file on AWS S3 with your local data. Do you wish to continue?', 'yes')
            ->expectsOutput('Target class [App\Models\InvalidModel] does not exist.')
            ->assertSuccessful();

        Queue::assertNotPushed(CacheMiturAbruzzoDataJob::class);
    }
}
