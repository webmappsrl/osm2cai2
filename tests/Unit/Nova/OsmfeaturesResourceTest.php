<?php

namespace Tests\Unit\Nova;

use App\Models\HikingRoute as HikingRouteModel;
use App\Nova\EnrichPoi;
use App\Nova\Municipality;
use App\Nova\OsmfeaturesResource;
use App\Nova\Poles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OsmfeaturesResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function getDummyQuery(): Builder
    {
        // Usiamo il model reale HikingRoute come "dummy" per costruire la query:
        // la logica di ricerca è condivisa tra tutte le risorse che estendono OsmfeaturesResource.
        return (new HikingRouteModel)->newQuery();
    }

    public function test_apply_search_with_osm_prefix_uses_osmfeatures_id_and_search_fields(): void
    {
        $query = $this->getDummyQuery();

        $result = TestOsmfeaturesResourceSimple::applySearchPublic($query, 'R19732');

        $sql = $result->toSql();
        $bindings = $result->getBindings();

        $this->assertStringContainsString('"osmfeatures_id"::text ilike ?', $sql);
        $this->assertStringContainsString('"name"::text ilike ?', $sql);

        $this->assertCount(2, $bindings);
        $this->assertContains('R19732%', $bindings);
        $this->assertContains('%R19732%', $bindings);
    }

    public function test_apply_search_with_numeric_value_uses_osm_prefixes_and_search_fields(): void
    {
        $query = $this->getDummyQuery();

        $result = TestOsmfeaturesResourceSimple::applySearchPublic($query, '19732');

        $sql = $result->toSql();
        $bindings = $result->getBindings();

        $this->assertStringContainsString('"osmfeatures_id"::text ilike ?', $sql);
        $this->assertStringContainsString('"name"::text ilike ?', $sql);

        $this->assertCount(4, $bindings);
        $this->assertContains('R19732%', $bindings);
        $this->assertContains('N19732%', $bindings);
        $this->assertContains('W19732%', $bindings);
        $this->assertContains('%19732%', $bindings);
    }

    public function test_apply_search_with_text_value_uses_search_fields_only(): void
    {
        $query = $this->getDummyQuery();

        $result = TestOsmfeaturesResourceSimple::applySearchPublic($query, 'hiking');

        $sql = $result->toSql();
        $bindings = $result->getBindings();

        $this->assertStringNotContainsString('osmfeatures_id', $sql);
        $this->assertStringContainsString('"name"::text ilike ?', $sql);

        $this->assertCount(1, $bindings);
        $this->assertContains('%hiking%', $bindings);
    }

    public function test_apply_search_with_json_field_uses_postgres_json_syntax(): void
    {
        $query = $this->getDummyQuery();

        $result = TestOsmfeaturesResourceWithJson::applySearchPublic($query, 'ABC123');

        $sql = $result->toSql();
        $bindings = $result->getBindings();

        $this->assertStringContainsString("osmfeatures_data->'properties'->>'ref' ILIKE ?", $sql);

        $this->assertCount(1, $bindings);
        $this->assertContains('%ABC123%', $bindings);
    }

    /**
     * Verifica che le risorse Nova che estendono OsmfeaturesResource
     * usino i campi di ricerca attesi per il testo libero.
     *
     * @dataProvider novaResourceSearchProvider
     *
     * @param  class-string  $resourceClass
     * @param  string  $searchTerm
     * @param  string[]  $expectedColumns
     */
    public function test_nova_resources_use_expected_search_fields_for_text_search(
        string $resourceClass,
        string $searchTerm,
        array $expectedColumns
    ): void {
        $query = $this->getDummyQuery();

        /** @var Builder $result */
        $result = $resourceClass::applySearchPublic($query, $searchTerm);

        $sql = $result->toSql();

        foreach ($expectedColumns as $column) {
            $this->assertStringContainsString(sprintf('"%s"::text ilike ?', $column), $sql);
        }
    }

    /**
     * @return array<string, array{0: class-string, 1: string, 2: string[]}>
     */
    public static function novaResourceSearchProvider(): array
    {
        return [
            'enrich poi' => [
                EnrichPoiSearchResource::class,
                'hiking',
                ['id', 'name', 'type'],
            ],
            'poles' => [
                PolesSearchResource::class,
                'ABC',
                ['id', 'ref'],
            ],
            'municipality' => [
                MunicipalitySearchResource::class,
                'Bolzano',
                ['id', 'name'],
            ],
        ];
    }
}

class TestOsmfeaturesResourceSimple extends OsmfeaturesResource
{
    /**
     * @var class-string<Model>
     */
    public static $model = HikingRouteModel::class;

    /**
     * @var array<int, string>
     */
    public static $search = [
        'name',
    ];

    public static function applySearchPublic(Builder $query, string $search): Builder
    {
        return static::applySearch($query, $search);
    }
}

class TestOsmfeaturesResourceWithJson extends OsmfeaturesResource
{
    /**
     * @var class-string<Model>
     */
    public static $model = HikingRouteModel::class;

    /**
     * @var array<int, string>
     */
    public static $search = [
        'osmfeatures_data->properties->ref',
    ];

    public static function applySearchPublic(Builder $query, string $search): Builder
    {
        return static::applySearch($query, $search);
    }
}

class EnrichPoiSearchResource extends EnrichPoi
{
    public static function applySearchPublic(Builder $query, string $search): Builder
    {
        return static::applySearch($query, $search);
    }
}

class PolesSearchResource extends Poles
{
    public static function applySearchPublic(Builder $query, string $search): Builder
    {
        return static::applySearch($query, $search);
    }
}

class MunicipalitySearchResource extends Municipality
{
    public static function applySearchPublic(Builder $query, string $search): Builder
    {
        return static::applySearch($query, $search);
    }
}
