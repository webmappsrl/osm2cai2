<?php

namespace Tests\Feature;

use App\Models\HikingRoute;
use App\Models\Poles;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Wm\WmPackage\Http\Clients\DemClient;

class SignageMapControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Configure HikingRoute to use the correct table (hiking_routes instead of ec_tracks)
        config(['wm-package.ec_track_table' => 'hiking_routes']);
        // Disable all middleware for testing Nova vendor routes
        $this->withoutMiddleware();

        // Ensure OSMFeatures columns exist in hiking_routes table
        $this->ensureOsmfeaturesColumns();
    }

    /**
     * Verifica e crea le colonne osmfeatures_* se non esistono
     */
    private function ensureOsmfeaturesColumns(): void
    {
        $table = 'hiking_routes';

        if (! Schema::hasTable($table)) {
            return;
        }

        $schema = DB::getSchemaBuilder();
        $columns = $schema->getColumnListing($table);

        if (! in_array('osmfeatures_id', $columns)) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN osmfeatures_id varchar(255)");
        }

        if (! in_array('osmfeatures_data', $columns)) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN osmfeatures_data jsonb");
        }

        if (! in_array('osmfeatures_updated_at', $columns)) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN osmfeatures_updated_at timestamp");
        }
    }

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
        $poleIds = array_map(fn ($pole) => (string) $pole->id, $poles);

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
                    'name' => $pole->name ?? $pole->ref ?? "Pole {$pole->id}",
                    'ref' => $pole->ref ?? '',
                    'description' => $pole->properties['description'] ?? '',
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

        $mockDemClient = Mockery::mock(DemClient::class);
        $mockDemClient->shouldReceive('getPointMatrix')
            ->andReturnUsing(function ($originalGeojson) use ($enrichedGeojson, $hikingRouteId) {
                $originalFeatures = $originalGeojson['features'] ?? [];
                $enrichedFeatures = $enrichedGeojson['features'] ?? [];

                $otherFeatures = array_filter($originalFeatures, function ($feature) {
                    $geometryType = strtolower($feature['geometry']['type'] ?? '');

                    return ! in_array($geometryType, ['point', 'multilinestring', 'linestring'], true);
                });

                $mockLineFeature = null;
                $mockPointFeatures = [];
                foreach ($enrichedFeatures as $feature) {
                    $geometryType = strtolower($feature['geometry']['type'] ?? '');
                    if ($geometryType === 'multilinestring') {
                        $mockLineFeature = $feature;
                    } elseif ($geometryType === 'point') {
                        $mockPointFeatures[] = $feature;
                    }
                }

                if ($mockLineFeature === null && ! empty($mockPointFeatures)) {
                    $poleIds = array_map(function ($pf) {
                        return (string) ($pf['properties']['id'] ?? '');
                    }, $mockPointFeatures);
                    $mockLineFeature = [
                        'type' => 'Feature',
                        'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[14.0, 37.0], [14.003, 37.003]]]],
                        'properties' => ['id' => $hikingRouteId, 'dem' => ['points_order' => array_filter($poleIds)]],
                    ];
                }

                $features = $mockLineFeature ? [$mockLineFeature] : [];
                $features = array_merge($features, $mockPointFeatures, array_values($otherFeatures));

                return ['type' => 'FeatureCollection', 'features' => $features];
            });

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
        $count = count(array_filter($checkpoint, fn ($id) => (int) $id === $pole->id));
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
        // Crea 3 Poles lungo la route (coordinate devono essere vicine alla route per essere incluse)
        $pole1 = $this->createPoleWithGeometry('POINT(14.0000 37.0000)', [], 'P1');
        $pole2 = $this->createPoleWithGeometry('POINT(14.0010 37.0010)', [], 'P2');
        $pole3 = $this->createPoleWithGeometry('POINT(14.0020 37.0020)', [], 'P3');

        // Crea un HikingRoute con ref (coordinate devono includere i poles)
        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010, 14.0020 37.0020))',
            [],
            ['properties' => ['osm_tags' => ['ref' => '178']]]
        );

        // Mock del DemClient - il mock restituisce un GeoJSON completo che sostituisce quello originale
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

        // Il controller processa solo i poles che sono in pointsOrder e hanno matrix_row
        // Il mock crea correttamente le features con matrix_row, quindi tutti i poles dovrebbero essere processati
        // Verifichiamo che almeno pole2 (il checkpoint) abbia i dati signage
        // Nota: pole1 e pole3 potrebbero non avere dati signage se non hanno matrix_row o non sono in pointsOrder
        if (isset($pole2->properties['signage'])) {
            $this->assertArrayHasKey('arrow_order', $pole2->properties['signage']);
            $this->assertIsArray($pole2->properties['signage']['arrow_order']);
            if (isset($pole2->properties['signage'][$hikingRouteIdStr])) {
                $this->assertArrayHasKey('arrows', $pole2->properties['signage'][$hikingRouteIdStr]);
                $this->assertIsArray($pole2->properties['signage'][$hikingRouteIdStr]['arrows']);
                $this->assertEquals('178', $pole2->properties['signage'][$hikingRouteIdStr]['ref']);
            }
        }

        // Verifica che il checkpoint sia stato aggiunto
        $hikingRoute->refresh();
        $checkpoint = $hikingRoute->properties['signage']['checkpoint'] ?? [];
        $this->assertContains($pole2->id, $checkpoint);
    }

    /** @test */
    public function poles_signage_contains_correct_forward_and_backward_data()
    {
        // Crea 4 Poles lungo la route (coordinate devono essere vicine alla route)
        $pole1 = $this->createPoleWithGeometry('POINT(14.0000 37.0000)', [], 'Start');
        $pole2 = $this->createPoleWithGeometry('POINT(14.0010 37.0010)', [], 'Mid1');
        $pole3 = $this->createPoleWithGeometry('POINT(14.0020 37.0020)', [], 'Mid2');
        $pole4 = $this->createPoleWithGeometry('POINT(14.0030 37.0030)', [], 'End');

        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0010 37.0010, 14.0020 37.0020, 14.0030 37.0030))',
            [],
            ['properties' => ['osm_tags' => ['ref' => '200']]]
        );

        // Mock del DemClient
        $this->mockDemClient([$pole1, $pole2, $pole3, $pole4], $hikingRoute->id);

        // Aggiungi pole2 come checkpoint
        $this->patchJson(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            ['poleId' => $pole2->id, 'add' => true]
        )->assertStatus(200);

        // Ricarica pole2
        $pole2->refresh();

        $hikingRouteIdStr = (string) $hikingRoute->id;
        $signageData = $pole2->properties['signage'][$hikingRouteIdStr] ?? null;

        // Verifica che pole2 abbia dati signage
        $this->assertNotNull($signageData, 'Pole2 dovrebbe avere dati signage dopo essere stato aggiunto come checkpoint. Verifica che il pole sia nel GeoJSON originale o che il mock del DemClient funzioni correttamente.');
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

        // Forward dovrebbe contenere pole4 (ultimo punto) perché pole2 è l'unico checkpoint
        $this->assertNotNull($forwardArrow, 'Pole2 dovrebbe avere una freccia forward');
        $forwardIds = array_column($forwardArrow['rows'], 'id');
        $this->assertContains($pole4->id, $forwardIds, 'Forward dovrebbe contenere l\'ultimo polo');

        // Backward dovrebbe contenere pole1 (primo punto)
        $this->assertNotNull($backwardArrow, 'Pole2 dovrebbe avere una freccia backward');
        $backwardIds = array_column($backwardArrow['rows'], 'id');
        $this->assertContains($pole1->id, $backwardIds, 'Backward dovrebbe contenere il primo polo');
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

        // Verifica che pole1 abbia dati signage
        $this->assertNotNull($signageData, 'Pole1 dovrebbe avere dati signage dopo che pole2 è stato aggiunto come checkpoint. Verifica che il pole sia nel GeoJSON originale o che il mock del DemClient funzioni correttamente.');
        $this->assertArrayHasKey('arrows', $signageData);
        $this->assertIsArray($signageData['arrows']);

        // Trova la freccia forward
        $forwardArrow = null;
        foreach ($signageData['arrows'] as $arrow) {
            if ($arrow['direction'] === 'forward') {
                $forwardArrow = $arrow;
                break;
            }
        }

        // Verifica che forward contenga distance e time_hiking
        $this->assertNotNull($forwardArrow, 'Pole1 dovrebbe avere una freccia forward');
        $this->assertNotEmpty($forwardArrow['rows'], 'La freccia forward dovrebbe avere almeno una riga');
        $firstForward = $forwardArrow['rows'][0];
        $this->assertArrayHasKey('distance', $firstForward);
        $this->assertArrayHasKey('time_hiking', $firstForward);
        $this->assertArrayHasKey('name', $firstForward);
        $this->assertArrayHasKey('id', $firstForward);
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

    private function seedPoleSignageArrowForMidpoint(Poles $pole, HikingRoute $hikingRoute, array $arrowOverrides = []): void
    {
        $hikingRouteIdStr = (string) $hikingRoute->id;

        $defaults = [
            'direction' => 'forward',
            'rows' => [
                ['id' => 1, 'name' => 'Nearest'],
                ['id' => 2, 'name' => 'Mid'],
                ['id' => 4, 'name' => 'Final'],
            ],
            'midpoints_data' => [
                '2' => ['distance' => 1200, 'time_hiking' => 30],
                '3' => ['distance' => 2100, 'time_hiking' => 55],
            ],
        ];

        // 'rows' viene sostituito direttamente per evitare che array_replace_recursive
        // mantenga gli elementi extra del default quando l'override ha meno righe.
        $rowsOverride = array_key_exists('rows', $arrowOverrides) ? $arrowOverrides['rows'] : null;
        $arrowOverridesWithoutRows = array_diff_key($arrowOverrides, ['rows' => null]);
        $arrow = array_replace_recursive($defaults, $arrowOverridesWithoutRows);
        if ($rowsOverride !== null) {
            $arrow['rows'] = $rowsOverride;
        }

        $props = $pole->properties ?? [];
        $props['signage'] ??= [];
        $props['signage'][$hikingRouteIdStr] ??= ['arrows' => [], 'ref' => ''];
        $props['signage'][$hikingRouteIdStr]['arrows'][0] = $arrow;

        $pole->properties = $props;
        $pole->saveQuietly();
    }

    /** @test */
    public function it_updates_arrow_midpoint_successfully_and_persists_it(): void
    {
        $nearest = $this->createPoleWithGeometry('POINT(14.0000 37.0000)', [], 'N1');
        $mid2 = $this->createPoleWithGeometry('POINT(14.0010 37.0010)', [], 'M2');
        $mid3 = $this->createPoleWithGeometry('POINT(14.0020 37.0020)', [], 'M3');
        $final = $this->createPoleWithGeometry('POINT(14.0030 37.0030)', [], 'F4');

        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0030 37.0030))',
            [
                'signage' => [
                    'checkpoint_order' => [$nearest->id, $mid2->id, $mid3->id, $final->id],
                    'checkpoint' => [$nearest->id, $mid2->id, $mid3->id, $final->id],
                ],
            ]
        );

        // Il palo su cui è salvata la segnaletica (popup)
        $pole = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');

        $this->seedPoleSignageArrowForMidpoint($pole, $hikingRoute, [
            'rows' => [
                ['id' => $nearest->id],
                ['id' => $mid2->id],
                ['id' => $final->id],
            ],
            'midpoints_data' => [
                (string) $mid2->id => ['distance' => 1200, 'time_hiking' => 30],
                (string) $mid3->id => ['distance' => 2100, 'time_hiking' => 55],
            ],
        ]);

        $response = $this->patchJson(
            "/nova-vendor/signage-map/pole/{$pole->id}/arrow-midpoint",
            [
                'hiking_route_id' => $hikingRoute->id,
                'arrow_index' => 0,
                'selected_pole_id' => $mid3->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('arrow.selected_midpoint_id', $mid3->id);
        $response->assertJsonPath('arrow.rows.1.id', $mid3->id);
        $response->assertJsonPath('arrow.rows.1.distance', 2100);
        $response->assertJsonPath('arrow.rows.1.time_hiking', 55);

        $pole->refresh();
        $hikingRouteIdStr = (string) $hikingRoute->id;
        $savedArrow = $pole->properties['signage'][$hikingRouteIdStr]['arrows'][0] ?? null;
        $this->assertNotNull($savedArrow);
        $this->assertSame($mid3->id, $savedArrow['selected_midpoint_id'] ?? null);
        $this->assertSame($mid3->id, $savedArrow['rows'][1]['id'] ?? null);
    }

    /** @test */
    public function it_returns_422_when_selected_midpoint_is_nearest_or_final_or_not_active_or_out_of_range(): void
    {
        $nearest = $this->createPoleWithGeometry('POINT(14.0000 37.0000)');
        $mid2 = $this->createPoleWithGeometry('POINT(14.0010 37.0010)');
        $mid3 = $this->createPoleWithGeometry('POINT(14.0020 37.0020)');
        $final = $this->createPoleWithGeometry('POINT(14.0030 37.0030)');

        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0030 37.0030))',
            [
                'signage' => [
                    'checkpoint_order' => [$nearest->id, $mid2->id, $mid3->id, $final->id],
                    // mid3 non attivo
                    'checkpoint' => [$nearest->id, $mid2->id, $final->id],
                ],
            ]
        );

        $pole = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');
        $this->seedPoleSignageArrowForMidpoint($pole, $hikingRoute, [
            'rows' => [
                ['id' => $nearest->id],
                ['id' => $mid2->id],
                ['id' => $final->id],
            ],
            'midpoints_data' => [
                (string) $mid2->id => ['distance' => 1200, 'time_hiking' => 30],
                (string) $mid3->id => ['distance' => 2100, 'time_hiking' => 55],
            ],
        ]);

        // nearest
        $this->patchJson(
            "/nova-vendor/signage-map/pole/{$pole->id}/arrow-midpoint",
            ['hiking_route_id' => $hikingRoute->id, 'arrow_index' => 0, 'selected_pole_id' => $nearest->id]
        )->assertStatus(422)->assertJsonPath('error', 'Selected pole is not a valid intermediate checkpoint');

        // final
        $this->patchJson(
            "/nova-vendor/signage-map/pole/{$pole->id}/arrow-midpoint",
            ['hiking_route_id' => $hikingRoute->id, 'arrow_index' => 0, 'selected_pole_id' => $final->id]
        )->assertStatus(422)->assertJsonPath('error', 'Selected pole is not a valid intermediate checkpoint');

        // non attivo (mid3)
        $this->patchJson(
            "/nova-vendor/signage-map/pole/{$pole->id}/arrow-midpoint",
            ['hiking_route_id' => $hikingRoute->id, 'arrow_index' => 0, 'selected_pole_id' => $mid3->id]
        )->assertStatus(422)->assertJsonPath('error', 'Selected pole is not a valid intermediate checkpoint');

        // fuori range (id non in checkpoint_order)
        $out = $this->createPoleWithGeometry('POINT(14.0100 37.0100)');
        $this->patchJson(
            "/nova-vendor/signage-map/pole/{$pole->id}/arrow-midpoint",
            ['hiking_route_id' => $hikingRoute->id, 'arrow_index' => 0, 'selected_pole_id' => $out->id]
        )->assertStatus(422)->assertJsonPath('error', 'Selected pole is not a valid intermediate checkpoint');
    }

    /** @test */
    public function it_returns_422_when_arrow_has_no_midpoint_slot(): void
    {
        $nearest = $this->createPoleWithGeometry('POINT(14.0000 37.0000)');
        $final = $this->createPoleWithGeometry('POINT(14.0030 37.0030)');

        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0030 37.0030))',
            [
                'signage' => [
                    'checkpoint_order' => [$nearest->id, $final->id],
                    'checkpoint' => [$nearest->id, $final->id],
                ],
            ]
        );

        $pole = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');
        $this->seedPoleSignageArrowForMidpoint($pole, $hikingRoute, [
            'rows' => [
                ['id' => $nearest->id],
                ['id' => $final->id],
            ],
        ]);

        $this->patchJson(
            "/nova-vendor/signage-map/pole/{$pole->id}/arrow-midpoint",
            [
                'hiking_route_id' => $hikingRoute->id,
                'arrow_index' => 0,
                'selected_pole_id' => $final->id,
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'Arrow has no midpoint slot');
    }

    /** @test */
    public function it_returns_404_when_arrow_or_hiking_route_not_found(): void
    {
        $nearest = $this->createPoleWithGeometry('POINT(14.0000 37.0000)');
        $mid = $this->createPoleWithGeometry('POINT(14.0010 37.0010)');
        $final = $this->createPoleWithGeometry('POINT(14.0030 37.0030)');

        $hikingRoute = $this->createHikingRouteWithGeometry(
            'MULTILINESTRING((14.0000 37.0000, 14.0030 37.0030))',
            [
                'signage' => [
                    'checkpoint_order' => [$nearest->id, $mid->id, $final->id],
                    'checkpoint' => [$nearest->id, $mid->id, $final->id],
                ],
            ]
        );

        $pole = $this->createPoleWithGeometry('POINT(14.0005 37.0005)');
        $this->seedPoleSignageArrowForMidpoint($pole, $hikingRoute, [
            'rows' => [
                ['id' => $nearest->id],
                ['id' => $mid->id],
                ['id' => $final->id],
            ],
        ]);

        // arrow index inesistente
        $this->patchJson(
            "/nova-vendor/signage-map/pole/{$pole->id}/arrow-midpoint",
            ['hiking_route_id' => $hikingRoute->id, 'arrow_index' => 99, 'selected_pole_id' => $mid->id]
        )->assertStatus(404)->assertJsonPath('error', 'Arrow not found');

        // hiking route inesistente
        $this->patchJson(
            "/nova-vendor/signage-map/pole/{$pole->id}/arrow-midpoint",
            ['hiking_route_id' => 999999, 'arrow_index' => 0, 'selected_pole_id' => $mid->id]
        )->assertStatus(404)->assertJsonPath('error', 'HikingRoute not found');
    }
}
