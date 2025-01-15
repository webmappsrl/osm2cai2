<?php

namespace Tests\Api;

use App\Models\Area;
use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CsvControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $region;

    protected $province;

    protected $area;

    protected $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->region = Region::factory()->create([
            'name' => 'Test Region',
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->province = Province::factory()->create([
            'name' => 'Test Province',
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->area = Area::factory()->create([
            'name' => 'Test Area',
            'code' => 'T',
            'full_code' => 'T123',
            'num_expected' => 10,
            'province_id' => $this->province->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->sector = Sector::factory()->create([
            'name' => 'Test Sector',
            'code' => 'T',
            'num_expected' => 10,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
            'full_code' => 'TS123',
            'area_id' => $this->area->id,
        ]);

        $hikingRoute = $this->createTestHikingRoute(4, 12345);

        DB::table('hiking_route_region')->insert([
            'hiking_route_id' => $hikingRoute->id,
            'region_id' => $this->region->id,
        ]);

        DB::table('hiking_route_province')->insert([
            'hiking_route_id' => $hikingRoute->id,
            'province_id' => $this->province->id,
        ]);

        DB::table('area_hiking_route')->insert([
            'hiking_route_id' => $hikingRoute->id,
            'area_id' => $this->area->id,
        ]);
    }

    protected function createTestHikingRoute($status, $osmId)
    {
        //if osmfeatures_data column is not present, create the column
        if (! Schema::hasColumn('hiking_routes', 'osmfeatures_data')) {
            Schema::table('hiking_routes', function (Blueprint $table) {
                $table->json('osmfeatures_data')->nullable();
            });
        }

        return HikingRoute::factory()->create([
            'osm2cai_status' => $status,
            'osmfeatures_id' => 'R' . $osmId,
            'osmfeatures_data' => [
                'properties' => [
                    'osm_id' => $osmId,
                    'ref' => 'TEST',
                    'source_ref' => '9200001',
                    'from' => 'Start',
                    'to' => 'End',
                    'cai_scale' => 'T',
                ],
            ],
            'geometry' => DB::raw("ST_GeomFromText('LINESTRING(0 0, 1 1)')"),
        ]);
    }

    public function test_download_csv_for_region()
    {
        $response = $this->get("/api/csv/region/{$this->region->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="osm2cai_' . date('Ymd') . '_regions_Test Region.csv"'
            );
    }

    public function test_download_csv_with_invalid_model_type()
    {
        $response = $this->get('/api/csv/invalid_model/1');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Invalid model type']);
    }

    public function test_download_csv_with_nonexistent_model()
    {
        $response = $this->get('/api/csv/region/99999');

        $response->assertStatus(404)
            ->assertJson(['error' => 'Model not found']);
    }

    public function test_download_csv_for_province()
    {
        $response = $this->get("/api/csv/province/{$this->province->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="osm2cai_' . date('Ymd') . '_provinces_Test Province.csv"'
            );
    }

    public function test_download_csv_for_area()
    {
        $response = $this->get("/api/csv/area/{$this->area->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="osm2cai_' . date('Ymd') . '_areas_Test Area.csv"'
            );
    }

    public function test_download_csv_for_sector()
    {
        $response = $this->get("/api/csv/sector/{$this->sector->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="osm2cai_' . date('Ymd') . '_sectors_Test Sector.csv"'
            );
    }
}
