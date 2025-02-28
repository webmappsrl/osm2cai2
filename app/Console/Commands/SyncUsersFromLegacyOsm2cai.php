<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Club;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class SyncUsersFromLegacyOsm2cai extends Command
{
    protected $signature = 'osm2cai:sync-users';

    protected $description = 'Sync users from legacy OSM2CAI along with relationships and handle roles and permissions.';

    protected $legacyDbConnection;

    // Cache per risultati di query frequenti
    protected $legacyCache = [];

    protected $modelCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->legacyDbConnection = DB::connection('legacyosm2cai');
    }

    public function handle()
    {
        // Prepopolare le cache per ridurre le query
        $this->preloadCaches();

        // Assicuriamoci che i ruoli esistano
        if (Role::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        }

        // Utilizziamo il metodo chunk per elaborare gli utenti in batch
        $this->legacyDbConnection->table('users')->orderBy('id')->chunk(100, function ($legacyUsers) {
            // Utilizziamo le transazioni per migliorare le prestazioni
            DB::beginTransaction();

            try {
                foreach ($legacyUsers as $legacyUser) {
                    $this->info('Importing user: '.$legacyUser->email);

                    try {
                        $this->syncUser($legacyUser);
                    } catch (\Exception $e) {
                        $this->error('Error importing user: '.$legacyUser->email);
                        $this->error($e->getMessage());
                        continue;
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error('Error during transaction: '.$e->getMessage());
            }
        });

        $this->info('Import completed.');
    }

    /**
     * Precarica dati frequentemente utilizzati nelle cache
     */
    private function preloadCaches()
    {
        // Precarica i dati dei modelli
        $this->modelCache['clubs'] = Club::all()->keyBy('cai_code');
        $this->modelCache['regions'] = Region::all()->keyBy('osmfeatures_id');
        $this->modelCache['provinces'] = Province::all();
        $this->modelCache['areas'] = Area::all()->keyBy('name');
        $this->modelCache['sectors'] = Sector::all()->keyBy('name');

        // Precarica i dati dal database legacy
        $this->legacyCache['sections'] = $this->legacyDbConnection->table('sections')->get()->keyBy('id');
        $this->legacyCache['regions'] = $this->legacyDbConnection->table('regions')->get()->keyBy('id');
    }

    /**
     * Sincronizza un singolo utente
     */
    private function syncUser($legacyUser)
    {
        $user = User::firstOrNew(['email' => $legacyUser->email]);

        $user->id = $legacyUser->id;
        $user->email = $legacyUser->email;
        $user->phone = $legacyUser->phone;
        $user->name = $legacyUser->name;
        $user->email_verified_at = $legacyUser->email_verified_at;
        $user->password = $legacyUser->password;
        $user->remember_token = $legacyUser->remember_token;
        $user->created_at = $legacyUser->created_at;
        $user->updated_at = now();

        $user->saveQuietly();

        $this->assignRolesAndPermissions($user, $legacyUser);
        $this->syncTerritorialRelations($user, $legacyUser);
    }

    private function syncTerritorialRelations($user, $legacyUser)
    {
        $shouldHaveLocalReferent = false;
        $shouldHaveRegionalReferent = false;
        $shouldHaveClubManager = false;

        // Sync the provinces - ottimizzato per ridurre le query
        $provinceIds = $this->legacyDbConnection
            ->table('province_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('province_id')
            ->toArray();

        if (! empty($provinceIds)) {
            // Recupera i codici provincia una sola volta se non sono in cache
            if (! isset($this->legacyCache['provinceCodes_'.implode('_', $provinceIds)])) {
                $this->legacyCache['provinceCodes_'.implode('_', $provinceIds)] = $this->legacyDbConnection
                    ->table('provinces')
                    ->whereIn('id', $provinceIds)
                    ->pluck('code')
                    ->toArray();
            }

            $provinceCodes = $this->legacyCache['provinceCodes_'.implode('_', $provinceIds)];

            if (! empty($provinceCodes)) {
                $provinces = $this->modelCache['provinces']->filter(function ($province) use ($provinceCodes) {
                    $shortName = data_get($province, 'osmfeatures_data.properties.osm_tags.short_name');
                    $ref = data_get($province, 'osmfeatures_data.properties.osm_tags.ref');

                    return in_array($shortName, $provinceCodes) || in_array($ref, $provinceCodes);
                });

                if ($provinces->isNotEmpty()) {
                    $user->provinces()->sync($provinces->pluck('id'));
                    $user->removeRole('Guest');
                    $user->assignRole('Local Referent');
                    $shouldHaveLocalReferent = true;
                } else {
                    $user->provinces()->detach();
                }
            } else {
                $user->provinces()->detach();
            }
        } else {
            $user->provinces()->detach();
        }

        // Sync the areas - ottimizzato
        $areaIds = $this->legacyDbConnection
            ->table('area_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('area_id')
            ->toArray();

        if (! empty($areaIds)) {
            // Recupera i nomi delle aree una sola volta se non sono in cache
            if (! isset($this->legacyCache['areaNames_'.implode('_', $areaIds)])) {
                $this->legacyCache['areaNames_'.implode('_', $areaIds)] = $this->legacyDbConnection
                    ->table('areas')
                    ->whereIn('id', $areaIds)
                    ->pluck('name')
                    ->toArray();
            }

            $areaNames = $this->legacyCache['areaNames_'.implode('_', $areaIds)];

            if (! empty($areaNames)) {
                $areas = $this->modelCache['areas']->only($areaNames);
                if ($areas->isNotEmpty()) {
                    $user->areas()->sync($areas->pluck('id'));
                    $user->removeRole('Guest');
                    $user->assignRole('Local Referent');
                    $shouldHaveLocalReferent = true;
                } else {
                    $user->areas()->detach();
                }
            } else {
                $user->areas()->detach();
            }
        } else {
            $user->areas()->detach();
        }

        // Sync the sectors - ottimizzato
        $sectorIds = $this->legacyDbConnection
            ->table('sector_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('sector_id')
            ->toArray();

        if (! empty($sectorIds)) {
            // Recupera i nomi dei settori una sola volta se non sono in cache
            if (! isset($this->legacyCache['sectorNames_'.implode('_', $sectorIds)])) {
                $this->legacyCache['sectorNames_'.implode('_', $sectorIds)] = $this->legacyDbConnection
                    ->table('sectors')
                    ->whereIn('id', $sectorIds)
                    ->pluck('name')
                    ->toArray();
            }

            $sectorNames = $this->legacyCache['sectorNames_'.implode('_', $sectorIds)];

            if (! empty($sectorNames)) {
                $sectors = $this->modelCache['sectors']->only($sectorNames);
                if ($sectors->isNotEmpty()) {
                    $user->sectors()->sync($sectors->pluck('id'));
                    $user->removeRole('Guest');
                    $user->assignRole('Local Referent');
                    $shouldHaveLocalReferent = true;
                } else {
                    $user->sectors()->detach();
                }
            } else {
                $user->sectors()->detach();
            }
        } else {
            $user->sectors()->detach();
        }

        // Sync the club - ottimizzato
        $clubId = $legacyUser->section_id;
        $user->club_id = null;

        if ($clubId && isset($this->legacyCache['sections'][$clubId])) {
            $legacySection = $this->legacyCache['sections'][$clubId];
            if ($legacySection && isset($this->modelCache['clubs'][$legacySection->cai_code])) {
                $user->club_id = $this->modelCache['clubs'][$legacySection->cai_code]->id;
            }
        }

        // Sync the region - ottimizzato
        $user->region_id = null;
        $regionId = $legacyUser->region_id;

        if ($regionId && isset($this->legacyCache['regions'][$regionId])) {
            $legacyRegion = $this->legacyCache['regions'][$regionId];
            if ($legacyRegion && isset($this->modelCache['regions'][$legacyRegion->osmfeatures_id])) {
                $user->region_id = $this->modelCache['regions'][$legacyRegion->osmfeatures_id]->id;
                $user->removeRole('Guest');
                $user->assignRole('Regional Referent');
                $shouldHaveRegionalReferent = true;
            }
        }

        // Sync the managed section - ottimizzato
        $user->managed_club_id = null;
        $managedSectionId = $legacyUser->manager_section_id;

        if ($managedSectionId && isset($this->legacyCache['sections'][$managedSectionId])) {
            $legacyManagedSection = $this->legacyCache['sections'][$managedSectionId];
            if ($legacyManagedSection && isset($this->modelCache['clubs'][$legacyManagedSection->cai_code])) {
                $user->managed_club_id = $this->modelCache['clubs'][$legacyManagedSection->cai_code]->id;
                $user->removeRole('Guest');
                $user->assignRole('Club Manager');
                $shouldHaveClubManager = true;
            }
        }

        // Remove roles if conditions are not met
        if (! $shouldHaveLocalReferent && $user->hasRole('Local Referent')) {
            $user->removeRole('Local Referent');
        }

        if (! $shouldHaveRegionalReferent && $user->hasRole('Regional Referent')) {
            $user->removeRole('Regional Referent');
        }

        if (! $shouldHaveClubManager && $user->hasRole('Club Manager')) {
            $user->removeRole('Club Manager');
        }

        // If no role is assigned and the user is not Administrator or National Referent or other roles,
        // reassign the Guest role
        if (
            ! $user->hasRole('Administrator') &&
            ! $user->hasRole('National Referent') &&
            ! $user->hasRole('Itinerary Manager') &&
            ! $user->hasRole('Validator') &&
            ! $shouldHaveLocalReferent &&
            ! $shouldHaveRegionalReferent &&
            ! $shouldHaveClubManager
        ) {
            $user->assignRole('Guest');
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

        if (! $user->hasRole('Administrator')) {
            if (isset($legacyResourceValidation['is_signs_validator']) && $legacyResourceValidation['is_signs_validator'] == true) {
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
}
