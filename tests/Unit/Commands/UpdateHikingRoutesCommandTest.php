<?php

namespace Tests\Unit\Commands;

use App\Jobs\SyncClubHikingRouteRelationJob;
use App\Models\HikingRoute;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UpdateHikingRoutesCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * Crea un HikingRoute di test
     */
    private function createHikingRoute(string $osmfeaturesId, ?string $sourceRef = null, int $osmId = 12345, int $status = 2): HikingRoute
    {
        $osmfeaturesData = [
            'properties' => [
                'osm_id' => $osmId,
            ],
        ];

        if ($sourceRef !== null) {
            $osmfeaturesData['properties']['source_ref'] = $sourceRef;
        }

        return HikingRoute::factory()->createQuietly([
            'osmfeatures_id' => $osmfeaturesId,
            'osm2cai_status' => $status,
            'osmfeatures_data' => $osmfeaturesData,
            'osmfeatures_updated_at' => Carbon::now()->subDay(),
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((1 1, 2 2))', 4326)"),
        ]);
    }

    /**
     * Crea la risposta della lista API
     */
    private function createListResponse(string $osmfeaturesId): array
    {
        return [
            'data' => [
                [
                    'id' => $osmfeaturesId,
                    'updated_at' => Carbon::now()->format('Y-m-d\TH:i:sP'),
                ],
            ],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 100,
            'total' => 1,
        ];
    }

    /**
     * Crea la risposta del dettaglio API
     */
    private function createDetailResponse(string $sourceRef, int $osmId = 12345, string $ref = 'TEST001', string $name = 'Test Route'): array
    {
        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'MultiLineString',
                'coordinates' => [[[12.5251604, 42.2366663], [12.5251554, 42.2366913]]],
            ],
            'properties' => [
                'osm_type' => 'R',
                'osm_id' => $osmId,
                'source_ref' => $sourceRef,
                'osm2cai_status' => 2,
                'ref' => $ref,
                'name' => $name,
            ],
        ];
    }

    /**
     * Mocka le chiamate HTTP all'API
     */
    private function mockHttpResponses(string $osmfeaturesId, array $listResponse, array $detailResponse): void
    {
        $endpoint = HikingRoute::getOsmfeaturesEndpoint();

        Http::fake([
            $endpoint.'list*' => Http::response($listResponse, 200),
            $endpoint.$osmfeaturesId => Http::response($detailResponse, 200),
        ]);
    }

    /**
     * Verifica che il job sia stato lanciato con i parametri corretti
     */
    private function assertJobPushedForHikingRoute(HikingRoute $hikingRoute): void
    {
        Queue::assertPushed(SyncClubHikingRouteRelationJob::class, function ($job) use ($hikingRoute) {
            $reflection = new \ReflectionClass($job);
            $modelTypeProp = $reflection->getProperty('modelType');
            $modelTypeProp->setAccessible(true);
            $modelIdProp = $reflection->getProperty('modelId');
            $modelIdProp->setAccessible(true);

            $modelType = $modelTypeProp->getValue($job);
            $modelId = $modelIdProp->getValue($job);

            return $modelType === 'HikingRoute' && $modelId === $hikingRoute->id;
        });
    }

    /** @test */
    public function it_dispatches_job_when_source_ref_changes()
    {
        $osmfeaturesId = 'R12345678';
        $hikingRoute = $this->createHikingRoute($osmfeaturesId, 'CAI001');
        $listResponse = $this->createListResponse($osmfeaturesId);
        $detailResponse = $this->createDetailResponse('CAI001_CHANGED', 12345, 'TEST001', 'Test Route');

        $this->mockHttpResponses($osmfeaturesId, $listResponse, $detailResponse);

        $this->artisan('osm2cai:update-hiking-routes')
            ->assertSuccessful();

        $this->assertJobPushedForHikingRoute($hikingRoute);

        $hikingRoute->refresh();
        $this->assertEquals('CAI001_CHANGED', $hikingRoute->osmfeatures_data['properties']['source_ref']);
    }

    /** @test */
    public function it_does_not_dispatch_job_when_source_ref_does_not_change()
    {
        $osmfeaturesId = 'R12345679';
        $this->createHikingRoute($osmfeaturesId, 'CAI002', 12346);
        $listResponse = $this->createListResponse($osmfeaturesId);
        $detailResponse = $this->createDetailResponse('CAI002', 12346, 'TEST002', 'Test Route 2');

        $this->mockHttpResponses($osmfeaturesId, $listResponse, $detailResponse);

        $this->artisan('osm2cai:update-hiking-routes')
            ->assertSuccessful();

        Queue::assertNotPushed(SyncClubHikingRouteRelationJob::class);
    }

    /** @test */
    public function it_dispatches_job_when_source_ref_changes_from_null_to_value()
    {
        $osmfeaturesId = 'R12345680';
        $hikingRoute = $this->createHikingRoute($osmfeaturesId, null, 12347);
        $listResponse = $this->createListResponse($osmfeaturesId);
        $detailResponse = $this->createDetailResponse('CAI003', 12347, 'TEST003', 'Test Route 3');

        $this->mockHttpResponses($osmfeaturesId, $listResponse, $detailResponse);

        $this->artisan('osm2cai:update-hiking-routes')
            ->assertSuccessful();

        $this->assertJobPushedForHikingRoute($hikingRoute);
    }

    /** @test */
    public function it_uses_update_quietly_to_avoid_events()
    {
        $osmfeaturesId = 'R12345681';
        $hikingRoute = $this->createHikingRoute($osmfeaturesId, 'CAI004', 12348);
        $listResponse = $this->createListResponse($osmfeaturesId);
        $detailResponse = $this->createDetailResponse('CAI004_CHANGED', 12348, 'TEST004', 'Test Route 4');

        $this->mockHttpResponses($osmfeaturesId, $listResponse, $detailResponse);

        $this->artisan('osm2cai:update-hiking-routes')
            ->assertSuccessful();

        $hikingRoute->refresh();
        $this->assertEquals('CAI004_CHANGED', $hikingRoute->osmfeatures_data['properties']['source_ref']);
    }
}
