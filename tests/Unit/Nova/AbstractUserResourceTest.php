<?php

namespace Tests\Unit\Nova;

use App\Models\UgcPoi;
use App\Models\UgcTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Nova;
use Tests\TestCase;
use Wm\WmPackage\Models\App as AppModel;
use Wm\WmPackage\Nova\AbstractUserResource;

class AbstractUserResourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_apps_returns_unique_app_ids_from_user_ugc_relations(): void
    {
        $user = $this->createAppUser();

        $firstApp = AppModel::factory()->create(['name' => 'App Alpha']);
        $secondApp = AppModel::factory()->create(['name' => 'App Beta']);

        $geojsonPoi1 = json_encode([
            'type' => 'Point',
            'coordinates' => [11.0, 45.0, 100.0],
        ]);
        $geojsonPoi2 = json_encode([
            'type' => 'Point',
            'coordinates' => [12.0, 46.0, 200.0],
        ]);
        $geojsonTrack = json_encode([
            'type' => 'LineString',
            'coordinates' => [[11.0, 45.0], [12.0, 46.0]],
        ]);

        UgcPoi::create([
            'app_id' => $firstApp->id,
            'user_id' => $user->id,
            'name' => 'POI for App Alpha',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojsonPoi1}')"),
            'properties' => [],
        ]);

        UgcPoi::create([
            'app_id' => $firstApp->id,
            'user_id' => $user->id,
            'name' => 'POI 2 for App Alpha',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojsonPoi2}')"),
            'properties' => [],
        ]);

        UgcTrack::create([
            'app_id' => $secondApp->id,
            'user_id' => $user->id,
            'name' => 'Track for App Beta',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojsonTrack}')"),
            'properties' => [],
        ]);

        $user->load(['ugcPois.app', 'ugcTracks.app']);
        $resource = $this->makeResource($user);

        $appIds = $resource->exposeGetApps();

        $this->assertCount(2, $appIds);
        $this->assertContains($firstApp->id, $appIds);
        $this->assertContains($secondApp->id, $appIds);
    }

    public function test_get_app_field_for_index_is_hidden_when_only_one_app_exists(): void
    {
        $user = $this->createAppUser();

        $singleApp = AppModel::factory()->create(['name' => 'Single App']);

        $geojson = json_encode([
            'type' => 'Point',
            'coordinates' => [11.0, 45.0, 100.0],
        ]);

        UgcPoi::create([
            'app_id' => $singleApp->id,
            'user_id' => $user->id,
            'name' => 'POI for Single App',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojson}')"),
            'properties' => [],
        ]);

        $user->load(['ugcPois.app', 'ugcTracks.app']);
        $resource = $this->makeResource($user);

        $appCount = AppModel::count();

        if ($appCount <= 1) {
            $this->assertNull($resource->exposeAppField(), 'Il campo App dovrebbe essere null quando ci sono <= 1 app');
        } else {
            $this->markTestSkipped("Test richiede database con massimo 1 app (attualmente: {$appCount})");
        }
    }

    public function test_get_app_field_for_index_returns_clickable_links_when_multiple_apps_exist(): void
    {
        $user = $this->createAppUser();

        $firstApp = AppModel::factory()->create(['name' => 'App Alpha']);
        $secondApp = AppModel::factory()->create(['name' => 'App Beta']);

        $geojson1 = json_encode([
            'type' => 'Point',
            'coordinates' => [11.0, 45.0, 100.0],
        ]);
        $geojson2 = json_encode([
            'type' => 'Point',
            'coordinates' => [12.0, 46.0, 200.0],
        ]);

        UgcPoi::create([
            'app_id' => $firstApp->id,
            'user_id' => $user->id,
            'name' => 'POI for App Alpha',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojson1}')"),
            'properties' => [],
        ]);

        UgcPoi::create([
            'app_id' => $secondApp->id,
            'user_id' => $user->id,
            'name' => 'POI for App Beta',
            'geometry' => DB::raw("ST_GeomFromGeoJSON('{$geojson2}')"),
            'properties' => [],
        ]);

        $user->load(['ugcPois.app', 'ugcTracks.app']);
        $resource = $this->makeResource($user);

        $field = $resource->exposeAppField();

        $this->assertInstanceOf(Text::class, $field);
        $this->assertTrue($field->asHtml);

        $html = $resource->exposeAppFieldValue();

        $this->assertNotNull($html);
        $this->assertStringContainsString($firstApp->name, $html);
        $this->assertStringContainsString($secondApp->name, $html);
        $this->assertStringContainsString(Nova::url('/resources/apps/'.$firstApp->id), $html);
        $this->assertStringContainsString(Nova::url('/resources/apps/'.$secondApp->id), $html);
    }

    private function makeResource(User $user): TestableUserResource
    {
        return new TestableUserResource($user);
    }

    private function createAppUser(): User
    {
        return User::factory()->create();
    }
}

class TestableUserResource extends AbstractUserResource
{
    /**
     * @var class-string<User>
     */
    public static $model = User::class;

    public function exposeGetApps(): array
    {
        return $this->getApps();
    }

    public function exposeAppField(): ?Text
    {
        return $this->getAppFieldForIndex();
    }

    public function exposeAppFieldValue(): ?string
    {
        $field = $this->getAppFieldForIndex();

        if (! $field) {
            return null;
        }

        $field->resolveForDisplay($this);

        return $field->value;
    }
}
