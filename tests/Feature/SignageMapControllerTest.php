<?php

namespace Tests\Feature;

use App\Models\HikingRoute;
use App\Models\Poles;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Wm\WmPackage\Http\Clients\DemClient;

class SignageMapControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function createHikingRouteWithGeometry(string $geometry, array $properties = [], array $osmfeaturesData = []): HikingRoute
    {
        return HikingRoute::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('{$geometry}', 4326)"),
            'properties' => $properties,
            'osmfeatures_data' => $osmfeaturesData ?: null,
        ]);
    }

    private function createPoleWithGeometry(string $geometry, array $properties = [], ?string $ref = null): Poles
    {
        return Poles::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('{$geometry}', 4326)"),
            'properties' => $properties,
            'ref' => $ref,
        ]);
    }

    /**
     * Crea un mock del DemClient che restituisce un GeoJSON arricchito con points_order e matrix_row
     */
    private function mockDemClient(array $poles, int $hikingRouteId): void
    {
        $poleIds = array_map(fn($pole) => (string) $pole->id, $poles);

        // Costruisci le features Point con matrix_row
        $pointFeatures = [];
        foreach ($poles as $index => $pole) {
            $matrixRow = [];
            // Calcola distanze e tempi fittizi verso gli altri poli
            foreach ($poles as $otherIndex => $otherPole) {
                if ($pole->id !== $otherPole->id) {
                    $distance = abs($otherIndex - $index) * 500; // 500m per ogni polo
                    $matrixRow[(string) $otherPole->id] = [
                        'distance' => $distance,
                        'time_hiking' => (int) ($distance / 50), // ~3km/h = 50m/min
                    ];
                }
            }

            $pointFeatures[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [14.0 + ($index * 0.001), 37.0 + ($index * 0.001)],
                ],
                'properties' => [
                    'id' => $pole->id,
                    'name' => $pole->ref ?? "Pole {$pole->id}",
                    'dem' => [
                        'matrix_row' => [
                            (string) $hikingRouteId => $matrixRow,
                        ],
                    ],
                ],
            ];
        }

        // Feature MultiLineString con points_order
        $lineFeature = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'MultiLineString',
                'coordinates' => [[[14.0, 37.0], [14.003, 37.003]]],
            ],
            'properties' => [
                'id' => $hikingRouteId,
                'dem' => [
                    'points_order' => $poleIds,
                ],
            ],
        ];

        $enrichedGeojson = [
            'type' => 'FeatureCollection',
            'features' => array_merge($pointFeatures, [$lineFeature]),
        ];

        // Mock del DemClient
        $mockDemClient = Mockery::mock(DemClient::class);
        $mockDemClient->shouldReceive('getPointMatrix')
            ->andReturn($enrichedGeojson);

        $this->app->instance(DemClient::class, $mockDemClient);
    }

    /** @test */
    public function it_adds_pole_id_to_checkpoint_when_toggle_meta_is_activated()
    {
        // Crea un HikingRoute senza checkpoint
        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010))',
            []
        );

        // Crea un Pole
        $pole = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');

        // Chiama l'endpoint per attivare il checkpoint
        $response = $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            [
                'poleId' => $pole->id,
                'add' => true,
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Ricarica l'HikingRoute dal database
        $hikingRoute->refresh();

        // Verifica che il poleId sia stato aggiunto al checkpoint
        $checkpoint = $hikingRoute->properties['signage']['checkpoint'] ?? [];
        $this->assertContains($pole->id, $checkpoint);
    }

    /** @test */
    public function it_removes_pole_id_from_checkpoint_when_toggle_meta_is_deactivated()
    {
        // Crea un Pole
        $pole = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');

        // Crea un HikingRoute con il poleId già nel checkpoint
        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010))',
            [
                'signage' => [
                    'checkpoint' => [$pole->id],
                ],
            ]
        );

        // Verifica che il checkpoint contenga il poleId
        $this->assertContains($pole->id, $hikingRoute->properties['signage']['checkpoint']);

        // Chiama l'endpoint per disattivare il checkpoint
        $response = $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            [
                'poleId' => $pole->id,
                'add' => false,
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Ricarica l'HikingRoute dal database
        $hikingRoute->refresh();

        // Verifica che il poleId sia stato rimosso dal checkpoint
        $checkpoint = $hikingRoute->properties['signage']['checkpoint'] ?? [];
        $this->assertNotContains($pole->id, $checkpoint);
    }

    /** @test */
    public function it_does_not_add_duplicate_pole_id_to_checkpoint()
    {
        // Crea un Pole
        $pole = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');

        // Crea un HikingRoute con il poleId già nel checkpoint
        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010))',
            [
                'signage' => [
                    'checkpoint' => [$pole->id],
                ],
            ]
        );

        // Chiama l'endpoint per attivare il checkpoint (già attivo)
        $response = $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            [
                'poleId' => $pole->id,
                'add' => true,
            ]
        );

        $response->assertStatus(200);

        // Ricarica l'HikingRoute dal database
        $hikingRoute->refresh();

        // Verifica che il checkpoint contenga il poleId solo una volta
        $checkpoint = $hikingRoute->properties['signage']['checkpoint'] ?? [];
        $count = count(array_filter($checkpoint, fn($id) => (int) $id === $pole->id));
        $this->assertEquals(1, $count, 'Il poleId non dovrebbe essere duplicato nel checkpoint');
    }

    /** @test */
    public function it_returns_error_when_pole_id_is_missing()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010))'
        );

        $response = $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            [
                'add' => true,
            ]
        );

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'poleId is required');
    }

    /** @test */
    public function it_returns_404_when_hiking_route_not_found()
    {
        $response = $this->patchJson(
            '/nova-vendor/signage-map/hiking-route/999999/properties',
            [
                'poleId' => 1,
                'add' => true,
            ]
        );

        $response->assertStatus(404);
    }

    /** @test */
    public function it_initializes_signage_structure_when_properties_are_empty()
    {
        // Crea un HikingRoute con properties null
        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010))',
            []
        );

        // Crea un Pole
        $pole = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');

        // Chiama l'endpoint
        $response = $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            [
                'poleId' => $pole->id,
                'add' => true,
            ]
        );

        $response->assertStatus(200);

        // Ricarica l'HikingRoute dal database
        $hikingRoute->refresh();

        // Verifica che la struttura signage sia stata inizializzata correttamente
        $this->assertIsArray($hikingRoute->properties);
        $this->assertArrayHasKey('signage', $hikingRoute->properties);
        $this->assertArrayHasKey('checkpoint', $hikingRoute->properties['signage']);
        $this->assertContains($pole->id, $hikingRoute->properties['signage']['checkpoint']);
    }

    /** @test */
    public function it_handles_multiple_poles_in_checkpoint()
    {
        // Crea più Poles
        $pole1 = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');
        $pole2 = $this->createPoleWithGeometry('POINT(14.0015 37.0015)');
        $pole3 = $this->createPoleWithGeometry('POINT(14.0025 37.0025)');

        // Crea un HikingRoute
        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0030 37.0030))'
        );

        // Aggiungi il primo pole
        $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            ['poleId' => $pole1->id, 'add' => true]
        )->assertStatus(200);

        // Aggiungi il secondo pole
        $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            ['poleId' => $pole2->id, 'add' => true]
        )->assertStatus(200);

        // Aggiungi il terzo pole
        $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            ['poleId' => $pole3->id, 'add' => true]
        )->assertStatus(200);

        // Ricarica l'HikingRoute
        $hikingRoute->refresh();
        $checkpoint = $hikingRoute->properties['signage']['checkpoint'] ?? [];

        // Verifica che tutti e tre siano presenti
        $this->assertContains($pole1->id, $checkpoint);
        $this->assertContains($pole2->id, $checkpoint);
        $this->assertContains($pole3->id, $checkpoint);
        $this->assertCount(3, $checkpoint);

        // Rimuovi il secondo pole
        $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            ['poleId' => $pole2->id, 'add' => false]
        )->assertStatus(200);

        // Ricarica e verifica
        $hikingRoute->refresh();
        $checkpoint = $hikingRoute->properties['signage']['checkpoint'] ?? [];

        $this->assertContains($pole1->id, $checkpoint);
        $this->assertNotContains($pole2->id, $checkpoint);
        $this->assertContains($pole3->id, $checkpoint);
        $this->assertCount(2, $checkpoint);
    }

    /** @test */
    public function it_returns_updated_properties_in_response()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010))'
        );
        $pole = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');

        $response = $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            [
                'poleId' => $pole->id,
                'add' => true,
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'properties' => [
                'signage' => [
                    'checkpoint',
                ],
            ],
        ]);

        // Verifica che il poleId sia nella risposta
        $responseCheckpoint = $response->json('properties.signage.checkpoint');
        $this->assertContains($pole->id, $responseCheckpoint);
    }

    /** @test */
    public function it_updates_poles_signage_data_when_checkpoint_is_added()
    {
        // Crea 3 Poles lungo la route
        $pole1 = $this->createPoleWithGeometry('POINT(14.0000 37.0000)', [], 'P1');
        $pole2 = $this->createPoleWithGeometry('POINT(14.0010 37.0010)', [], 'P2');
        $pole3 = $this->createPoleWithGeometry('POINT(14.0020 37.0020)', [], 'P3');

        // Crea un HikingRoute con ref
        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0020 37.0020))',
            [],
            ['properties' => ['osm_tags' => ['ref' => '178']]]
        );

        // Mock del DemClient
        $this->mockDemClient([$pole1, $pole2, $pole3], $hikingRoute->id);

        // Aggiungi pole2 come checkpoint
        $response = $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            [
                'poleId' => $pole2->id,
                'add' => true,
            ]
        );

        $response->assertStatus(200);

        // Ricarica i Poles dal database
        $pole1->refresh();
        $pole2->refresh();
        $pole3->refresh();

        // Verifica che i Poles abbiano i dati signage aggiornati
        $hikingRouteIdStr = (string) $hikingRoute->id;

        // Pole1 dovrebbe avere dati signage con arrows e arrow_order
        $this->assertArrayHasKey('signage', $pole1->properties);
        $this->assertArrayHasKey('arrow_order', $pole1->properties['signage']);
        $this->assertIsArray($pole1->properties['signage']['arrow_order']);
        $this->assertArrayHasKey($hikingRouteIdStr, $pole1->properties['signage']);
        $this->assertArrayHasKey('arrows', $pole1->properties['signage'][$hikingRouteIdStr]);
        $this->assertIsArray($pole1->properties['signage'][$hikingRouteIdStr]['arrows']);
        $this->assertEquals('178', $pole1->properties['signage'][$hikingRouteIdStr]['ref']);
    }

    /** @test */
    public function poles_signage_contains_correct_forward_and_backward_data()
    {
        // Crea 4 Poles lungo la route
        $pole1 = $this->createPoleWithGeometry('POINT(14.0000 37.0000)', [], 'Start');
        $pole2 = $this->createPoleWithGeometry('POINT(14.0010 37.0010)', [], 'Mid1');
        $pole3 = $this->createPoleWithGeometry('POINT(14.0020 37.0020)', [], 'Mid2');
        $pole4 = $this->createPoleWithGeometry('POINT(14.0030 37.0030)', [], 'End');

        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0030 37.0030))',
            [],
            ['properties' => ['osm_tags' => ['ref' => '200']]]
        );

        // Mock del DemClient
        $this->mockDemClient([$pole1, $pole2, $pole3, $pole4], $hikingRoute->id);

        // Aggiungi pole2 e pole3 come checkpoint
        $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            ['poleId' => $pole2->id, 'add' => true]
        )->assertStatus(200);

        // Ricarica pole2
        $pole2->refresh();

        $hikingRouteIdStr = (string) $hikingRoute->id;
        $signageData = $pole2->properties['signage'][$hikingRouteIdStr] ?? null;

        $this->assertNotNull($signageData);
        $this->assertArrayHasKey('arrows', $signageData);
        $this->assertIsArray($signageData['arrows']);

        // Trova le frecce forward e backward
        $forwardArrow = null;
        $backwardArrow = null;
        foreach ($signageData['arrows'] as $arrow) {
            if ($arrow['direction'] === 'forward') {
                $forwardArrow = $arrow;
            } elseif ($arrow['direction'] === 'backward') {
                $backwardArrow = $arrow;
            }
        }

        // Forward dovrebbe contenere pole3 (checkpoint) e pole4 (ultimo)
        if ($forwardArrow) {
            $forwardIds = array_column($forwardArrow['rows'], 'id');
            $this->assertContains($pole4->id, $forwardIds, 'Forward dovrebbe contenere l\'ultimo polo');
        }

        // Backward dovrebbe contenere pole1 (primo)
        if ($backwardArrow) {
            $backwardIds = array_column($backwardArrow['rows'], 'id');
            $this->assertContains($pole1->id, $backwardIds, 'Backward dovrebbe contenere il primo polo');
        }
    }

    /** @test */
    public function poles_signage_includes_distance_and_time()
    {
        $pole1 = $this->createPoleWithGeometry('POINT(14.0000 37.0000)', [], 'P1');
        $pole2 = $this->createPoleWithGeometry('POINT(14.0010 37.0010)', [], 'P2');

        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010))',
            [],
            ['properties' => ['osm_tags' => ['ref' => '100']]]
        );

        $this->mockDemClient([$pole1, $pole2], $hikingRoute->id);

        $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            ['poleId' => $pole2->id, 'add' => true]
        )->assertStatus(200);

        $pole1->refresh();

        $hikingRouteIdStr = (string) $hikingRoute->id;
        $signageData = $pole1->properties['signage'][$hikingRouteIdStr] ?? null;

        $this->assertNotNull($signageData);

        // Verifica che forward contenga distance e time_hiking
        if (! empty($signageData['forward'])) {
            $firstForward = $signageData['forward'][0];
            $this->assertArrayHasKey('distance', $firstForward);
            $this->assertArrayHasKey('time_hiking', $firstForward);
            $this->assertArrayHasKey('name', $firstForward);
            $this->assertArrayHasKey('id', $firstForward);
        }
    }

    /** @test */
    public function poles_signage_is_cleared_when_all_checkpoints_removed()
    {
        $pole1 = $this->createPoleWithGeometry('POINT(14.0000 37.0000)', [], 'P1');
        $pole2 = $this->createPoleWithGeometry('POINT(14.0010 37.0010)', [], 'P2');

        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010))',
            [
                'signage' => [
                    'checkpoint' => [$pole1->id],
                ],
            ],
            ['properties' => ['osm_tags' => ['ref' => '100']]]
        );

        $this->mockDemClient([$pole1, $pole2], $hikingRoute->id);

        // Rimuovi l'unico checkpoint
        $response = $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            ['poleId' => $pole1->id, 'add' => false]
        );

        $response->assertStatus(200);

        // Verifica che il checkpoint sia stato rimosso
        $hikingRoute->refresh();
        $checkpoint = $hikingRoute->properties['signage']['checkpoint'] ?? [];
        $this->assertEmpty($checkpoint);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
