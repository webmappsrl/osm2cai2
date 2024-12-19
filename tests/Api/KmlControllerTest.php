<?php

namespace Tests\Api;

use App\Models\Area;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KmlControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $region;

    protected $province;

    protected $area;

    protected $sector;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test models with geometry
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

    public function test_download_kml_for_region()
    {
        $response = $this->get("/api/kml/region/{$this->region->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.google-earth.kml+xml')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="regions_'.date('Ymd').'.kml"'
            );

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $response->getContent());
        $this->assertStringContainsString('<kml xmlns="http://www.opengis.net/kml/2.2">', $response->getContent());
    }

    public function test_download_kml_with_invalid_model_type()
    {
        $response = $this->get('/api/kml/invalid_model/1');

        $response->assertStatus(404)
            ->assertSee('Invalid model type');
    }

    public function test_download_kml_with_nonexistent_model()
    {
        $response = $this->get('/api/kml/region/99999');

        $response->assertStatus(404)
            ->assertSee('Model not found');
    }

    public function test_download_kml_for_sector()
    {
        $response = $this->get("/api/kml/sector/{$this->sector->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.google-earth.kml+xml')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="sectors_'.date('Ymd').'.kml"'
            );

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $response->getContent());
        $this->assertStringContainsString('<kml xmlns="http://www.opengis.net/kml/2.2">', $response->getContent());
    }

    public function test_kml_contains_valid_structure()
    {
        $response = $this->get("/api/kml/region/{$this->region->id}");
        $content = $response->getContent();

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<kml xmlns="http://www.opengis.net/kml/2.2">', $content);
        $this->assertStringContainsString('<Document>', $content);
        $this->assertStringContainsString('<Placemark>', $content);
        $this->assertStringContainsString('</Placemark>', $content);
        $this->assertStringContainsString('</Document>', $content);
        $this->assertStringContainsString('</kml>', $content);
    }

    public function test_kml_contains_geometry_data()
    {
        $response = $this->get("/api/kml/region/{$this->region->id}");
        $content = $response->getContent();

        $this->assertStringContainsString('<coordinates>', $content);
        $this->assertStringContainsString('</coordinates>', $content);
        $this->assertMatchesRegularExpression('/[-\d.,\s]+/', $content);
    }
}
