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

        DB::shouldReceive('select')
            ->once()
            ->with($expectedQuery)
            ->andReturn([(object)['g' => 'geometry_4326']]);

        $result = $this->geometryService->geometryTo4326Srid($this->geoJsonPoint);
        $this->assertEquals('geometry_4326', $result);
    }

    public function testTextToGeojsonWithValidLineStringJson()
    {
        $lineStringText = '{
            "type": "LineString", 
            "coordinates": [[0, 0], [1, 1]]
        }';

        $result = $this->geometryService->textToGeojson($lineStringText);

        $this->assertEquals(["type" => "LineString", "coordinates" => [[0, 0], [1, 1]]], $result);
    }

    public function testTextToGeojsonWithValidFeatureCollectionJson(){

        $featureCollectionText = '{
            "type": "FeatureCollection", 
            "features": [
                {"geometry": {"type": "Point", "coordinates": [125.6, 10.1]}}
            ]
        }';

        $result = $this->geometryService->textToGeojson($featureCollectionText);

        $this->assertEquals(["type" => "Point", "coordinates" => [125.6, 10.1]], $result);
    }

    public function testTextToGeojsonWithValidGeometryCollectionJson(){
        $geometryCollectionText = '{
            "type": "GeometryCollection",
            "geometries": [
              {"type": "LineString", "coordinates": [[0, 0], [1, 1]]},
              {"type": "Point", "coordinates": [0, 0]}
            ]
        }';

        $result = $this->geometryService->textToGeojson($geometryCollectionText);

        $this->assertEquals(["type" => "LineString", "coordinates" => [[0, 0], [1, 1]]], $result);
    }

    public function testTextToGeoJsonWithValidFeatureJson()
    {
        $featureText = '{
            "type": "Feature",
            "geometry": {"type": "LineString", "coordinates": [[0, 0], [1, 1]]}
        }';

        $result = $this->geometryService->textToGeojson($featureText);

        $this->assertEquals(["type" => "LineString", "coordinates" => [[0, 0], [1, 1]]], $result);
    }

    public function testTextToGeoJsonWithValidXmlGpxFile()
    {
        $xmlText = '<?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="Example Creator">
                <trk>
                    <name>Example GPX Track</name>
                    <trkseg>
                        <trkpt lat="4" lon="5">
                            <ele>4.46</ele>
                            <time>2009-10-17T18:37:26Z</time>
                        </trkpt>
                        <trkpt lat="3" lon="2">
                            <ele>4.94</ele>
                            <time>2009-10-17T18:37:31Z</time>
                        </trkpt>
                    </trkseg>
                </trk>
            </gpx>
            ';
        
        $result = $this->geometryService->textToGeojson($xmlText);

        $this->assertEquals(["type" => "LineString", "coordinates" => [[5, 4], [2, 3]]], $result);
    }

    public function testTextToGeoJsonWithValidKmlFile()
    {
        $kmlText = '<?xml version="1.0" encoding="UTF-8"?>
            <kml xmlns="http://www.opengis.net/kml/2.2">
            <Document>
                <Placemark>
                <name>Example KML LineString</name>
                <LineString>
                    <coordinates>
                    -12,4 -13,8
                    </coordinates>
                </LineString>
                </Placemark>
            </Document>
            </kml>
        ';

        $result = $this->geometryService->textToGeojson($kmlText);

        $this->assertEquals(["type" => "LineString", "coordinates" => [[-12, 4], [-13, 8]]], $result);
    }

    public function testTextToGeojsonWithInvalidOrNullText()
    {
        $invalidText = 'invalid text';
        $emptyText = null;
        $this->assertNull($this->geometryService->textToGeojson($invalidText));
        $this->assertNull($this->geometryService->textToGeojson($emptyText));
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
        $expectedCentre = DB::select("select ST_AsGeoJSON(ST_Centroid('".$this->geoJsonLineString."')) as g")[0]->g;
        $result = $this->geometryService->getCentroid($this->geoJsonLineString);
        $this->assertEquals($expectedCentre, $result);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}