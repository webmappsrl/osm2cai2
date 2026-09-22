<?php

namespace Tests\Unit\Nova\Filters;

use App\Models\HikingRoute;
use App\Models\Sector;
use App\Models\User;
use App\Nova\Filters\SectorFilter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class SectorFilterTest extends TestCase
{
    use DatabaseTransactions;

    private const SECTOR_GEOMETRY = "ST_GeomFromText('MULTIPOLYGON(((0 0, 10 0, 10 10, 0 10, 0 0)))', 4326)";

    protected function createNovaRequest(): NovaRequest
    {
        return NovaRequest::create('/nova-api/hiking-routes', 'GET');
    }

    private function createSector(array $attributes = []): Sector
    {
        return Sector::factory()->create(array_merge(
            ['geometry' => DB::raw(self::SECTOR_GEOMETRY)],
            $attributes
        ));
    }

    private function createHikingRoute(): HikingRoute
    {
        return HikingRoute::factory()->createQuietly();
    }

    private function createUser(array $attributes = []): User
    {
        return User::find(User::factory()->createQuietly($attributes)->id);
    }

    public function test_apply_no_sector_filters_hiking_routes_without_sectors(): void
    {
        $filter = new SectorFilter();
        $request = $this->createNovaRequest();

        $sector = $this->createSector();
        $hikingRouteWithSector = $this->createHikingRoute();
        $hikingRouteWithoutSector = $this->createHikingRoute();

        $hikingRouteWithSector->sectors()->attach($sector->id, ['percentage' => 100]);

        $query = HikingRoute::query();
        $result = $filter->apply($request, $query, 'no_sector')->get();

        $this->assertTrue($result->contains($hikingRouteWithoutSector));
        $this->assertFalse($result->contains($hikingRouteWithSector));
    }

    public function test_apply_specific_sector_filters_hiking_routes_by_sector(): void
    {
        $filter = new SectorFilter();
        $request = $this->createNovaRequest();

        $sectorOne = $this->createSector();
        $sectorTwo = $this->createSector();

        $hikingRouteOne = $this->createHikingRoute();
        $hikingRouteTwo = $this->createHikingRoute();
        $hikingRouteWithoutSector = $this->createHikingRoute();

        $hikingRouteOne->sectors()->attach($sectorOne->id, ['percentage' => 100]);
        $hikingRouteTwo->sectors()->attach($sectorTwo->id, ['percentage' => 100]);

        $query = HikingRoute::query();
        $result = $filter->apply($request, $query, $sectorOne->id)->get();

        $this->assertTrue($result->contains($hikingRouteOne));
        $this->assertFalse($result->contains($hikingRouteTwo));
        $this->assertFalse($result->contains($hikingRouteWithoutSector));
    }

    public function test_options_includes_no_sector_and_all_sectors_for_non_regional_user(): void
    {
        $filter = new SectorFilter();
        $request = $this->createNovaRequest();

        /** @var User $user */
        $user = $this->createUser();
        $this->actingAs($user);

        $sectorOne = $this->createSector(['full_code' => 'AA']);
        $sectorTwo = $this->createSector(['full_code' => 'BB']);

        $options = $filter->options($request);

        $this->assertArrayHasKey(__('Senza settore'), $options);
        $this->assertSame('no_sector', $options[__('Senza settore')]);
        $this->assertArrayHasKey($sectorOne->full_code, $options);
        $this->assertArrayHasKey($sectorTwo->full_code, $options);
        $this->assertEquals(__('Senza settore'), array_key_first($options));
    }
}
