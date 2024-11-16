<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\User;
use App\Models\Sector;
use App\Models\Province;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SyncUsersFromLegacyOsm2cai extends Command
{
    protected $signature = 'osm2cai2:sync-users';
    protected $description = 'Sync users from legacy OSM2CAI and handle roles and permissions';

    public function handle()
    {
        $legacyUsers = DB::connection('legacyosm2cai')->table('users')->get();

        foreach ($legacyUsers as $legacyUser) {
            $this->info("Importing user: " . $legacyUser->email);

            $user = User::updateOrCreate(
                ['email' => $legacyUser->email],
                [
                    'name' => $legacyUser->name,
                    'email_verified_at' => $legacyUser->email_verified_at,
                    'password' => $legacyUser->password,
                    'remember_token' => $legacyUser->remember_token,
                    'created_at' => $legacyUser->created_at,
                    'updated_at' => now(),
                ]
            );

            //populate the roles and permissions table 
            Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

            $this->assignRolesAndPermissions($user, $legacyUser);
            $this->syncTerritorialRelations($user, $legacyUser);
        }

        $this->info('Import completato.');
    }

    private function syncTerritorialRelations($user, $legacyUser)
    {
        // Sincronizza le province
        $provinceIds = DB::connection('legacyosm2cai')
            ->table('province_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('province_id');

        $provinceNames = DB::connection('legacyosm2cai')
            ->table('provinces')
            ->whereIn('id', $provinceIds)
            ->pluck('name');

        if ($provinceNames->isNotEmpty()) {
            $provinces = Province::whereIn('name', $provinceNames)->get();
            $user->provinces()->sync($provinces->pluck('name'));
            //remove role guest
            $user->removeRole('Guest');
            $user->assignRole('Local Referent');
        }

        // Sincronizza le aree
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
            $user->areas()->sync($areas->pluck('name')); // Sincronizza con i nomi delle aree
            $user->removeRole('Guest');
            $user->assignRole('Local Referent');
        }

        // Sincronizza i settori
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
            $user->sectors()->sync($sectors->pluck('name')); // Sincronizza con i nomi dei settori
            $user->removeRole('Guest');
            $user->assignRole('Local Referent');
        }
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

        // Recupera la regione e sezione dal database legacy
        $regionName = DB::connection('legacyosm2cai')
            ->table('regions')
            ->where('id', $legacyUser->region_id)
            ->value('name');

        $sectionCode = DB::connection('legacyosm2cai')
            ->table('sections')
            ->where('id', $legacyUser->section_id)
            ->value('cai_code');

        if ($regionName) {
            $user->region_name = $regionName;
            $user->removeRole('Guest');
            $user->assignRole('Regional Referent');
        }
        if ($sectionCode) {
            $user->club_cai_code = $sectionCode;
            $user->removeRole('Guest');
            $user->assignRole('Sectional Referent');
        }

        $this->assignResourcesValidationPermissions($legacyUser, $user);
    }

    private function assignResourcesValidationPermissions($legacyUser, $user)
    {
        $legacyResourceValidation = is_string($legacyUser->resources_validator)
            ? json_decode($legacyUser->resources_validator, true)
            : $legacyUser->resources_validator;

        if (!$legacyResourceValidation) {
            return;
        }

        if (isset($legacyResourceValidation['is_sign_validator'])) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate signs');
        }

        if (isset($legacyResourceValidation['is_source_validator'])) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate source surveys');
        }

        if (isset($legacyResourceValidation['is_geological_site_validator'])) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate geological sites');
        }

        if (isset($legacyResourceValidation['is_archaeological_site_validator'])) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate archaeological sites');
        }

        if (isset($legacyResourceValidation['is_archaeological_area_validator'])) {
            $user->syncRoles(['Validator']);
            $user->givePermissionTo('validate archaeological areas');
        }
    }
}
