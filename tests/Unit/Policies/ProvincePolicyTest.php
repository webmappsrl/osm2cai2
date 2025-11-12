<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\Region;
use App\Policies\ProvincePolicy;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProvincePolicyTest extends TestCase
{
    use DatabaseTransactions;

    private ProvincePolicy $policy;
    private Region $region;

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

        // Crea una regione per i test
        $this->region = Region::factory()->createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->policy = new ProvincePolicy;
    }

    /** @test */
    public function administrator_can_view_any_provinces()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function national_referent_can_view_any_provinces()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::NationalReferent);

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function regional_referent_can_view_any_provinces()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::RegionalReferent);

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function local_referent_cannot_view_any_provinces()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::LocalReferent);

        $this->assertFalse($this->policy->viewAny($user));
    }

    /** @test */
    public function guest_cannot_view_any_provinces()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Guest);

        $this->assertFalse($this->policy->viewAny($user));
    }

    /** @test */
    public function user_without_roles_cannot_view_any_provinces()
    {
        $user = UserFactory::new()->createQuietly();

        $this->assertFalse($this->policy->viewAny($user));
    }

    /** @test */
    public function administrator_can_view_province()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);
        $province = Province::factory()->createQuietly([
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertTrue($this->policy->view($user, $province));
    }

    /** @test */
    public function national_referent_can_view_province()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::NationalReferent);
        $province = Province::factory()->createQuietly([
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertTrue($this->policy->view($user, $province));
    }

    /** @test */
    public function regional_referent_can_view_province()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::RegionalReferent);
        $province = Province::factory()->createQuietly([
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertTrue($this->policy->view($user, $province));
    }

    /** @test */
    public function local_referent_can_view_their_own_province()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::LocalReferent);
        $province = Province::factory()->createQuietly([
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        // Assegna la provincia all'utente
        $user->provinces()->attach($province->id);

        $this->assertTrue($this->policy->view($user, $province));
    }

    /** @test */
    public function local_referent_cannot_view_province_not_assigned()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::LocalReferent);
        $province = Province::factory()->createQuietly([
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        // Non assegna la provincia all'utente

        $this->assertFalse($this->policy->view($user, $province));
    }

    /** @test */
    public function no_user_can_create_province()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);

        $this->assertFalse($this->policy->create($user));
    }

    /** @test */
    public function no_user_can_update_province()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);
        $province = Province::factory()->createQuietly([
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertFalse($this->policy->update($user, $province));
    }

    /** @test */
    public function no_user_can_delete_province()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);
        $province = Province::factory()->createQuietly([
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertFalse($this->policy->delete($user, $province));
    }
}

