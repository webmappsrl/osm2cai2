<?php

namespace Tests\Api;

use App\Models\Area;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShapeFileControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $region;

    protected $province;

    protected $area;

    protected $sector;

    protected function setUp(): void
    {
        parent::setUp();

        // Assicurati che la directory esista
        Storage::disk('public')->makeDirectory('shape_files/zip');
        Storage::disk('public')->makeDirectory('shape_files/shp');

        // Crea le entità di test come prima
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
    }

    protected function tearDown(): void
    {
        // Pulisci i file temporanei dopo ogni test
        Storage::disk('public')->deleteDirectory('shape_files');
        parent::tearDown();
    }

    public function test_download_shapefile_for_region()
    {
        $response = $this->get("/api/shapefile/region/{$this->region->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename=test-region-'.date('Ymd').'.zip'
            );

        Storage::disk('public')->assertExists('shape_files/zip/Test_Region.zip');
    }

    public function test_download_shapefile_with_invalid_model_type()
    {
        $response = $this->get('/api/shapefile/invalid_model/1');

        $response->assertStatus(404)
            ->assertSee('Invalid model type');
    }

    public function test_download_shapefile_with_nonexistent_model()
    {
        $response = $this->get('/api/shapefile/region/99999');

        $response->assertStatus(404)
            ->assertSee('Model not found');
    }

    public function test_download_shapefile_for_sector()
    {
        $response = $this->get("/api/shapefile/sector/{$this->sector->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename=test-sector-'.date('Ymd').'.zip'
            );

        Storage::disk('public')->assertExists('shape_files/zip/Test_Sector.zip');
    }

    public function test_shapefile_contains_required_files()
    {
        $response = $this->get("/api/shapefile/region/{$this->region->id}");

        $zipPath = Storage::disk('public')->path('shape_files/zip/Test_Region.zip');
        $this->assertFileExists($zipPath);

        $zip = new \ZipArchive;
        $zip->open($zipPath);

        $requiredExtensions = ['.shp', '.shx', '.dbf', '.prj'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $extension = substr($filename, strrpos($filename, '.'));
            $key = array_search($extension, $requiredExtensions);
            if ($key !== false) {
                unset($requiredExtensions[$key]);
            }
        }

        $this->assertEmpty($requiredExtensions, 'Missing required shapefile components');
    }
}
