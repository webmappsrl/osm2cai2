<?php

namespace Tests\Unit\Models;

use App\Models\SiPoi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Wm\WmPackage\Models\TaxonomyPoiType;

class SiPoiTaxonomyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /** @test */
    public function it_attaches_punto_accoglienza_taxonomy_poi_type_on_create(): void
    {
        $taxonomyPoiType = TaxonomyPoiType::firstOrCreate(
            ['identifier' => SiPoi::DEFAULT_TAXONOMY_POI_TYPE_IDENTIFIER],
            [
                'name' => ['it' => 'Punto Accoglienza'],
                'description' => [],
                'excerpt' => [],
                'icon' => 'txn-alpine-hut',
            ]
        );

        $siPoi = SiPoi::create([
            'name' => ['it' => 'Test Welcome Point'],
            'geometry' => DB::raw("ST_GeomFromText('POINTZ(12.5 41.9 0)', 4326)"),
        ]);

        $this->assertTrue(
            $siPoi->taxonomyPoiTypes()
                ->where('taxonomy_poi_types.id', $taxonomyPoiType->id)
                ->exists()
        );
        $this->assertSame(2, $siPoi->fresh()->app_id);
    }
}
