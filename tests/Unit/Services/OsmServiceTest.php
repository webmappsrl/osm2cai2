<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\OsmService;
use App\Models\HikingRoute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Mockery;
class OsmServiceTest extends TestCase
{
    use DatabaseTransactions;
    protected $osmService;
    protected $hikingRouteModel;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('wm-osmfeatures:initialize-tables', ['--table' => 'hiking_routes']);

        $this->osmService = new OsmService();
        http::fake([
            'https://www.openstreetmap.org/api/0.6/relation/1' => 
                Http::response('<?xml version="1.0" encoding="UTF-8"?>
                <osm version="0.6">
                    <relation id="12345">
                        <tag k="name" v="Test Hiking Route"/>
                        <tag k="ref" v="TR-123"/>
                        <tag k="network" v="lwn"/>
                        <tag k="distance" v="5.2"/>
                    </relation>
                </osm>',
                200
            ),
            'https://www.openstreetmap.org/api/0.6/relation/999' => 
                Http::response('<?xml version="1.0" encoding="UTF-8"?>
                <osm version="0.6">
                    <relation id="999">
                        <tag k="name" v="Test Hiking Route"/>
                        <tag k="ref" v="TR-123"/>
                        <tag k="network" v="lwn"/>
                        <tag k="distance" v="5.2"/>
                    </relation>
                </osm>',
                404
            ),
            'https://hiking.waymarkedtrails.org/api/v1/details/relation/1/geometry/geojson' => 
                Http::response('{"type": "LineString", "coordinates": [[1,2],[3,4]]}', 200),
            
            'https://hiking.waymarkedtrails.org/api/v1/details/relation/999/geometry/geojson' => 
                Http::response('{"type": "LineString", "coordinates": [[1,2],[3,4]]}', 404),

            'https://www.openstreetmap.org/api/0.6/relation/*' => Http::response(
                '<?xml version="1.0" encoding="UTF-8"?>
                <osm version="0.6">
                    <relation id="12345">
                        <tag k="name" v="Test Hiking Route"/>
                        <tag k="ref" v="TR-123"/>
                        <tag k="network" v="lwn"/>
                        <tag k="distance" v="5.2"/>
                    </relation>
                </osm>',
                200
            ),
            'https://hiking.waymarkedtrails.org/api/v1/details/relation/1/geometry/gpx' => Http::response(
                '<?xml version="1.0" encoding="UTF-8"?>
                <gpx version="1.1">
                    <trk>
                        <trkseg>
                            <trkpt lat="45.0" lon="9.0"></trkpt>
                            <trkpt lat="45.1" lon="9.1"></trkpt>
                        </trkseg>
                    </trk>
                </gpx>',
                200
            ),
            'https://hiking.waymarkedtrails.org/api/v1/details/relation/999/geometry/gpx' => 
                Http::response(
                    '<?xml version="1.0" encoding="UTF-8"?>
                    <gpx version="1.1">
                        <trk>
                            <trkseg>
                                <trkpt lat="45.0" lon="9.0"></trkpt>
                                <trkpt lat="45.1" lon="9.1"></trkpt>
                            </trkseg>
                        </trk>
                    </gpx>',
                    404
                ),
            
        ]);
    }

    /** @test */
    public function hikingRouteExistsReturnsTrueIfRelationIdIsValid()
    {
        $this->assertTrue($this->osmService->hikingRouteExists(1));
    }

    /** @test */
    public function hikingRouteExistsReturnsFalseIfRelationIdIsNotValid()
    {
        $this->assertFalse($this->osmService->hikingRouteExists(999));
    }

    /** @test */
    public function getHikingRouteWorksAsExpected()
    {
        $hikingRoute = $this->osmService->getHikingRoute(1);
        $this->assertEquals('Test Hiking Route', $hikingRoute['name']);
        $this->assertEquals('TR-123', $hikingRoute['ref']);
        $this->assertEquals('lwn', $hikingRoute['network']);
        $this->assertEquals('5.2', $hikingRoute['distance']);
        $this->assertEquals('1', $hikingRoute['osm_id']);
    }

    /** @test */
    public function getHikingRouteReturnsFalseIfRelationIdIsNotValid()
    {
        $hikingRoute = $this->osmService->getHikingRoute(999);
        $this->assertFalse($hikingRoute);
    }

    /** @test */
    public function getHikingRouteGeojsonWorksAsExpected()
    {
        $geojson = $this->osmService->getHikingRouteGeojson(1);
        $this->assertEquals('{"type": "LineString", "coordinates": [[1,2],[3,4]]}', $geojson);
    }

    /** @test */
    public function getHikingRouteGeojsonReturnsFalseIfRelationIdIsNotValid()
    {
        $geojson = $this->osmService->getHikingRouteGeojson(999);
        $this->assertFalse($geojson);
    }

    /** @test */
    public function getHikingRouteGpxWorksAsExpected()
    {
        $gpx = $this->osmService->getHikingRouteGpx(1);
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8"?>
                <gpx version="1.1">
                    <trk>
                        <trkseg>
                            <trkpt lat="45.0" lon="9.0"></trkpt>
                            <trkpt lat="45.1" lon="9.1"></trkpt>
                        </trkseg>
                    </trk>
                </gpx>', $gpx);
    }

    /** @test */
    public function getHikingRouteGeometryWorksAsExpected()
    {
        $geometry = $this->osmService->getHikingRouteGeometry(1);
        $this->assertEquals('0105000020E610000001000000010200000002000000000000000000224000000000008046403333333333332240CDCCCCCCCC8C4640', $geometry);
    }

    /** @test */
    public function getHikingRouteGeometryReturnsFalseIfRelationIdIsNotValid()
    {
        $geometryFalse = $this->osmService->getHikingRouteGeometry(999);
        $geometryEmpty = $this->osmService->getHikingRouteGeometry('');
        $this->assertFalse($geometryFalse);
        $this->assertFalse($geometryEmpty);
    }

    /** @test */
    public function getHikingRouteGeometry3857WorksAsExpected()
    {
        $geometry = $this->osmService->getHikingRouteGeometry3857(1);
        $this->assertEquals('0105000020110F000001000000010200000002000000B74D93D526932E4154C51D5FC4715541780781BB1EEA2E413FC6EB8C27815541', $geometry);
    }
    
    /** @test */
    public function getHikingRouteGeometry3857ReturnsFalseIfRelationIdIsNotValidOrEmpty()
    {
        $geometryFalse = $this->osmService->getHikingRouteGeometry3857(999);
        $geometryEmpty = $this->osmService->getHikingRouteGeometry3857('');
        $this->assertFalse($geometryFalse);
        $this->assertFalse($geometryEmpty);
    }
    
    /** @test */
    public function getHikingRouteGpxReturnsFalseIfRelationIdIsNotValid()
    {
        $gpx = $this->osmService->getHikingRouteGpx(999);
        $this->assertFalse($gpx);
    }

    /** @test */
    public function updateHikingRouteModelWithOsmDataWorksAsExpected()
    {
        $hikingRoute = HikingRoute::factory()->create(
            [
                'geometry' => null,
                'osmfeatures_data' => [
                    'properties' => [
                        'osm_id' => 1,
                    ],
                ],
            ]
        );
        $result = $this->osmService->updateHikingRouteModelWithOsmData($hikingRoute, $this->osmService->getHikingRoute(1));
        $this->assertTrue($result);
        $this->assertDatabaseHas('hiking_routes', [
            'id' => $hikingRoute->id,
            'osmfeatures_data->geometry->type' => 'MultiLineString',
            'osmfeatures_data->properties->osm_id' => 1,
            'osmfeatures_data->properties->name'   => 'Test Hiking Route',
            'osmfeatures_data->properties->ref'    => 'TR-123',
            'osmfeatures_data->properties->network'=> 'lwn',
            'osmfeatures_data->properties->distance' => '5.2',
        ]);
        
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
