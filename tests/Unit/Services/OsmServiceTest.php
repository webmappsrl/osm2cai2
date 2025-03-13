<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\OsmService;
use App\Models\HikingRoute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Sector;
use Illuminate\Support\Facades\DB;
use Mockery;
class OsmServiceTest extends TestCase
{
    use DatabaseTransactions;
    protected $osmService;
    protected $hikingRouteModel;
    const HIKING_ROUTE_EXPECTED_DATA = [
        'name' => 'Test Hiking Route',
        'ref' => 'TR-123',
        'network' => 'lwn',
        'distance' => '5.2',
        'osm_id' => 1,
    ];
    const HIKING_ROUTE_EXPECTED_GEOJSON = '{"type": "LineString", "coordinates": [[1,2],[3,4]]}';
    const HIKING_ROUTE_EXPECTED_GPX = '<?xml version="1.0" encoding="UTF-8"?>
                <gpx version="1.1">
                    <trk>
                        <trkseg>
                            <trkpt lat="45.0" lon="9.0"></trkpt>
                            <trkpt lat="45.1" lon="9.1"></trkpt>
                        </trkseg>
                    </trk>
                </gpx>';
    const HIKING_ROUTE_EXPECTED_GEOMETRY = '0105000020E610000001000000010200000002000000000000000000224000000000008046403333333333332240CDCCCCCCCC8C4640';
    const HIKING_ROUTE_EXPECTED_GEOMETRY_3857 = '0105000020110F000001000000010200000002000000B74D93D526932E4154C51D5FC4715541780781BB1EEA2E413FC6EB8C27815541';
    protected $intersectingSector;
    protected $nonIntersectingSector;
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('wm-osmfeatures:initialize-tables', ['--table' => 'hiking_routes']);
        if (! Schema::hasColumn('hiking_routes', 'geometry')) {
            Schema::table('hiking_routes', function (Blueprint $table) {
                $table->geometry('geometry')->nullable();
            });
        }

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

        $this->intersectingSector = Sector::factory()->create(
            [
                'geometry' => DB::raw("ST_GeomFromText('POLYGON((8.95 44.95, 8.95 45.05, 9.05 45.05, 9.05 44.95, 8.95 44.95))', 4326)")
            ]
        );
        $this->nonIntersectingSector = Sector::factory()->create(
            [
                'geometry' => DB::raw("ST_GeomFromText('POLYGON((9.2 45.2, 9.2 45.3, 9.3 45.3, 9.3 45.2, 9.2 45.2))', 4326)")
            ]
        );
    }

    /** @test */
    public function hikingRouteExistsReturnsTrueIfRelationIdIsValid()
    {
        $this->assertTrue($this->osmService->hikingRouteExists(1));
        $this->assertFalse($this->osmService->hikingRouteExists(999));
    }

    /** @test */
    public function getHikingRouteWorksAsExpected()
    {
        $hikingRoute = $this->osmService->getHikingRoute(1);
        $this->assertEquals(self::HIKING_ROUTE_EXPECTED_DATA['name'], $hikingRoute['name']);
        $this->assertEquals(self::HIKING_ROUTE_EXPECTED_DATA['ref'], $hikingRoute['ref']);
        $this->assertEquals(self::HIKING_ROUTE_EXPECTED_DATA['network'], $hikingRoute['network']);
        $this->assertEquals(self::HIKING_ROUTE_EXPECTED_DATA['distance'], $hikingRoute['distance']);
        $this->assertEquals(self::HIKING_ROUTE_EXPECTED_DATA['osm_id'], $hikingRoute['osm_id']);
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
        $this->assertEquals(self::HIKING_ROUTE_EXPECTED_GEOJSON, $geojson);
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
        $this->assertEquals(self::HIKING_ROUTE_EXPECTED_GPX, $gpx);
    }

    /** @test */
    public function getHikingRouteGeometryWorksAsExpected()
    {
        $geometry = $this->osmService->getHikingRouteGeometry(1);
        $this->assertEquals(self::HIKING_ROUTE_EXPECTED_GEOMETRY, $geometry);
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
        $this->assertEquals(self::HIKING_ROUTE_EXPECTED_GEOMETRY_3857, $geometry);
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
        $firstHikingRoute = HikingRoute::factory()->create(
            [
                'geometry' => null,
                'osmfeatures_data' => [
                    'properties' => [
                        'osm_id' => 1,
                    ],
                ],
            ]
        );
        $secondHikingRoute = HikingRoute::factory()->create(
            [
                'geometry' => null,
                'osmfeatures_data' => [
                    'properties' => [
                        'osm_id' => 1,
                    ],
                ],
            ]
        );
        $this->assertTrue($this->osmService->updateHikingRouteModelWithOsmData($firstHikingRoute, $this->osmService->getHikingRoute(1)));
        $this->assertTrue($this->osmService->updateHikingRouteModelWithOsmData($secondHikingRoute, null));
        $this->assertConditionsForHikingRoute($firstHikingRoute);
        $this->assertConditionsForHikingRoute($secondHikingRoute);
    }

    private function assertConditionsForHikingRoute($hikingRoute){
        $this->assertDatabaseHas('hiking_routes', [
            'id' => $hikingRoute->id,
            'osmfeatures_data->geometry->type' => 'MultiLineString',
            'osmfeatures_data->properties->osm_id' => 1,
            'osmfeatures_data->properties->name'   => self::HIKING_ROUTE_EXPECTED_DATA['name'],
            'osmfeatures_data->properties->ref'    => self::HIKING_ROUTE_EXPECTED_DATA['ref'],
            'osmfeatures_data->properties->network'=> self::HIKING_ROUTE_EXPECTED_DATA['network'],
            'osmfeatures_data->properties->distance' => self::HIKING_ROUTE_EXPECTED_DATA['distance'],
        ]);

        $this->assertTrue($hikingRoute->sectors->contains($this->intersectingSector->id));
        $this->assertFalse($hikingRoute->sectors->contains($this->nonIntersectingSector->id));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
