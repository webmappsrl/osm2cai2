<?php

namespace Tests\Unit\Actions;

use App\Models\HikingRoute;
use App\Models\User;
use App\Nova\Actions\ManageHikingRouteValidationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Fields\ActionFields;
use Tests\TestCase;

class ManageHikingRouteValidationActionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Eventuali setup aggiuntivi
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

    private function createTestHikingRoute($id, $osm_id, $status)
    {
        return HikingRoute::createQuietly([
            'id' => $id,
            'osm_id' => $osm_id,
            'osm2cai_status' => $status,
            'osmfeatures_data' => [
                'properties' => [
                    'osm2cai_status' => $status,
                ],
            ],
        ]);
    }

    /** @test */
    public function it_validates_a_hiking_route_with_status_3()
    {
        $adminUser = $this->createAdminUser();
        $hikingRoute = $this->createTestHikingRoute(14102, 14102, 3);

        $this->actingAs($adminUser);
        $action = new ManageHikingRouteValidationAction;
        $fields = new ActionFields(collect(), collect());
        $action->handle($fields, collect([$hikingRoute]));

        $hikingRoute->refresh();

        $this->assertEquals(4, $hikingRoute->osm2cai_status);
        $this->assertEquals(4, $hikingRoute->osmfeatures_data['properties']['osm2cai_status']);
        $this->assertNotEquals(3, $hikingRoute->osm2cai_status);
        $this->assertNotEquals(3, $hikingRoute->osmfeatures_data['properties']['osm2cai_status']);
        $this->assertEquals($adminUser->id, $hikingRoute->validator_id);
        $this->assertNotNull($hikingRoute->validation_date);
    }

    /** @test */
    public function it_reverts_a_hiking_route_with_status_4()
    {
        $adminUser = $this->createAdminUser();
        $hikingRoute = $this->createTestHikingRoute(14102, 14102, 4);

        $this->actingAs($adminUser);

        $action = new ManageHikingRouteValidationAction;
        $fields = new ActionFields(collect(), collect());
        $action->handle($fields, collect([$hikingRoute]));

        $hikingRoute->refresh();

        $this->assertEquals(3, $hikingRoute->osm2cai_status);
        $this->assertEquals(3, $hikingRoute->osmfeatures_data['properties']['osm2cai_status']);
        $this->assertNotEquals(4, $hikingRoute->osm2cai_status);
        $this->assertNotEquals(4, $hikingRoute->osmfeatures_data['properties']['osm2cai_status']);
        $this->assertNull($hikingRoute->validator_id);
        $this->assertNull($hikingRoute->validation_date);
    }

    /** @test */
    public function it_returns_correct_confirm_text_based_on_status()
    {
        $hikingRoute = $this->createTestHikingRoute(14102, 14102, 3);

        // Status 3: validate
        $expected = 'Are you sure you want to validate this route?';
        $this->assertStringStartsWith($expected, ManageHikingRouteValidationAction::getValidationConfirmText($hikingRoute));

        // Status 4: revert validation
        $hikingRoute = $this->createTestHikingRoute(14102, 14102, 4);
        $expected = 'Are you sure you want to revert the validation of this route?';
        $this->assertStringStartsWith($expected, ManageHikingRouteValidationAction::getValidationConfirmText($hikingRoute));
    }

    /** @test */
    public function it_returns_correct_button_text_based_on_status()
    {
        $hikingRoute = $this->createTestHikingRoute(14102, 14102, 3);

        // Status 3: validate
        $expected = 'Validate';
        $this->assertEquals($expected, ManageHikingRouteValidationAction::getValidationButtonText($hikingRoute));

        // Status 4: revert validation
        $hikingRoute = $this->createTestHikingRoute(14102, 14102, 4);
        $expected = 'Revert validation';
        $this->assertEquals($expected, ManageHikingRouteValidationAction::getValidationButtonText($hikingRoute));
    }
}
