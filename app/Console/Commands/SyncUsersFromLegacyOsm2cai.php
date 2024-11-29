<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Club;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SyncUsersFromLegacyOsm2cai extends Command
{
    protected $signature = 'osm2cai:sync-users';

    protected $description = 'Sync users from legacy OSM2CAI along with relationships and handle roles and permissions.';

    public function handle()
    {
        $legacyUsers = DB::connection('legacyosm2cai')->table('users')->get();

        //populate the roles and permissions table
        Artisan::call('db:seed');

        foreach ($legacyUsers as $legacyUser) {
            $this->info('Importing user: ' . $legacyUser->email);

            $user = User::updateOrCreate(
                ['email' => $legacyUser->email],
                [
                    'id' => $legacyUser->id,
                    'name' => $legacyUser->name,
                    'email_verified_at' => $legacyUser->email_verified_at,
                    'password' => $legacyUser->password,
                    'remember_token' => $legacyUser->remember_token,
                    'created_at' => $legacyUser->created_at,
                    'updated_at' => now(),
                ]
            );

            $this->assignRolesAndPermissions($user, $legacyUser);
            $this->syncTerritorialRelations($user, $legacyUser);
        }

        $this->info('Import completed.');
    }

    private function syncTerritorialRelations($user, $legacyUser)
    {
        // Sync the provinces
        $provinceIds = DB::connection('legacyosm2cai')
            ->table('province_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('province_id');

        $provinceCodes = DB::connection('legacyosm2cai')
            ->table('provinces')
            ->whereIn('id', $provinceIds)
            ->pluck('code');

        if ($provinceCodes->isNotEmpty()) {
            $provinces = Province::whereIn('osmfeatures_data->properties->osm_tags->short_name', $provinceCodes)
                ->orWhereIn('osmfeatures_data->properties->osm_tags->ref', $provinceCodes)
                ->get();
            if ($provinces->isNotEmpty()) {
                $user->provinces()->sync($provinces->pluck('id'));
                //remove role guest
                $user->removeRole('Guest');
                $user->assignRole('Local Referent');
            }
        }

        // Sync the areas
        $areaIds = DB::connection('legacyosm2cai')
            ->table('area_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('area_id');

        $areaNames = DB::connection('legacyosm2cai')
            ->table('areas')
            ->whereIn('id', $areaIds)
            ->pluck('name');

        if ($areaNames->isNotEmpty()) {
            $areas = Area::whereIn('name', $areaNames)->get();
            $user->areas()->sync($areas->pluck('id'));
            $user->removeRole('Guest');
            $user->assignRole('Local Referent');
        }

        // Sync the sectors
        $sectorIds = DB::connection('legacyosm2cai')
            ->table('sector_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('sector_id');

        $sectorNames = DB::connection('legacyosm2cai')
            ->table('sectors')
            ->whereIn('id', $sectorIds)
            ->pluck('name');

        if ($sectorNames->isNotEmpty()) {
            $sectors = Sector::whereIn('name', $sectorNames)->get();
            $user->sectors()->sync($sectors->pluck('id'));
            $user->removeRole('Guest');
            $user->assignRole('Local Referent');
        }

        //Sync the club
        $clubId = $legacyUser->section_id;

        if ($clubId) {
            //get the section from legacy osm2cai
            $legacySection = DB::connection('legacyosm2cai')
                ->table('sections')
                ->where('id', $clubId)
                ->first();

            if ($legacySection) {
                $club = Club::where('cai_code', $legacySection->cai_code)->first();
                if ($club) {
                    $user->club_id = $club->id;
                }
            }

            //sync the region
            $legacyRegion = DB::connection('legacyosm2cai')
                ->table('regions')
                ->where('id', $legacyUser->region_id)
                ->first();

            if ($legacyRegion) {
                $region = Region::where('osmfeatures_id', $legacyRegion->osmfeatures_id)->first();
                if ($region) {
                    $user->region_id = $region->id;
                    //assign role
                    $user->removeRole('Guest');
                    $user->assignRole('Regional Referent');
                }
            }

            //sync the managed section
            $legacyManagedSection = DB::connection('legacyosm2cai')
                ->table('sections')
                ->where('id', $legacyUser->manager_section_id)
                ->first();

            if ($legacyManagedSection) {
                $managedSection = Club::where('cai_code', $legacyManagedSection->cai_code)->first();
                if ($managedSection) {
                    $user->managed_club_id = $managedSection->id;
                    $user->removeRole('Guest');
                    $user->assignRole('Club Manager');
                }
            }
        }

        $user->save();
    }

    private function assignRolesAndPermissions($user, $legacyUser)
    {
        if ($legacyUser->is_administrator) {
            $user->removeRole('Guest');
            $user->assignRole('Administrator');
        }
        if ($legacyUser->is_national_referent) {
            $user->removeRole('Guest');
            $user->assignRole('National Referent');
        }
        if ($legacyUser->is_itinerary_manager) {
            $user->removeRole('Guest');
            $user->assignRole('Itinerary Manager');
        }

        $this->assignResourcesValidationPermissions($legacyUser, $user);
    }

    private function assignResourcesValidationPermissions($legacyUser, $user)
    {
        $legacyResourceValidation = is_string($legacyUser->resources_validator)
            ? json_decode($legacyUser->resources_validator, true)
            : $legacyUser->resources_validator;

        if (! $legacyResourceValidation) {
            return;
        }

        if (isset($legacyResourceValidation['is_sign_validator']) && $legacyResourceValidation['is_sign_validator'] == true) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate signs');
        }

        if (isset($legacyResourceValidation['is_source_validator']) && $legacyResourceValidation['is_source_validator'] == true) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate source surveys');
        }

        if (isset($legacyResourceValidation['is_geological_site_validator']) && $legacyResourceValidation['is_geological_site_validator'] == true) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate geological sites');
        }

        if (isset($legacyResourceValidation['is_archaeological_site_validator']) && $legacyResourceValidation['is_archaeological_site_validator'] == true) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate archaeological sites');
        }

        if (isset($legacyResourceValidation['is_archaeological_area_validator']) && $legacyResourceValidation['is_archaeological_area_validator'] == true) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate archaeological areas');
        }
    }
}
