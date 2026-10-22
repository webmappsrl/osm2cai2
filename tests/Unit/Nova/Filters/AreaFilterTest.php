<?php

namespace Tests\Unit\Nova\Filters;

use App\Models\Area;
use App\Models\HikingRoute;
use App\Models\User;
use App\Nova\Filters\AreaFilter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class AreaFilterTest extends TestCase
{
    use DatabaseTransactions;

    private function createRequest(): Request
    {
        return new Request;
    }

    private function createHikingRoute(): HikingRoute
    {
        return HikingRoute::factory()->createQuietly();
    }

    private function createArea(array $attributes = []): Area
    {
        return Area::factory()->createQuietly($attributes);
    }

    private function createUser(array $attributes = []): User
    {
        return User::find(User::factory()->createQuietly($attributes)->id);
    }

    public function test_apply_no_area_filters_hiking_routes_without_areas(): void
    {
        $filter = new AreaFilter;
        $request = $this->createRequest();

        $hikingRouteWithArea = $this->createHikingRoute();
        $hikingRouteWithoutArea = $this->createHikingRoute();

        $area = $this->createArea();
        $hikingRouteWithArea->areas()->attach($area->id);

        $query = HikingRoute::query();
        $result = $filter->apply($request, $query, 'no_area')->get();

        $this->assertTrue($result->contains($hikingRouteWithoutArea));
        $this->assertFalse($result->contains($hikingRouteWithArea));
    }

    public function test_apply_specific_area_filters_hiking_routes_by_area(): void
    {
        $filter = new AreaFilter;
        $request = $this->createRequest();

        $areaOne = $this->createArea();
        $areaTwo = $this->createArea();

        $hikingRouteWithAreaOne = $this->createHikingRoute();
        $hikingRouteWithAreaTwo = $this->createHikingRoute();
        $hikingRouteWithoutArea = $this->createHikingRoute();

        $hikingRouteWithAreaOne->areas()->attach($areaOne->id);
        $hikingRouteWithAreaTwo->areas()->attach($areaTwo->id);

        $query = HikingRoute::query();
        $result = $filter->apply($request, $query, $areaOne->id)->get();

        $this->assertTrue($result->contains($hikingRouteWithAreaOne));
        $this->assertFalse($result->contains($hikingRouteWithAreaTwo));
        $this->assertFalse($result->contains($hikingRouteWithoutArea));
    }

    public function test_apply_no_area_filters_users_without_areas(): void
    {
        $filter = new AreaFilter;
        $request = $this->createRequest();

        $userWithArea = $this->createUser();
        $userWithoutArea = $this->createUser();

        $area = $this->createArea();
        $userWithArea->areas()->attach($area->id);

        $query = User::query();
        $result = $filter->apply($request, $query, 'no_area')->get();

        $this->assertTrue($result->contains($userWithoutArea));
        $this->assertFalse($result->contains($userWithArea));
    }

    public function test_apply_specific_area_filters_users_by_area(): void
    {
        $filter = new AreaFilter;
        $request = $this->createRequest();

        $areaOne = $this->createArea();
        $areaTwo = $this->createArea();

        $userWithAreaOne = $this->createUser();
        $userWithAreaTwo = $this->createUser();
        $userWithoutArea = $this->createUser();

        $userWithAreaOne->areas()->attach($areaOne->id);
        $userWithAreaTwo->areas()->attach($areaTwo->id);

        $query = User::query();
        $result = $filter->apply($request, $query, $areaOne->id)->get();

        $this->assertTrue($result->contains($userWithAreaOne));
        $this->assertFalse($result->contains($userWithAreaTwo));
        $this->assertFalse($result->contains($userWithoutArea));
    }

    public function test_options_includes_no_area_and_all_areas_for_non_regional_user(): void
    {
        $filter = new AreaFilter;
        $request = $this->createRequest();

        /** @var User $user */
        $user = $this->createUser();
        $this->actingAs($user);

        $areaOne = $this->createArea(['name' => 'AAA Area']);
        $areaTwo = $this->createArea(['name' => 'BBB Area']);

        $options = $filter->options($request);

        $this->assertArrayHasKey(__('Senza area'), $options);
        $this->assertSame('no_area', $options[__('Senza area')]);

        $this->assertArrayHasKey($areaOne->name, $options);
        $this->assertArrayHasKey($areaTwo->name, $options);

        $this->assertEquals(__('Senza area'), array_key_first($options));
    }
}
