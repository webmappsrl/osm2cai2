<?php

namespace Tests\Feature;

use App\Enums\IssuesStatusEnum;
use App\Enums\UserRole;
use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Nova\Actions\CreateIssue;
use App\Nova\HikingRoute as HikingRouteResource;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Nova\Http\Requests\NovaRequest;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Permessi territoriali della Nova action CreateIssue («Segnalazioni Percorribilità»), oc:8945.
 */
class CreateIssueActionTest extends TestCase
{
    use DatabaseTransactions;

    private const DESCRIPTION = 'oc:8945 test issue';

    protected function setUp(): void
    {
        parent::setUp();

        // Il save() di CreateIssue::handle() fa partire HikingRouteObserver e i job verso WMFE
        Queue::fake();
        Bus::fake();

        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }
    }

    /** @test */
    public function administrator_can_run_action_on_any_route()
    {
        $route = $this->createRoute();
        $user = $this->createUser(UserRole::Administrator);

        $this->runAction($user, [$route])->assertOk();

        $this->assertRouteUpdated($route);
    }

    /** @test */
    public function national_referent_can_run_action_on_any_route()
    {
        $route = $this->createRoute();
        $user = $this->createUser(UserRole::NationalReferent);

        $this->runAction($user, [$route])->assertOk();

        $this->assertRouteUpdated($route);
    }

    /** @test */
    public function regional_referent_can_run_action_on_route_of_own_region()
    {
        $region = Region::factory()->createQuietly();
        $route = $this->createRoute(region: $region);
        $user = $this->createUser(UserRole::RegionalReferent, ['region_id' => $region->id]);

        $this->runAction($user, [$route])->assertOk();

        $this->assertRouteUpdated($route);
    }

    /** @test */
    public function regional_referent_cannot_update_route_of_another_region()
    {
        $ownRegion = Region::factory()->createQuietly();
        $route = $this->createRoute(region: Region::factory()->createQuietly());
        $user = $this->createUser(UserRole::RegionalReferent, ['region_id' => $ownRegion->id]);

        $this->assertDanger($this->runAction($user, [$route]));

        $this->assertRouteNotUpdated($route);
    }

    /** @test */
    public function local_referent_can_run_action_on_route_in_own_territory()
    {
        $province = $this->createProvince();
        $route = $this->createRoute(province: $province);
        $user = $this->createLocalReferent($province);

        $this->runAction($user, [$route])->assertOk();

        $this->assertRouteUpdated($route);
    }

    /** @test */
    public function local_referent_cannot_update_route_outside_own_territory()
    {
        $route = $this->createRoute(province: $this->createProvince());
        $user = $this->createLocalReferent($this->createProvince());

        $this->assertDanger($this->runAction($user, [$route]));

        $this->assertRouteNotUpdated($route);
    }

    /** @test */
    public function regional_referent_without_region_cannot_update_route()
    {
        // Un solo percorso: Nova usa detailQuery() e non indexQuery(), che per questo utente va in errore (fuori da oc:8945)
        $route = $this->createRoute(region: Region::factory()->createQuietly());
        $user = $this->createUser(UserRole::RegionalReferent);

        $this->assertDanger($this->runAction($user, [$route]));

        $this->assertRouteNotUpdated($route);
    }

    /** @test */
    public function local_referent_mixed_selection_updates_only_routes_in_own_territory()
    {
        $province = $this->createProvince();
        $inside = $this->createRoute(province: $province);
        $outside = $this->createRoute(province: $this->createProvince());
        $user = $this->createLocalReferent($province);

        $response = $this->runAction($user, [$inside, $outside])->assertOk();

        $this->assertSame(
            __('Issues updated on :updated hiking routes, :skipped skipped because outside your territory', ['updated' => 1, 'skipped' => 1]),
            $response->json('message')
        );
        $this->assertRouteUpdated($inside);
        $this->assertRouteNotUpdated($outside);
    }

    /** @test */
    public function mixed_selection_larger_than_one_chunk_reports_totals_of_all_chunks()
    {
        // Nova chiama handle() per blocchi di $chunkCount percorsi: con blocchi da 2 i 4 percorsi
        // finiscono in due blocchi, e l'ultimo contiene solo percorsi fuori territorio
        $originalChunkCount = CreateIssue::$chunkCount;
        CreateIssue::$chunkCount = 2;

        try {
            $province = $this->createProvince();
            $otherProvince = $this->createProvince();
            $inside = [$this->createRoute(province: $province), $this->createRoute(province: $province)];
            $outside = [$this->createRoute(province: $otherProvince), $this->createRoute(province: $otherProvince)];
            $user = $this->createLocalReferent($province);

            $response = $this->runAction($user, [...$inside, ...$outside])->assertOk();

            $this->assertSame(
                __('Issues updated on :updated hiking routes, :skipped skipped because outside your territory', ['updated' => 2, 'skipped' => 2]),
                $response->json('message')
            );
            foreach ($inside as $route) {
                $this->assertRouteUpdated($route);
            }
            foreach ($outside as $route) {
                $this->assertRouteNotUpdated($route);
            }
        } finally {
            CreateIssue::$chunkCount = $originalChunkCount;
        }
    }

    /** @test */
    public function user_without_territorial_role_cannot_run_action()
    {
        $route = $this->createRoute();
        $user = $this->createUser(UserRole::Guest);

        // Per chi non ha un ruolo territoriale la action non è disponibile: Nova risponde 403
        $this->runAction($user, [$route])->assertForbidden();

        $this->assertRouteNotUpdated($route);
    }

    /** @test */
    public function detail_view_shows_action_only_on_routes_the_local_referent_can_manage()
    {
        $province = $this->createProvince();
        $inside = $this->createRoute(province: $province);
        $outside = $this->createRoute(province: $this->createProvince());
        $user = $this->createLocalReferent($province);

        $this->assertContains($this->uriKey(), $this->detailActionKeys($user, $inside));
        $this->assertNotContains($this->uriKey(), $this->detailActionKeys($user, $outside));
    }

    /** @test */
    public function index_view_shows_inline_action_only_on_rows_the_local_referent_can_manage()
    {
        $province = $this->createProvince();
        $inside = $this->createRoute(province: $province);
        $outside = $this->createRoute(province: $this->createProvince());
        $user = $this->createLocalReferent($province);

        // La Resource non ha campi di ricerca e l'index del DB di sviluppo contiene migliaia di percorsi:
        // si verifica direttamente il metodo che Nova usa per le action di ogni riga (Resource::serializeForIndex)
        $this->assertContains($this->uriKey(), $this->tableRowActionKeys($user, $inside));
        $this->assertNotContains($this->uriKey(), $this->tableRowActionKeys($user, $outside));
    }

    /** @test */
    public function multiple_selection_menu_shows_action_by_territorial_role()
    {
        $this->assertContains($this->uriKey(), $this->indexActionKeys($this->createLocalReferent($this->createProvince())));
        $this->assertNotContains($this->uriKey(), $this->indexActionKeys($this->createUser(UserRole::Guest)));
    }

    private function uriKey(): string
    {
        return (new CreateIssue)->uriKey();
    }

    private function detailActionKeys(User $user, HikingRoute $route): array
    {
        return collect(
            $this->actingAs($user)
                ->getJson('/nova-api/hiking-routes/actions?display=detail&resources[]='.$route->id)
                ->assertOk()
                ->json('actions')
        )->pluck('uriKey')->all();
    }

    private function tableRowActionKeys(User $user, HikingRoute $route): array
    {
        $this->actingAs($user);

        $request = NovaRequest::create('/nova-api/hiking-routes');
        $request->setUserResolver(fn () => $user);

        return (new HikingRouteResource($route))
            ->availableActionsOnTableRow($request)
            ->map(fn ($action) => $action->uriKey())
            ->all();
    }

    private function indexActionKeys(User $user): array
    {
        return collect(
            $this->actingAs($user)->getJson('/nova-api/hiking-routes/actions?display=index')->assertOk()->json('actions')
        )->pluck('uriKey')->all();
    }

    private function runAction(User $user, array $routes): TestResponse
    {
        // ActionRequest legge `resources` con Arr::wrap(): serve un array di id, non una stringa separata da virgole
        $ids = collect($routes)->pluck('id')->all();

        return $this->actingAs($user)->postJson(
            '/nova-api/hiking-routes/action?action='.(new CreateIssue)->uriKey(),
            [
                'resources' => $ids,
                'issues_status' => IssuesStatusEnum::Closed->value,
                'issues_description' => self::DESCRIPTION,
            ]
        );
    }

    private function createRoute(?Region $region = null, ?Province $province = null): HikingRoute
    {
        $route = HikingRoute::createQuietly([
            'app_id' => HikingRoute::OSM2CAI_APP_ID,
            'geometry' => DB::raw("ST_Force3DZ(ST_GeomFromText('LINESTRING(10 10, 20 20)', 4326))"),
        ]);

        if ($region) {
            $route->regions()->attach($region->id);
        }

        if ($province) {
            $route->provinces()->attach($province->id);
        }

        return $route;
    }

    private function createProvince(): Province
    {
        return Province::factory()->createQuietly(['region_id' => Region::factory()->createQuietly()->id]);
    }

    private function createUser(UserRole $role, array $attributes = []): User
    {
        $user = UserFactory::new()->createQuietly($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function createLocalReferent(Province $province): User
    {
        $user = $this->createUser(UserRole::LocalReferent);
        $user->provinces()->attach($province->id);

        return $user;
    }

    private function assertDanger(TestResponse $response): void
    {
        // Il messaggio deve essere quello di CreateIssue::handle(), non il rifiuto generico di Nova
        $response->assertOk();
        $this->assertSame(
            __('You are not authorized to report issues on the selected hiking routes'),
            $response->json('danger')
        );
    }

    private function assertRouteUpdated(HikingRoute $route): void
    {
        $this->assertSame(self::DESCRIPTION, $route->fresh()->issues_description);
    }

    private function assertRouteNotUpdated(HikingRoute $route): void
    {
        $this->assertNotSame(self::DESCRIPTION, $route->fresh()->issues_description);
    }
}
