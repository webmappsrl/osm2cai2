<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use App\Policies\SectorPolicy;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SectorPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private SectorPolicy $policy;

    private Region $region;

    private Province $province;

    private Area $area;

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

        // Crea la gerarchia: Region -> Province -> Area -> Sector
        $this->region = Region::factory()->createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->province = Province::factory()->createQuietly([
            'region_id' => $this->region->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->area = Area::factory()->createQuietly([
            'province_id' => $this->province->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->policy = new SectorPolicy;
    }

    /** @test */
    public function anyone_can_view_any_sectors()
    {
        $user = UserFactory::new()->createQuietly();

        $this->assertTrue($this->policy->viewAny($user));
    }

    /** @test */
    public function anyone_can_view_sector()
    {
        $user = UserFactory::new()->createQuietly();
        $sector = Sector::factory()->createQuietly([
            'area_id' => $this->area->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertTrue($this->policy->view($user, $sector));
    }

    /** @test */
    public function administrator_can_create_sector()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);

        $this->assertTrue($this->policy->create($user));
    }

    /** @test */
    public function national_referent_cannot_create_sector()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::NationalReferent);

        $this->assertFalse($this->policy->create($user));
    }

    /** @test */
    public function regional_referent_cannot_create_sector()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::RegionalReferent);

        $this->assertFalse($this->policy->create($user));
    }

    /** @test */
    public function guest_cannot_create_sector()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Guest);

        $this->assertFalse($this->policy->create($user));
    }

    /** @test */
    public function administrator_can_update_sector()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);
        $sector = Sector::factory()->createQuietly([
            'area_id' => $this->area->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertTrue($this->policy->update($user, $sector));
    }

    /** @test */
    public function national_referent_can_update_sector()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::NationalReferent);
        $sector = Sector::factory()->createQuietly([
            'area_id' => $this->area->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertTrue($this->policy->update($user, $sector));
    }

    /** @test */
    public function regional_referent_can_update_sector_in_their_region()
    {
        $user = UserFactory::new()->createQuietly([
            'region_id' => $this->region->id,
        ]);
        $user->assignRole(UserRole::RegionalReferent);
        $sector = Sector::factory()->createQuietly([
            'area_id' => $this->area->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        // Ricarica le relazioni per assicurarsi che area->province->region_id sia disponibile
        $sector->load('area.province');

        $this->assertTrue($this->policy->update($user, $sector));
    }

    /** @test */
    public function regional_referent_cannot_update_sector_in_different_region()
    {
        $otherRegion = Region::factory()->createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((10 10, 10 11, 11 11, 11 10, 10 10))')"),
        ]);

        $user = UserFactory::new()->createQuietly([
            'region_id' => $otherRegion->id,
        ]);
        $user->assignRole(UserRole::RegionalReferent);
        $sector = Sector::factory()->createQuietly([
            'area_id' => $this->area->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        // Ricarica le relazioni
        $sector->load('area.province');

        $this->assertFalse($this->policy->update($user, $sector));
    }

    /** @test */
    public function local_referent_cannot_update_sector()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::LocalReferent);
        $sector = Sector::factory()->createQuietly([
            'area_id' => $this->area->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertFalse($this->policy->update($user, $sector));
    }

    /** @test */
    public function no_user_can_delete_sector()
    {
        $user = UserFactory::new()->createQuietly();
        $user->assignRole(UserRole::Administrator);
        $sector = Sector::factory()->createQuietly([
            'area_id' => $this->area->id,
            'geometry' => DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))')"),
        ]);

        $this->assertFalse($this->policy->delete($user, $sector));
    }
}
