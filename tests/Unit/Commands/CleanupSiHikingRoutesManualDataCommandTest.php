<?php

namespace Tests\Unit\Commands;

use App\Models\HikingRoute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupSiHikingRoutesManualDataCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function createRoute(array $properties, ?string $osmid = null): HikingRoute
    {
        return HikingRoute::factory()->createQuietly([
            'app_id' => 2,
            'osmid' => $osmid,
            'properties' => $properties,
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((1 1 0, 2 2 0))', 4326)"),
            'geometry_raw_data' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((1 1 0, 2 2 0))', 4326)"),
        ]);
    }

    /** @test */
    public function it_restores_top_level_fields_from_dem_data(): void
    {
        $route = $this->createRoute([
            'ascent' => 9999,
            'descent' => 8888,
            'dem_data' => ['ascent' => 1711, 'descent' => 965],
        ]);

        $this->artisan('osm2cai:cleanup-si-hiking-routes-manual-data')->assertSuccessful();

        $route->refresh();
        $this->assertEquals(1711, $route->properties['ascent']);
        $this->assertEquals(965, $route->properties['descent']);
        $this->assertArrayNotHasKey('manual_data', $route->properties);
    }

    /** @test */
    public function it_prefers_osm_data_over_dem_data_when_osmid_is_set(): void
    {
        $route = $this->createRoute([
            'ascent' => 9999,
            'dem_data' => ['ascent' => 1711],
            'osm_data' => ['ascent' => 2000],
        ], osmid: 'R123456');

        $this->artisan('osm2cai:cleanup-si-hiking-routes-manual-data')->assertSuccessful();

        $route->refresh();
        $this->assertEquals(2000, $route->properties['ascent']);
    }

    /** @test */
    public function it_ignores_osm_data_when_osmid_is_null(): void
    {
        $route = $this->createRoute([
            'ascent' => 9999,
            'dem_data' => ['ascent' => 1711],
            'osm_data' => ['ascent' => 2000],
        ], osmid: null);

        $this->artisan('osm2cai:cleanup-si-hiking-routes-manual-data')->assertSuccessful();

        $route->refresh();
        $this->assertEquals(1711, $route->properties['ascent']);
    }

    /** @test */
    public function it_sets_field_to_null_when_no_source_available(): void
    {
        $route = $this->createRoute([
            'ascent' => 9999,
            'descent' => 8888,
        ]);

        $this->artisan('osm2cai:cleanup-si-hiking-routes-manual-data')->assertSuccessful();

        $route->refresh();
        $this->assertNull($route->properties['ascent']);
        $this->assertNull($route->properties['descent']);
    }

    /** @test */
    public function it_removes_manual_data_when_present(): void
    {
        $route = $this->createRoute([
            'ascent' => 3181,
            'dem_data' => ['ascent' => 1711],
            'manual_data' => ['ascent' => 3181],
        ]);

        $this->artisan('osm2cai:cleanup-si-hiking-routes-manual-data')->assertSuccessful();

        $route->refresh();
        $this->assertArrayNotHasKey('manual_data', $route->properties);
        $this->assertEquals(1711, $route->properties['ascent']);
    }

    /** @test */
    public function dry_run_does_not_write_to_database(): void
    {
        $route = $this->createRoute([
            'ascent' => 9999,
            'dem_data' => ['ascent' => 1711],
            'manual_data' => ['ascent' => 9999],
        ]);

        $this->artisan('osm2cai:cleanup-si-hiking-routes-manual-data', ['--dry-run' => true])->assertSuccessful();

        $route->refresh();
        $this->assertEquals(9999, $route->properties['ascent']);
        $this->assertArrayHasKey('manual_data', $route->properties);
    }

    /** @test */
    public function it_skips_records_with_non_app_id_2(): void
    {
        $route = HikingRoute::factory()->createQuietly([
            'app_id' => 1,
            'properties' => [
                'ascent' => 9999,
                'dem_data' => ['ascent' => 1711],
                'manual_data' => ['ascent' => 9999],
            ],
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((1 1 0, 2 2 0))', 4326)"),
            'geometry_raw_data' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((1 1 0, 2 2 0))', 4326)"),
        ]);

        $this->artisan('osm2cai:cleanup-si-hiking-routes-manual-data')->assertSuccessful();

        $route->refresh();
        $this->assertEquals(9999, $route->properties['ascent']);
        $this->assertArrayHasKey('manual_data', $route->properties);
    }

    /** @test */
    public function it_is_idempotent(): void
    {
        $route = $this->createRoute([
            'ascent' => 9999,
            'dem_data' => ['ascent' => 1711],
            'manual_data' => ['ascent' => 9999],
        ]);

        $this->artisan('osm2cai:cleanup-si-hiking-routes-manual-data')->assertSuccessful();
        $this->artisan('osm2cai:cleanup-si-hiking-routes-manual-data')->assertSuccessful();

        $route->refresh();
        $this->assertEquals(1711, $route->properties['ascent']);
        $this->assertArrayNotHasKey('manual_data', $route->properties);
    }
}
