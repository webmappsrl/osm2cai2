<?php

namespace Tests\Unit\Services;

use App\Models\Area;
use App\Services\AreaModelService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AreaModelServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected $area;

    protected $areaService;

    const WKT_POLYGON = 'POLYGON((0 0, 10 0, 10 10, 0 10, 0 0))';

    protected function setUp(): void
    {
        parent::setUp();
        $this->area = Area::factory()->create();

        $this->area->children()->createMany([
            [
                'code' => 'B',
                'name' => 'Test Area 1',
                'full_code' => '472B',
                'geometry' => self::WKT_POLYGON,
                'num_expected' => 1,
            ],
        ]);

        $this->areaService = new AreaModelService();
    }

    protected function convertPolygonToWKB(string $wktPolygon): string
    {
        $result = DB::selectOne(
            'SELECT ST_AsEWKB(ST_GeomFromText(?, 4326)) as wkb',
            [$wktPolygon]
        );
        $wkb = is_resource($result->wkb) ? stream_get_contents($result->wkb) : $result->wkb;

        return strtoupper(bin2hex($wkb));
    }

    public function testComputeGeometryBySectors()
    {
        $this->assertEquals(
            $this->convertPolygonToWKB(self::WKT_POLYGON),
            $this->areaService->computeGeometryBySectors($this->area)
        );
    }
}
