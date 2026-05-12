<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\ForceUpdateOsmFeaturesFromTo;
use App\Models\HikingRoute;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ForceUpdateOsmFeaturesFromToCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Disabilita Scout completamente per evitare connessioni a Elasticsearch
        config(['scout.driver' => null]);

        Queue::fake();

        // Registra esplicitamente il comando nel kernel dei test (senza toccare App\Console\Kernel)
        /** @var \Illuminate\Foundation\Console\Kernel $kernel */
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->addCommands([ForceUpdateOsmFeaturesFromTo::class]);

        // Crea le colonne osmfeatures se non esistono (ambiente test può avere schema non allineato)
        if (! Schema::hasColumn('hiking_routes', 'osmfeatures_id')) {
            DB::statement('ALTER TABLE hiking_routes ADD COLUMN osmfeatures_id varchar(255)');
        }
        if (! Schema::hasColumn('hiking_routes', 'osmfeatures_data')) {
            DB::statement('ALTER TABLE hiking_routes ADD COLUMN osmfeatures_data jsonb');
        }
        if (! Schema::hasColumn('hiking_routes', 'osmfeatures_updated_at')) {
            DB::statement('ALTER TABLE hiking_routes ADD COLUMN osmfeatures_updated_at timestamp');
        }
        if (! Schema::hasColumn('hiking_routes', 'properties')) {
            DB::statement('ALTER TABLE hiking_routes ADD COLUMN properties jsonb');
        }
    }

    private function createHikingRoute(
        string $osmfeaturesId,
        array $properties,
        array $osmfeaturesData,
        string $name = 'OLD NAME'
    ): HikingRoute {
        return HikingRoute::factory()->createQuietly([
            'osmfeatures_id' => $osmfeaturesId,
            'osm2cai_status' => 4,
            'name' => $name,
            'properties' => $properties,
            'osmfeatures_data' => $osmfeaturesData,
            'osmfeatures_updated_at' => Carbon::now()->subDay(),
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((1 1 0, 2 2 0))', 4326)"),
            'geometry_raw_data' => DB::raw("ST_GeomFromText('MULTILINESTRINGZ((1 1 0, 2 2 0))', 4326)"),
            'is_geometry_correct' => true,
        ]);
    }

    private function fakeSingleFeature(string $osmfeaturesId, ?string $from, ?string $to): void
    {
        $endpoint = HikingRoute::getOsmfeaturesEndpoint();

        Http::fake([
            $endpoint.$osmfeaturesId => Http::response([
                'type' => 'Feature',
                'geometry' => null,
                'properties' => [
                    'from' => $from,
                    'to' => $to,
                ],
            ], 200),
        ]);
    }

    /** @test */
    public function it_does_not_persist_changes_in_dry_run(): void
    {
        $osmfeaturesId = 'R12345678';

        $route = $this->createHikingRoute(
            $osmfeaturesId,
            ['from' => '', 'to' => ''],
            ['properties' => ['from' => '', 'to' => '']]
        );

        $this->fakeSingleFeature($osmfeaturesId, 'A', 'B');

        $this->artisan('osm2cai:force-update-osmfeatures-from-to', [
            '--dry-run' => true,
            '--id' => $route->id,
            '--delay' => 0,
        ])->assertSuccessful();

        $route->refresh();
        $this->assertSame('', $route->properties['from'] ?? '');
        $this->assertSame('', $route->properties['to'] ?? '');
        $this->assertSame('', $route->osmfeatures_data['properties']['from'] ?? '');
        $this->assertSame('', $route->osmfeatures_data['properties']['to'] ?? '');

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_updates_properties_and_osmfeatures_data_quietly_and_does_not_update_name_by_default(): void
    {
        $osmfeaturesId = 'R12345679';

        $route = $this->createHikingRoute(
            $osmfeaturesId,
            ['from' => '', 'to' => ''],
            ['properties' => ['ref' => 'REF001', 'from' => '', 'to' => '']],
            'OLD NAME'
        );

        $this->fakeSingleFeature($osmfeaturesId, 'From API', 'To API');

        $this->artisan('osm2cai:force-update-osmfeatures-from-to', [
            '--id' => $route->id,
            '--delay' => 0,
        ])->assertSuccessful();

        $route->refresh();

        $this->assertSame('From API', $route->properties['from']);
        $this->assertSame('To API', $route->properties['to']);
        $this->assertSame('From API', $route->osmfeatures_data['properties']['from']);
        $this->assertSame('To API', $route->osmfeatures_data['properties']['to']);
        $this->assertSame('OLD NAME', $route->name);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_does_not_overwrite_non_empty_local_values_with_empty_api_values(): void
    {
        $osmfeaturesId = 'R12345680';

        $route = $this->createHikingRoute(
            $osmfeaturesId,
            ['from' => 'LOCAL', 'to' => ''],
            ['properties' => ['from' => '', 'to' => '']]
        );

        // API returns empty from, valid to
        $this->fakeSingleFeature($osmfeaturesId, '', 'DEST');

        $this->artisan('osm2cai:force-update-osmfeatures-from-to', [
            '--id' => $route->id,
            '--delay' => 0,
        ])->assertSuccessful();

        $route->refresh();

        // 'from' local already set, should stay
        $this->assertSame('LOCAL', $route->properties['from']);
        // 'to' should be filled
        $this->assertSame('DEST', $route->properties['to']);

        // hybrid: osmfeatures_data should get 'to' (and 'from' stays empty because api empty)
        $this->assertSame('', $route->osmfeatures_data['properties']['from'] ?? '');
        $this->assertSame('DEST', $route->osmfeatures_data['properties']['to']);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_updates_properties_from_osmfeatures_data_without_calling_api(): void
    {
        $osmfeaturesId = 'R12345681';

        $route = $this->createHikingRoute(
            $osmfeaturesId,
            ['from' => '', 'to' => ''],
            ['properties' => ['from' => 'FROM OSF', 'to' => 'TO OSF']]
        );

        Http::fake();

        $this->artisan('osm2cai:force-update-osmfeatures-from-to', [
            '--id' => $route->id,
            '--delay' => 0,
        ])->assertSuccessful();

        $route->refresh();
        $this->assertSame('FROM OSF', $route->properties['from']);
        $this->assertSame('TO OSF', $route->properties['to']);

        Http::assertNothingSent();
    }

    /** @test */
    public function it_updates_name_only_when_option_is_enabled(): void
    {
        $osmfeaturesId = 'R12345682';

        $route = $this->createHikingRoute(
            $osmfeaturesId,
            ['from' => '', 'to' => ''],
            ['properties' => ['ref' => 'REF001', 'from' => '', 'to' => '']],
            'OLD NAME'
        );

        $this->fakeSingleFeature($osmfeaturesId, 'From API', 'To API');

        $this->artisan('osm2cai:force-update-osmfeatures-from-to', [
            '--id' => $route->id,
            '--delay' => 0,
            '--update-name' => true,
        ])->assertSuccessful();

        $route->refresh();
        $this->assertSame('REF001 - From API - To API', $route->name);
    }
}

