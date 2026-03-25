<?php

namespace Tests\Feature\V3;

use App\Models\Sector;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SectorControllerTest extends TestCase
{
    use DatabaseTransactions;

    private Generator $faker;

    private const DEFAULT_GEOMETRY = 'MULTIPOLYGON(((10.0 44.0, 10.1 44.0, 10.1 44.1, 10.0 44.1, 10.0 44.0)))';

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = FakerFactory::create();
    }

    private function createSector(array $attributes = [], string $geometry = self::DEFAULT_GEOMETRY): Sector
    {
        $defaults = [
            'name' => $this->faker->name,
            'code' => $this->faker->randomLetter,
            'full_code' => strtoupper($this->faker->lexify('???')),
            'num_expected' => $this->faker->numberBetween(1, 10),
            'human_name' => null,
            'manager' => null,
        ];

        $attrs = array_merge($defaults, $attributes);

        $id = DB::selectOne(
            "INSERT INTO sectors (name, code, full_code, num_expected, human_name, manager, geometry, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ST_GeomFromText(?, 4326), NOW(), NOW())
             RETURNING id",
            [
                $attrs['name'],
                $attrs['code'],
                $attrs['full_code'],
                $attrs['num_expected'],
                $attrs['human_name'],
                $attrs['manager'],
                $geometry,
            ]
        )->id;

        return Sector::find($id);
    }

    // -------------------------------------------------------------------------
    // LIST
    // -------------------------------------------------------------------------

    public function test_list_returns_array_with_correct_structure(): void
    {
        $sector = $this->createSector(['name' => 'Settore Test']);

        $response = $this->getJson('/api/v3/sectors/list');

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['id', 'updated_at', 'name'],
            ]);

        $item = collect($response->json())->firstWhere('id', $sector->id);
        $this->assertNotNull($item);
        $this->assertEquals(['it' => 'Settore Test'], $item['name']);
        $this->assertArrayHasKey('updated_at', $item);
    }

    public function test_list_filters_by_updated_at(): void
    {
        $old = $this->createSector();
        DB::table('sectors')->where('id', $old->id)->update(['updated_at' => now()->subDays(10)]);

        $recent = $this->createSector();
        DB::table('sectors')->where('id', $recent->id)->update(['updated_at' => now()]);

        $response = $this->getJson('/api/v3/sectors/list?updated_at='.now()->subDays(5)->format('Y-m-d'));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->toArray();

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_list_filters_by_bbox(): void
    {
        // Settore dentro il bbox (10.0-10.1, 44.0-44.1)
        $inside = $this->createSector();

        // Settore fuori dal bbox
        $outside = $this->createSector([], 'MULTIPOLYGON(((20.0 50.0, 20.1 50.0, 20.1 50.1, 20.0 50.1, 20.0 50.0)))');

        $response = $this->getJson('/api/v3/sectors/list?bbox=9.9,43.9,10.2,44.2');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->toArray();

        $this->assertContains($inside->id, $ids);
        $this->assertNotContains($outside->id, $ids);
    }

    public function test_list_returns_400_for_invalid_bbox(): void
    {
        $this->getJson('/api/v3/sectors/list?bbox=10.0,44.0')
            ->assertStatus(400)
            ->assertJsonFragment(['error' => 'Invalid bbox format. Expected: min_lon,min_lat,max_lon,max_lat']);
    }

    public function test_list_name_is_wrapped_in_translatable_format(): void
    {
        $this->createSector(['name' => 'Nome Italiano']);

        $response = $this->getJson('/api/v3/sectors/list');

        $response->assertOk();
        $item = collect($response->json())->first();
        $this->assertIsArray($item['name']);
        $this->assertArrayHasKey('it', $item['name']);
    }

    // -------------------------------------------------------------------------
    // SHOW
    // -------------------------------------------------------------------------

    public function test_show_returns_geojson_feature(): void
    {
        $sector = $this->createSector([
            'name' => 'Settore A',
            'code' => 'A',
            'full_code' => 'LIG01',
            'num_expected' => 42,
            'human_name' => 'Settore Alpino Ligure',
            'manager' => 'Mario Rossi',
        ]);

        $response = $this->getJson("/api/v3/sectors/{$sector->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'type',
                'geometry' => ['type', 'coordinates'],
                'properties' => [
                    'id', 'name', 'code', 'full_code',
                    'num_expected', 'human_name', 'manager',
                    'created_at', 'updated_at',
                ],
            ]);

        $json = $response->json();
        $this->assertEquals('Feature', $json['type']);
        $this->assertEquals('MultiPolygon', $json['geometry']['type']);
        $this->assertEquals(['it' => 'Settore A'], $json['properties']['name']);
        $this->assertEquals(['it' => 'Settore Alpino Ligure'], $json['properties']['human_name']);
        $this->assertEquals('A', $json['properties']['code']);
        $this->assertEquals('LIG01', $json['properties']['full_code']);
        $this->assertEquals(42, $json['properties']['num_expected']);
        $this->assertEquals('Mario Rossi', $json['properties']['manager']);
    }

    public function test_show_returns_404_for_missing_sector(): void
    {
        $this->getJson('/api/v3/sectors/999999')
            ->assertNotFound()
            ->assertJsonFragment(['error' => 'Sector not found']);
    }

    public function test_show_human_name_null_when_not_set(): void
    {
        $sector = $this->createSector(['human_name' => null]);

        $response = $this->getJson("/api/v3/sectors/{$sector->id}");

        $response->assertOk();
        $this->assertNull($response->json('properties.human_name'));
    }

    public function test_show_human_name_is_not_wrapped_when_null(): void
    {
        $sector = $this->createSector(['human_name' => null]);

        $response = $this->getJson("/api/v3/sectors/{$sector->id}");

        $humanName = $response->json('properties.human_name');
        // Deve essere null, NON {"it": null}
        $this->assertNull($humanName);
    }
}
