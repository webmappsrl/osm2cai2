<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Province;
use App\Models\Region;
use App\Policies\AreaPolicy;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AreaPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private AreaPolicy $policy;

    private Region $region;

    private Province $province;

    protected function setUp(): void
    {
        parent::setUp();

        // Crea i ruoli necessari per i test
        Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'National Referent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Regional Referent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Local Referent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Club Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Guest', 'guard_name' => 'web']);

        // Crea una regione e una provincia per i test
        $this->region = Region::factory()->createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->province = Province::factory()->createQuietly([
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->policy = new AreaPolicy;
    }

    /** @test */
    public function administrator_can_view_any_areas()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function national_referent_can_view_any_areas()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::NationalReferent);

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function regional_referent_can_view_any_areas()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::RegionalReferent);

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function local_referent_cannot_view_any_areas()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::LocalReferent);

        $this->assertFalse($this->policy->viewAny($user));
    }

    /** @test */
    public function guest_cannot_view_any_areas()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Guest);

        $this->assertFalse($this->policy->viewAny($user));
    }

    /** @test */
    public function user_without_roles_cannot_view_any_areas()
    {
        $user = UserFactory::new()->createQuietly();

        $this->assertFalse($this->policy->viewAny($user));
    }

    /** @test */
    public function administrator_can_view_area()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);
        $area = Area::factory()->createQuietly([
            'province_id' => $this->province->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertTrue($this->policy->view($user, $area));
    }

    /** @test */
    public function national_referent_can_view_area()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::NationalReferent);
        $area = Area::factory()->createQuietly([
            'province_id' => $this->province->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertTrue($this->policy->view($user, $area));
    }

    /** @test */
    public function regional_referent_can_view_area()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::RegionalReferent);
        $area = Area::factory()->createQuietly([
            'province_id' => $this->province->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertTrue($this->policy->view($user, $area));
    }

    /** @test */
    public function local_referent_cannot_view_area()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::LocalReferent);
        $area = Area::factory()->createQuietly([
            'province_id' => $this->province->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertFalse($this->policy->view($user, $area));
    }

    /** @test */
    public function no_user_can_create_area()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);

        $this->assertFalse($this->policy->create($user));
    }

    /** @test */
    public function no_user_can_update_area()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);
        $area = Area::factory()->createQuietly([
            'province_id' => $this->province->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertFalse($this->policy->update($user, $area));
    }

    /** @test */
    public function no_user_can_delete_area()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);
        $area = Area::factory()->createQuietly([
            'province_id' => $this->province->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertFalse($this->policy->delete($user, $area));
    }
}
