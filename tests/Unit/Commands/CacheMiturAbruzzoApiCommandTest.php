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
        // clean up the database
        HikingRoute::truncate();
        Region::truncate();
    }

    /** @test */
    public function it_processes_hiking_routes_with_status_4()
    {
        // Create some routes with different statuses
        HikingRoute::factory()->createQuietly([
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((1 1, 2 2))', 4326)"),
        ]);
        HikingRoute::factory()->createQuietly([
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((3 3, 4 4))', 4326)"),
        ]);
        HikingRoute::factory()->createQuietly([
            'osm2cai_status' => 3,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((5 5, 6 6))', 4326)"),
        ]); // should not be processed

        $this->artisan('osm2cai:cache-mitur-abruzzo-api', ['model' => 'HikingRoute'])
            ->expectsConfirmation('This command is meant to be run in production. By continuing, you will update cached file on AWS S3 with your local data. Do you wish to continue?', 'yes')
            ->expectsOutput('Processing 2 HikingRoute')
            ->assertSuccessful();

        Queue::assertPushed(CacheMiturAbruzzoDataJob::class, 2);
    }

    /** @test */
    public function it_processes_specific_hiking_route_by_id()
    {
        $route = HikingRoute::factory()->createQuietly([
            'osm2cai_status' => 4,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((1 1, 2 2))', 4326)"),
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
        $this->artisan('osm2cai:cache-mitur-abruzzo-api', ['model' => 'HikingRoute'])
            ->expectsConfirmation('This command is meant to be run in production. By continuing, you will update cached file on AWS S3 with your local data. Do you wish to continue?', 'yes')
            ->expectsOutput('No hiking routes found with osm2cai_status 4')
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
