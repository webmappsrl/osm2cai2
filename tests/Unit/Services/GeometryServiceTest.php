<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\GeometryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;

class GeometryServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected $geometryService;
    protected $geoJsonSimpleArray = [
        "type" => "Point",
        "coordinates" => [125.6, 10.1]
    ];
    protected $geoJsonLineString = '{"type": "LineString", "coordinates": [[1,2],[3,4]]}';
    protected $geoJsonPoint = '{"type":"Point","coordinates":[125.6,10.1]}';
    protected $jsonText = '{"type": "FeatureCollection", "features": [{"geometry": {"type": "Point", "coordinates": [125.6, 10.1]}}]}';
    protected $encodedGeoJsonSimpleArray;
    protected $encodedGeoJsonLineString;
    public function setUp(): void
    {
        parent::setUp();
        $this->geometryService = new GeometryService();
        $this->encodedGeoJsonSimpleArray = json_encode($this->geoJsonSimpleArray);
    }

    public function testGeojsonToGeometryReturnsNullOnEmpty()
    {
        $this->assertNull($this->geometryService->geojsonToGeometry(''));
    }

    public function testGeojsonToGeometryWithArrayInput()
    {
        DB::shouldReceive('select')
            ->once()
            ->with("select (ST_Force3D(ST_GeomFromGeoJSON('".$this->encodedGeoJsonSimpleArray."'))) as g ")
            ->andReturn([(object)['g' => 'geometry_value']]);

        $result = $this->geometryService->geojsonToGeometry($this->geoJsonSimpleArray);
        $this->assertEquals('geometry_value', $result);
    }

    public function testGeojsonToMultilinestringGeometry()
    {
        $geojson = $this->geoJsonLineString;
        $expectedQuery = "select (
        ST_Multi(
          ST_GeomFromGeoJSON('".$geojson."')
        )
    ) as g ";

        DB::shouldReceive('select')
            ->once()
            ->with($expectedQuery)
            ->andReturn([(object)['g' => 'multilinestring_geometry']]);

        $result = $this->geometryService->geojsonToMultilinestringGeometry($geojson);
        $this->assertEquals('multilinestring_geometry', $result);
    }

    public function testGeojsonToMultilinestringGeometry3857()
    {
        $expectedQuery = "select (
        ST_Multi(
          ST_Transform( ST_GeomFromGeoJSON('".$this->geoJsonLineString."' ) , 3857 )
        )
    ) as g ";
     
        $this->testIfRawQueryGetsCalled($expectedQuery);

        DB::shouldReceive('select')
            ->once()
            ->with($expectedQuery)
            ->andReturn([(object)['g' => 'multilinestring_geometry_3857']]);

        $result = $this->geometryService->geojsonToMultilinestringGeometry3857($this->geoJsonLineString);
        $this->assertEquals('multilinestring_geometry_3857', $result);
    }

    public function testGeometryTo4326Srid()
    {
        $expectedQuery = "select (
      ST_Transform('".$this->geoJsonPoint."', 4326)
    ) as g ";

        $this->testIfRawQueryGetsCalled($expectedQuery);

        DB::shouldReceive('select')
            ->once()
            ->with($expectedQuery)
            ->andReturn([(object)['g' => 'geometry_4326']]);

        $result = $this->geometryService->geometryTo4326Srid($this->geoJsonPoint);
        $this->assertEquals('geometry_4326', $result);
    }

    public function testTextToGeojsonWithValidJsonFeatureCollection()
    {
        $result = $this->geometryService->textToGeojson($this->jsonText);

        $this->assertEquals(["type" => "Point", "coordinates" => [125.6, 10.1]], $result);
    }

    public function testTextToGeojsonWithInvalidText()
    {
        $text = 'invalid text';

        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        });
        
        try {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('Undefined variable $contentGeometry');
            $this->geometryService->textToGeojson($text);
        } finally {
            restore_error_handler();
        }
    }

    public function testGetGeometryTypeForHikingRoutes()
    {
        $table = 'hiking_routes';
        $geometryColumn = 'geom';

        DB::shouldReceive('selectOne')
            ->once()
            ->with(\Mockery::on(function($query) use ($table, $geometryColumn) {
                return strpos($query, "FROM {$table}") !== false
                    && strpos($query, "CASE") !== false;
            }))
            ->andReturn((object)['geom_type' => 'ST_Point']);

        $result = $this->geometryService->getGeometryType($table, $geometryColumn);
        $this->assertEquals('Point', $result);
    }

    public function testGetGeometryTypeForOtherTable()
    {
        $table = 'other_table';
        $geometryColumn = 'geom';

        DB::shouldReceive('selectOne')
            ->once()
            ->with(\Mockery::on(function($query) use ($table, $geometryColumn) {
                return strpos($query, "FROM {$table}") !== false
                    && strpos($query, "ST_GeometryType({$geometryColumn})") !== false;
            }))
            ->andReturn((object)['geom_type' => 'ST_LineString']);

        $result = $this->geometryService->getGeometryType($table, $geometryColumn);
        $this->assertEquals('LineString', $result);
    }

    public function testGetCentroidReturnsNullForEmpty()
    {
        $this->assertNull($this->geometryService->getCentroid(''));
    }

    public function testGetCentroidReturnsCentroid()
    {
        $result = $this->geometryService->getCentroid($this->geoJsonPoint);
        $this->assertEquals($this->geoJsonPoint, $result);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    private function testIfRawQueryGetsCalled($query)
    {
        DB::shouldReceive('raw')
            ->once()
            ->with($query)
            ->andReturn($query);
    }
}
