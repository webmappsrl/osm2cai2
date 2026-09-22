<?php

namespace Tests\Unit\Nova\Filters;

use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\User;
use App\Nova\Filters\ProvinceFilter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProvinceFilterTest extends TestCase
{
    use DatabaseTransactions;

    private function createRequest(): Request
    {
        return new Request();
    }

    private function createHikingRoute(): HikingRoute
    {
        return HikingRoute::factory()->createQuietly();
    }

    private function createProvince(array $attributes = []): Province
    {
        return Province::factory()->createQuietly($attributes);
    }

    private function createUser(array $attributes = []): User
    {
        return User::find(User::factory()->createQuietly($attributes)->id);
    }

    public function test_apply_no_province_filters_hiking_routes_without_provinces(): void
    {
        $filter = new ProvinceFilter();
        $request = $this->createRequest();

        $province = $this->createProvince();
        $hikingRouteWithProvince = $this->createHikingRoute();
        $hikingRouteWithoutProvince = $this->createHikingRoute();

        $hikingRouteWithProvince->provinces()->attach($province->id);

        $query = HikingRoute::query();
        $result = $filter->apply($request, $query, 'no_province')->get();

        $this->assertTrue($result->contains($hikingRouteWithoutProvince));
        $this->assertFalse($result->contains($hikingRouteWithProvince));
    }

    public function test_apply_specific_province_filters_hiking_routes_by_province(): void
    {
        $filter = new ProvinceFilter();
        $request = $this->createRequest();

        $provinceOne = $this->createProvince();
        $provinceTwo = $this->createProvince();

        $hikingRouteOne = $this->createHikingRoute();
        $hikingRouteTwo = $this->createHikingRoute();
        $hikingRouteWithoutProvince = $this->createHikingRoute();

        $hikingRouteOne->provinces()->attach($provinceOne->id);
        $hikingRouteTwo->provinces()->attach($provinceTwo->id);

        $query = HikingRoute::query();
        $result = $filter->apply($request, $query, $provinceOne->id)->get();

        $this->assertTrue($result->contains($hikingRouteOne));
        $this->assertFalse($result->contains($hikingRouteTwo));
        $this->assertFalse($result->contains($hikingRouteWithoutProvince));
    }

    public function test_apply_no_province_filters_users_without_provinces(): void
    {
        $filter = new ProvinceFilter();
        $request = $this->createRequest();

        $province = $this->createProvince();
        $userWithProvince = $this->createUser();
        $userWithoutProvince = $this->createUser();

        $userWithProvince->provinces()->attach($province->id);

        $query = User::query();
        $result = $filter->apply($request, $query, 'no_province')->get();

        $this->assertTrue($result->contains($userWithoutProvince));
        $this->assertFalse($result->contains($userWithProvince));
    }

    public function test_apply_specific_province_filters_users_by_province(): void
    {
        $filter = new ProvinceFilter();
        $request = $this->createRequest();

        $provinceOne = $this->createProvince(['name' => 'Province One']);
        $provinceTwo = $this->createProvince(['name' => 'Province Two']);

        $userOne = $this->createUser();
        $userTwo = $this->createUser();
        $userWithoutProvince = $this->createUser();

        $userOne->provinces()->attach($provinceOne->id);
        $userTwo->provinces()->attach($provinceTwo->id);

        $query = User::query();
        $result = $filter->apply($request, $query, $provinceOne->id)->get();

        $this->assertTrue($result->contains($userOne));
        $this->assertFalse($result->contains($userTwo));
        $this->assertFalse($result->contains($userWithoutProvince));
    }

    public function test_options_includes_no_province_and_all_provinces_for_non_regional_user(): void
    {
        $filter = new ProvinceFilter();
        $request = $this->createRequest();

        /** @var User $user */
        $user = $this->createUser();
        $this->actingAs($user);

        $provinceOne = $this->createProvince(['name' => 'AAA Province']);
        $provinceTwo = $this->createProvince(['name' => 'BBB Province']);

        $options = $filter->options($request);

        $this->assertArrayHasKey(__('Senza provincia'), $options);
        $this->assertSame('no_province', $options[__('Senza provincia')]);
        $this->assertArrayHasKey($provinceOne->name, $options);
        $this->assertArrayHasKey($provinceTwo->name, $options);
        $this->assertEquals(__('Senza provincia'), array_key_first($options));
    }
}
