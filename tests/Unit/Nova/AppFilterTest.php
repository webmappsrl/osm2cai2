<?php

namespace Tests\Unit\Nova;

use App\Models\UgcPoi;
use App\Models\UgcTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;
use Wm\WmPackage\Models\App as AppModel;
use Wm\WmPackage\Nova\Filters\AppFilter;

class AppFilterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
    }

    public function test_apply_filters_query_by_app_id_when_model_has_app_id_column(): void
    {
        $filter = new AppFilter;
        $user = User::factory()->create();
        $app = AppModel::factory()->create(['user_id' => $user->id]);
        $otherApp = AppModel::factory()->create(['user_id' => $user->id]);

        $geojson1 = json_encode([
            'type' => 'Point',
            'coordinates' => [11.0, 45.0, 100.0],
        ]);
        $geojson2 = json_encode([
            'type' => 'Point',
            'coordinates' => [12.0, 46.0, 200.0],
        ]);
        $geojson3 = json_encode([
            'type' => 'Point',
            'coordinates' => [13.0, 47.0, 300.0],
        ]);

        $poi1 = UgcPoi::create([
            'app_id' => $app->id,
            'user_id' => $user->id,
            'name' => 'POI 1',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojson1}')"),
            'properties' => [],
        ]);

        $poi2 = UgcPoi::create([
            'app_id' => $app->id,
            'user_id' => $user->id,
            'name' => 'POI 2',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojson2}')"),
            'properties' => [],
        ]);

        $poi3 = UgcPoi::create([
            'app_id' => $otherApp->id,
            'user_id' => $user->id,
            'name' => 'POI 3',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojson3}')"),
            'properties' => [],
        ]);

        $query = UgcPoi::query();
        $request = new \Illuminate\Http\Request;

        $filteredQuery = $filter->apply($request, $query, $app->id);
        $results = $filteredQuery->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $poi1->id));
        $this->assertTrue($results->contains('id', $poi2->id));
        $this->assertFalse($results->contains('id', $poi3->id));
    }

    public function test_apply_filters_query_by_app_id_when_model_has_no_app_id_column(): void
    {
        $filter = new AppFilter;
        $user = User::factory()->create();
        $app = AppModel::factory()->create(['user_id' => $user->id]);
        $otherApp = AppModel::factory()->create(['user_id' => $user->id]);

        $geojson1 = json_encode([
            'type' => 'Point',
            'coordinates' => [11.0, 45.0, 100.0],
        ]);
        $geojson2 = json_encode([
            'type' => 'Point',
            'coordinates' => [12.0, 46.0, 200.0],
        ]);

        UgcPoi::create([
            'app_id' => $app->id,
            'user_id' => $user->id,
            'name' => 'POI 1',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojson1}')"),
            'properties' => [],
        ]);

        UgcTrack::create([
            'app_id' => $otherApp->id,
            'user_id' => $user->id,
            'name' => 'Track for other app',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojson2}')"),
            'properties' => [],
        ]);

        $query = User::query();
        $request = new \Illuminate\Http\Request;

        $filteredQuery = $filter->apply($request, $query, $app->id);
        $results = $filteredQuery->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains('id', $user->id));
    }

    public function test_options_returns_all_apps_for_administrator(): void
    {
        $filter = new AppFilter;

        $app1 = AppModel::factory()->create(['name' => 'App Alpha']);
        $app2 = AppModel::factory()->create(['name' => 'App Beta']);
        $app3 = AppModel::factory()->create(['name' => 'App Gamma']);

        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $request = NovaRequest::create('/nova-api/users/filters');
        $request->setUserResolver(fn () => $admin);

        $options = $filter->options($request);

        $this->assertArrayHasKey('App Alpha', $options);
        $this->assertArrayHasKey('App Beta', $options);
        $this->assertArrayHasKey('App Gamma', $options);
        $this->assertEquals($app1->id, $options['App Alpha']);
        $this->assertEquals($app2->id, $options['App Beta']);
        $this->assertEquals($app3->id, $options['App Gamma']);
    }

    public function test_options_returns_only_user_apps_for_non_administrator(): void
    {
        $filter = new AppFilter;

        $user = User::factory()->create();

        $userApp1 = AppModel::factory()->create([
            'name' => 'User App 1',
            'sku' => 'user-app-1',
            'user_id' => $user->id,
        ]);
        $userApp2 = AppModel::factory()->create([
            'name' => 'User App 2',
            'sku' => 'user-app-2',
            'user_id' => $user->id,
        ]);

        $otherUser = User::factory()->create();
        AppModel::factory()->create([
            'name' => 'Other App',
            'sku' => 'other-app',
            'user_id' => $otherUser->id,
        ]);

        $request = NovaRequest::create('/nova-api/users/filters');
        $request->setUserResolver(fn () => $user);

        $options = $filter->options($request);

        $this->assertArrayHasKey('User App 1', $options);
        $this->assertArrayHasKey('User App 2', $options);
        $this->assertArrayNotHasKey('Other App', $options);
        $this->assertEquals($userApp1->id, $options['User App 1']);
        $this->assertEquals($userApp2->id, $options['User App 2']);
    }

    public function test_options_returns_empty_array_when_non_administrator_has_no_apps(): void
    {
        $filter = new AppFilter;

        $user = User::factory()->create();

        $otherUser = User::factory()->create();
        AppModel::factory()->create([
            'name' => 'Other App',
            'user_id' => $otherUser->id,
        ]);

        $request = NovaRequest::create('/nova-api/users/filters');
        $request->setUserResolver(fn () => $user);

        $options = $filter->options($request);

        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }
}
