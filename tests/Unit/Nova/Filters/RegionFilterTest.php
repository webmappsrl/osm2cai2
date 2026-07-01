<?php

namespace Tests\Unit\Nova\Filters;

use App\Models\Club;
use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use App\Models\User;
use App\Nova\Filters\RegionFilter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class RegionFilterTest extends TestCase
{
    use DatabaseTransactions;

    protected function createNovaRequest(): NovaRequest
    {
        return NovaRequest::create('/nova-api/users', 'GET');
    }

    private function createHikingRoute(): HikingRoute
    {
        return HikingRoute::factory()->createQuietly();
    }

    private function createRegion(): Region
    {
        return Region::factory()->createQuietly();
    }

    private function createProvince(array $attributes = []): Province
    {
        $province = Province::factory()->createQuietly();
        if ($attributes !== []) {
            $province->forceFill($attributes)->save();
        }
        return $province;
    }

    private function createUser(array $attributes = []): User
    {
        return User::find(User::factory()->createQuietly($attributes)->id);
    }

    public function test_apply_no_region_filters_provinces_without_region(): void
    {
        $filter = new RegionFilter();
        $request = $this->createNovaRequest();

        $region = $this->createRegion();
        $provinceWithRegion = $this->createProvince(['region_id' => $region->id]);
        $provinceWithoutRegion = $this->createProvince(['region_id' => null]);

        $query = Province::query();
        $result = $filter->apply($request, $query, 'no_region')->get();

        $this->assertTrue($result->contains($provinceWithoutRegion));
        $this->assertFalse($result->contains($provinceWithRegion));
    }

    public function test_apply_specific_region_filters_provinces_by_region(): void
    {
        $filter = new RegionFilter();
        $request = $this->createNovaRequest();

        $regionOne = $this->createRegion();
        $regionTwo = $this->createRegion();

        $provinceOne = $this->createProvince(['region_id' => $regionOne->id]);
        $provinceTwo = $this->createProvince(['region_id' => $regionTwo->id]);

        $query = Province::query();
        $result = $filter->apply($request, $query, $regionOne->id)->get();

        $this->assertTrue($result->contains($provinceOne));
        $this->assertFalse($result->contains($provinceTwo));
    }

    public function test_apply_no_region_filters_users_without_region(): void
    {
        $filter = new RegionFilter();
        $request = $this->createNovaRequest();

        $region = $this->createRegion();
        $userWithRegion = $this->createUser(['region_id' => $region->id]);
        $userWithoutRegion = $this->createUser(['region_id' => null]);

        $query = User::query();
        $result = $filter->apply($request, $query, 'no_region')->get();

        $this->assertTrue($result->contains($userWithoutRegion));
        $this->assertFalse($result->contains($userWithRegion));
    }

    public function test_apply_specific_region_filters_users_by_region(): void
    {
        $filter = new RegionFilter();
        $request = $this->createNovaRequest();

        $regionOne = $this->createRegion();
        $regionTwo = $this->createRegion();

        $userOne = $this->createUser(['region_id' => $regionOne->id]);
        $userTwo = $this->createUser(['region_id' => $regionTwo->id]);

        $query = User::query();
        $result = $filter->apply($request, $query, $regionOne->id)->get();

        $this->assertTrue($result->contains($userOne));
        $this->assertFalse($result->contains($userTwo));
    }

    public function test_apply_no_region_filters_hiking_routes_without_regions(): void
    {
        $filter = new RegionFilter();
        $request = $this->createNovaRequest();

        $region = $this->createRegion();
        $hikingRouteWithRegion = $this->createHikingRoute();
        $hikingRouteWithoutRegion = $this->createHikingRoute();

        $hikingRouteWithRegion->regions()->attach($region->id);

        $query = HikingRoute::query();
        $result = $filter->apply($request, $query, 'no_region')->get();

        $this->assertTrue($result->contains($hikingRouteWithoutRegion));
        $this->assertFalse($result->contains($hikingRouteWithRegion));
    }

    public function test_apply_specific_region_filters_hiking_routes_by_region(): void
    {
        $filter = new RegionFilter();
        $request = $this->createNovaRequest();

        $regionOne = $this->createRegion();
        $regionTwo = $this->createRegion();

        $hikingRouteOne = $this->createHikingRoute();
        $hikingRouteTwo = $this->createHikingRoute();
        $hikingRouteWithoutRegion = $this->createHikingRoute();

        $hikingRouteOne->regions()->attach($regionOne->id);
        $hikingRouteTwo->regions()->attach($regionTwo->id);

        $query = HikingRoute::query();
        $result = $filter->apply($request, $query, $regionOne->id)->get();

        $this->assertTrue($result->contains($hikingRouteOne));
        $this->assertFalse($result->contains($hikingRouteTwo));
        $this->assertFalse($result->contains($hikingRouteWithoutRegion));
    }

    public function test_options_includes_no_region_and_all_regions(): void
    {
        $filter = new RegionFilter();
        $request = $this->createNovaRequest();

        /** @var User $user */
        $user = $this->createUser();
        $this->actingAs($user);

        $regionOne = $this->createRegion(['name' => 'AAA Region']);
        $regionTwo = $this->createRegion(['name' => 'BBB Region']);

        $options = $filter->options($request);

        $this->assertArrayHasKey(__('Senza regione'), $options);
        $this->assertSame('no_region', $options[__('Senza regione')]);
        $this->assertArrayHasKey($regionOne->name, $options);
        $this->assertArrayHasKey($regionTwo->name, $options);
        $this->assertEquals(__('Senza regione'), array_key_first($options));
    }
}
