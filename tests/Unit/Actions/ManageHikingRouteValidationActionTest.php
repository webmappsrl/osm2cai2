<?php

namespace Tests\Unit\Actions;

use App\Models\HikingRoute;
use App\Models\User;
use App\Nova\Actions\ManageHikingRouteValidationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Nova\Fields\ActionFields;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageHikingRouteValidationActionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Disabilita Scout completamente per evitare connessioni a Elasticsearch
        config(['scout.driver' => null]);

        // Fake dei job batch per evitare connessioni a Redis
        Bus::fake();

        // Crea i ruoli necessari per i test
        Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);

        // Crea i permessi necessari per i test
        Permission::firstOrCreate(['name' => 'validate signs', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate source surveys', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate geological sites', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate archaeological sites', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate archaeological areas', 'guard_name' => 'web']);

        // Crea la colonna osmfeatures_data se non esiste
        if (! Schema::hasColumn('hiking_routes', 'osmfeatures_data')) {
            DB::statement('ALTER TABLE hiking_routes ADD COLUMN osmfeatures_data jsonb');
        }
    }

    private function createAdminUser()
    {
        $user = User::factory()->createQuietly();
        $user->assignRole('Administrator');
        $user->givePermissionTo('validate signs');
        $user->givePermissionTo('validate source surveys');
        $user->givePermissionTo('validate geological sites');
        $user->givePermissionTo('validate archaeological sites');
        $user->givePermissionTo('validate archaeological areas');
        $user->save();

        return $user;
    }

    private function getOsmfeaturesDataProperty(HikingRoute $hikingRoute, string $key)
    {
        if (isset($hikingRoute->osmfeatures_data['properties'][$key])) {
            return $hikingRoute->osmfeatures_data['properties'][$key];
        }

        // Fallback a properties se osmfeatures_data non ha la proprietà
        return $hikingRoute->properties[$key] ?? null;
    }

    private function createTestHikingRoute($id, $osm_id, $status)
    {
        // Usiamo una query SQL diretta per inserire geometrie 3D corrette
        // che soddisfano il constraint delle colonne geometry con Z dimension
        $osmfeaturesDataJson = json_encode([
            'properties' => [
                'osm_id' => $osm_id,
                'osm2cai_status' => $status,
            ],
        ]);

        DB::statement("
            INSERT INTO hiking_routes (
                id, osm2cai_status, geometry, geometry_raw_data, is_geometry_correct,
                osmfeatures_data, created_at, updated_at
            ) VALUES (
                ?, ?,
                ST_GeomFromText('LINESTRINGZ(10 10 0, 20 20 0)', 4326),
                ST_GeomFromText('LINESTRINGZ(10 10 0, 20 20 0)', 4326),
                ?, ?::jsonb, ?, ?
            )
        ", [
            $id,
            $status,
            true,
            $osmfeaturesDataJson,
            now(),
            now(),
        ]);

        return HikingRoute::find($id);
    }

    /** @test */
    public function it_validates_a_hiking_route_with_status_3()
    {
        $adminUser = $this->createAdminUser();
        $hikingRoute = $this->createTestHikingRoute(999991, 14102, 3);

        $this->actingAs($adminUser);
        $action = new ManageHikingRouteValidationAction;
        $fields = new ActionFields(collect(), collect());
        $action->handle($fields, collect([$hikingRoute]));

        $hikingRoute->refresh();

        $this->assertEquals(4, $hikingRoute->osm2cai_status);
        $this->assertEquals(4, $this->getOsmfeaturesDataProperty($hikingRoute, 'osm2cai_status'));
        $this->assertNotEquals(3, $hikingRoute->osm2cai_status);
        $this->assertNotEquals(3, $this->getOsmfeaturesDataProperty($hikingRoute, 'osm2cai_status'));
        $this->assertEquals($adminUser->id, $hikingRoute->validator_id);
        $this->assertNotNull($hikingRoute->validation_date);
    }

    /** @test */
    public function it_reverts_a_hiking_route_with_status_4()
    {
        $adminUser = $this->createAdminUser();
        $hikingRoute = $this->createTestHikingRoute(999992, 14102, 4);

        $this->actingAs($adminUser);

        $action = new ManageHikingRouteValidationAction;
        $fields = new ActionFields(collect(), collect());
        $action->handle($fields, collect([$hikingRoute]));

        $hikingRoute->refresh();

        $this->assertEquals(3, $hikingRoute->osm2cai_status);
        $this->assertEquals(3, $this->getOsmfeaturesDataProperty($hikingRoute, 'osm2cai_status'));
        $this->assertNotEquals(4, $hikingRoute->osm2cai_status);
        $this->assertNotEquals(4, $this->getOsmfeaturesDataProperty($hikingRoute, 'osm2cai_status'));
        $this->assertNull($hikingRoute->validator_id);
        $this->assertNull($hikingRoute->validation_date);
    }

    /** @test */
    public function it_returns_correct_confirm_text_based_on_status()
    {
        $hikingRoute = $this->createTestHikingRoute(999993, 14102, 3);

        // Status 3: validate - verifica che contenga il testo localizzato in italiano
        $confirmText = ManageHikingRouteValidationAction::getValidationConfirmText($hikingRoute);
        $this->assertStringContainsString('Sei sicuro', $confirmText);
        $this->assertStringContainsString('validare', $confirmText);

        // Status 4: revert validation - verifica che contenga il testo localizzato in italiano
        $hikingRoute = $this->createTestHikingRoute(999994, 14102, 4);
        $confirmText = ManageHikingRouteValidationAction::getValidationConfirmText($hikingRoute);
        $this->assertStringContainsString('Sei sicuro', $confirmText);
        $this->assertStringContainsString('annullare la validazione', $confirmText);
    }

    /** @test */
    public function it_returns_correct_button_text_based_on_status()
    {
        $hikingRoute = $this->createTestHikingRoute(999995, 14102, 3);

        // Status 3: validate - verifica il testo localizzato in italiano
        $buttonText = ManageHikingRouteValidationAction::getValidationButtonText($hikingRoute);
        $this->assertStringContainsString('Valida', $buttonText);

        // Status 4: revert validation - verifica il testo localizzato in italiano
        $hikingRoute = $this->createTestHikingRoute(999996, 14102, 4);
        $buttonText = ManageHikingRouteValidationAction::getValidationButtonText($hikingRoute);
        $this->assertStringContainsString('Annulla', $buttonText);
    }
}
