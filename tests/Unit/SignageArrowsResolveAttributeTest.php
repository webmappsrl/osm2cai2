<?php

namespace Tests\Unit;

use App\Models\HikingRoute;
use App\Models\Poles;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Osm2cai\SignageArrows\SignageArrows;
use ReflectionClass;
use Tests\TestCase;

class SignageArrowsResolveAttributeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['wm-package.ec_track_table' => 'hiking_routes']);
        $this->withoutMiddleware();
        $this->ensureOsmfeaturesColumns();
    }

    private function ensureOsmfeaturesColumns(): void
    {
        $table = 'hiking_routes';
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = DB::getSchemaBuilder()->getColumnListing($table);
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

    private function createHikingRoute(array $properties = []): HikingRoute
    {
        return HikingRoute::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('MULTILINESTRING((14.0000 37.0000, 14.0100 37.0100))', 4326)"),
            'properties' => $properties,
        ]);
    }

    private function createPole(string $ref, string $name): Poles
    {
        return Poles::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('POINT(14.0000 37.0000)', 4326)"),
            'ref' => $ref,
            'properties' => ['name' => $name],
        ]);
    }

    private function callResolveAttribute(object $resource, string $attribute): mixed
    {
        $field = new SignageArrows('Signage', $attribute);
        $ref = new ReflectionClass($field);
        $method = $ref->getMethod('resolveAttribute');
        $method->setAccessible(true);

        return $method->invoke($field, $resource, $attribute);
    }

    /** @test */
    public function it_computes_available_midpoints_between_nearest_and_final_and_filters_inactive(): void
    {
        $p1 = $this->createPole('P1', 'Pole 1');
        $p2 = $this->createPole('P2', 'Pole 2');
        $p3 = $this->createPole('P3', 'Pole 3');
        $p4 = $this->createPole('P4', 'Pole 4');

        $route = $this->createHikingRoute([
            'signage' => [
                'checkpoint_order' => [$p1->id, $p2->id, $p3->id, $p4->id],
                // p3 non attivo: deve essere filtrato
                'checkpoint' => [$p1->id, $p2->id, $p4->id],
            ],
        ]);

        $resource = (object) [
            'properties' => [
                'signage' => [
                    (string) $route->id => [
                        'arrows' => [
                            [
                                'direction' => 'forward',
                                'rows' => [
                                    ['id' => $p1->id],
                                    ['id' => $p2->id],
                                    ['id' => $p4->id],
                                ],
                                'midpoints_data' => [
                                    (string) $p2->id => ['distance' => 1200, 'time_hiking' => 30],
                                    (string) $p3->id => ['distance' => 2100, 'time_hiking' => 55],
                                ],
                            ],
                        ],
                    ],
                    'arrow_order' => [],
                ],
            ],
        ];

        $resolved = $this->callResolveAttribute($resource, 'properties.signage');

        $arrow = $resolved[(string) $route->id]['arrows'][0] ?? null;
        $this->assertNotNull($arrow);
        $this->assertArrayHasKey('available_midpoints', $arrow);

        $available = $arrow['available_midpoints'];
        $this->assertCount(1, $available, 'Deve includere solo p2 (p3 è inattivo)');
        $this->assertSame($p2->id, $available[0]['id']);
        $this->assertSame('Pole 2', $available[0]['name']);
        $this->assertSame('P2', $available[0]['ref']);
        $this->assertSame(1200, $available[0]['distance']);
        $this->assertSame(30, $available[0]['time_hiking']);
    }

    /** @test */
    public function it_excludes_nearest_and_final_even_if_present_in_checkpoint_order(): void
    {
        $p1 = $this->createPole('P1', 'Pole 1');
        $p2 = $this->createPole('P2', 'Pole 2');
        $p3 = $this->createPole('P3', 'Pole 3');
        $p4 = $this->createPole('P4', 'Pole 4');

        $route = $this->createHikingRoute([
            'signage' => [
                'checkpoint_order' => [$p1->id, $p2->id, $p3->id, $p4->id],
                'checkpoint' => [$p1->id, $p2->id, $p3->id, $p4->id],
            ],
        ]);

        $resource = (object) [
            'properties' => [
                'signage' => [
                    (string) $route->id => [
                        'arrows' => [
                            [
                                'rows' => [
                                    ['id' => $p1->id],
                                    ['id' => $p2->id],
                                    ['id' => $p4->id],
                                ],
                                'midpoints_data' => [
                                    (string) $p1->id => ['distance' => 10],
                                    (string) $p2->id => ['distance' => 20],
                                    (string) $p3->id => ['distance' => 30],
                                    (string) $p4->id => ['distance' => 40],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $resolved = $this->callResolveAttribute($resource, 'properties.signage');
        $available = $resolved[(string) $route->id]['arrows'][0]['available_midpoints'] ?? null;
        $this->assertIsArray($available);

        $ids = array_map(fn ($m) => $m['id'], $available);
        $this->assertNotContains($p1->id, $ids);
        $this->assertNotContains($p4->id, $ids);
        $this->assertSame([$p2->id, $p3->id], $ids);
    }

    /** @test */
    public function it_sets_empty_available_midpoints_when_rows_less_than_three(): void
    {
        $p1 = $this->createPole('P1', 'Pole 1');
        $p2 = $this->createPole('P2', 'Pole 2');

        $route = $this->createHikingRoute([
            'signage' => [
                'checkpoint_order' => [$p1->id, $p2->id],
                'checkpoint' => [$p1->id, $p2->id],
            ],
        ]);

        $resource = (object) [
            'properties' => [
                'signage' => [
                    (string) $route->id => [
                        'arrows' => [
                            [
                                'rows' => [
                                    ['id' => $p1->id],
                                    ['id' => $p2->id],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $resolved = $this->callResolveAttribute($resource, 'properties.signage');
        $available = $resolved[(string) $route->id]['arrows'][0]['available_midpoints'] ?? null;
        $this->assertSame([], $available);
    }

    /** @test */
    public function it_does_not_touch_arrows_when_checkpoint_order_is_missing(): void
    {
        $p1 = $this->createPole('P1', 'Pole 1');
        $p2 = $this->createPole('P2', 'Pole 2');
        $p3 = $this->createPole('P3', 'Pole 3');

        $route = $this->createHikingRoute([
            'signage' => [
                // checkpoint_order assente
                'checkpoint' => [$p1->id, $p2->id, $p3->id],
            ],
        ]);

        $resource = (object) [
            'properties' => [
                'signage' => [
                    (string) $route->id => [
                        'arrows' => [
                            [
                                'rows' => [
                                    ['id' => $p1->id],
                                    ['id' => $p2->id],
                                    ['id' => $p3->id],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $resolved = $this->callResolveAttribute($resource, 'properties.signage');
        $arrow = $resolved[(string) $route->id]['arrows'][0] ?? null;
        $this->assertNotNull($arrow);
        $this->assertArrayNotHasKey('available_midpoints', $arrow, 'Senza checkpoint_order, resolveAttribute() fa continue e non aggiunge la chiave');
    }
}

