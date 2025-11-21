<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Club;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class SyncUsersFromLegacyOsm2cai extends Command
{
    protected $signature = 'osm2cai:sync-users {--role= : Filter users by role (Local Referent, Club Manager, Regional Referent, etc.)} {--debug : Enable debug mode with additional information}';

    protected $description = 'Sync users from legacy OSM2CAI along with relationships and handle roles and permissions.';

    protected $legacyDbConnection;

    // Cache for frequent query results
    protected $legacyCache = [];

    protected $modelCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->legacyDbConnection = DB::connection('legacyosm2cai');
    }

    public function handle()
    {
        // Preload caches to reduce queries
        $this->preloadCaches();

        // Make sure roles exist
        if (Role::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        }

        $role = $this->option('role');
        $debug = $this->option('debug');

        // Counters for debug
        $userStats = [
            'total' => 0,
            'processed' => 0,
            'roles' => [],
        ];

        // Use chunk method to process users in batches
        $query = $this->legacyDbConnection->table('users')->orderBy('id');

        // Specific filters by role
        if ($role) {
            $this->info("Filtering users by role: $role");

            // Apply filters based on requested role
            switch ($role) {
                case UserRole::LocalReferent->value:
                    // Users with assigned provinces, areas or sectors
                    $usersWithProvinces = $this->legacyDbConnection->table('province_user')
                        ->distinct()->pluck('user_id');
                    $usersWithAreas = $this->legacyDbConnection->table('area_user')
                        ->distinct()->pluck('user_id');
                    $usersWithSectors = $this->legacyDbConnection->table('sector_user')
                        ->distinct()->pluck('user_id');

                    // Union of user IDs with provinces, areas or sectors
                    $userIds = collect($usersWithProvinces)
                        ->merge($usersWithAreas)
                        ->merge($usersWithSectors)
                        ->unique();

                    if ($debug) {
                        $this->info("Found {$usersWithProvinces->count()} users with provinces");
                        $this->info("Found {$usersWithAreas->count()} users with areas");
                        $this->info("Found {$usersWithSectors->count()} users with sectors");
                        $this->info("Total unique Local Referent users in legacy system: {$userIds->count()}");
                    }

                    $query->whereIn('id', $userIds);
                    break;

                case UserRole::ClubManager->value:
                    // Users with manager_section_id set
                    $query->whereNotNull('manager_section_id');

                    if ($debug) {
                        $count = $this->legacyDbConnection->table('users')
                            ->whereNotNull('manager_section_id')->count();
                        $this->info("Found $count Club Manager users in legacy system");
                    }
                    break;

                case UserRole::RegionalReferent->value:
                    // Users with region_id set
                    $query->whereNotNull('region_id');

                    if ($debug) {
                        $count = $this->legacyDbConnection->table('users')
                            ->whereNotNull('region_id')->count();
                        $this->info("Found $count Regional Referent users in legacy system");
                    }
                    break;

                case UserRole::Administrator->value:
                    $query->where('is_administrator', true);

                    if ($debug) {
                        $count = $this->legacyDbConnection->table('users')
                            ->where('is_administrator', true)->count();
                        $this->info("Found $count Administrator users in legacy system");
                    }
                    break;

                case UserRole::NationalReferent->value:
                    $query->where('is_national_referent', true);

                    if ($debug) {
                        $count = $this->legacyDbConnection->table('users')
                            ->where('is_national_referent', true)->count();
                        $this->info("Found $count National Referent users in legacy system");
                    }
                    break;

                case UserRole::Validator->value:
                    // Users with any validation permission
                    $query->where(function ($q) {
                        $q->whereNotNull('resources_validator')
                            ->where('resources_validator', '<>', '');
                    });

                    if ($debug) {
                        $count = $this->legacyDbConnection->table('users')
                            ->whereNotNull('resources_validator')
                            ->where('resources_validator', '<>', '')
                            ->count();
                        $this->info("Found $count Validator users in legacy system");
                    }
                    break;

                default:
                    $this->warn("Unknown role: $role. Will process all users.");
            }
        }

        // Count total users for the operation
        $totalUsers = $query->count();
        $userStats['total'] = $totalUsers;
        $this->info("Processing $totalUsers users");

        if ($debug) {
            // Verifica count utenti unici nelle relazioni territoriali
            $legacyCount = $this->legacyDbConnection->select('
                SELECT COUNT(DISTINCT user_id) AS utenti_unici
                FROM (
                    SELECT user_id FROM area_user
                    UNION ALL
                    SELECT user_id FROM sector_user
                    UNION ALL
                    SELECT user_id FROM province_user
                ) AS combined_users
            ')[0]->utenti_unici;

            $currentCount = DB::select('
                SELECT COUNT(DISTINCT user_id) AS utenti_unici
                FROM (
                    SELECT user_id FROM area_user
                    UNION ALL
                    SELECT user_id FROM sector_user
                    UNION ALL
                    SELECT user_id FROM province_user
                ) AS combined_users
            ')[0]->utenti_unici;

            $this->info("Utenti unici con relazioni territoriali - Legacy: $legacyCount, Attuale: $currentCount");
        }

        $query->chunk(100, function ($legacyUsers) use ($debug, &$userStats) {
            // Use transactions to improve performance
            DB::beginTransaction();

            try {
                foreach ($legacyUsers as $legacyUser) {
                    $this->info('Importing user: '.$legacyUser->email);
                    $userStats['processed']++;

                    try {
                        $before = null;
                        if ($debug) {
                            // Save roles before synchronization for debugging
                            $user = User::where('email', $legacyUser->email)->first();
                            if ($user) {
                                $before = $user->getRoleNames()->toArray();
                            }
                        }

                        $this->syncUser($legacyUser);

                        if ($debug) {
                            // Check roles after synchronization for debugging
                            $user = User::where('email', $legacyUser->email)->first();
                            if ($user) {
                                $after = $user->getRoleNames()->toArray();

                                foreach ($after as $role) {
                                    if (! isset($userStats['roles'][$role])) {
                                        $userStats['roles'][$role] = 0;
                                    }
                                    $userStats['roles'][$role]++;
                                }

                                $this->info("User {$user->email} roles: ".implode(', ', $after));

                                if ($before) {
                                    $added = array_diff($after, $before);
                                    $removed = array_diff($before, $after);

                                    if (! empty($added)) {
                                        $this->info('Roles added: '.implode(', ', $added));
                                    }

                                    if (! empty($removed)) {
                                        $this->info('Roles removed: '.implode(', ', $removed));
                                    }
                                }
                            }
                        }
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

        if ($debug) {
            $this->info("\nSync statistics:");
            $this->info("Total legacy users matching criteria: {$userStats['total']}");
            $this->info("Total users processed: {$userStats['processed']}");
            $this->info("\nRole counts after sync:");

            foreach ($userStats['roles'] as $role => $count) {
                $this->info("- $role: $count users");
            }

            // Current role statistics in the system
            $this->info("\nCurrent role counts in system:");
            $roles = Role::all();
            foreach ($roles as $role) {
                $count = $role->users()->count();
                $this->info("- {$role->name}: $count users");
            }

            // Show specific statistics for Local Referents
            if ($this->option('role') == UserRole::LocalReferent->value || ! $this->option('role')) {
                $this->info("\nLocal Referent Details:");
                $usersWithProvinces = User::has('provinces')->count();
                $usersWithAreas = User::has('areas')->count();
                $usersWithSectors = User::has('sectors')->count();
                $this->info("- Users with provinces: $usersWithProvinces");
                $this->info("- Users with areas: $usersWithAreas");
                $this->info("- Users with sectors: $usersWithSectors");

                $localRefCount = User::role(UserRole::LocalReferent)->count();
                $this->info("- Total with 'Local Referent' role: $localRefCount");
            }
        }

        $this->info('Import completed.');
    }

    /**
     * Preload frequently used data into caches
     */
    private function preloadCaches()
    {
        // Preload model data
        $this->modelCache['clubs'] = Club::all()->keyBy('cai_code');
        $this->modelCache['regions'] = Region::all()->keyBy('osmfeatures_id');
        $this->modelCache['provinces'] = Province::all();
        $this->modelCache['areas'] = Area::all()->keyBy('name');
        $this->modelCache['sectors'] = Sector::all()->keyBy('name');

        // Preload data from legacy database
        $this->legacyCache['sections'] = $this->legacyDbConnection->table('sections')->get()->keyBy('id');
        $this->legacyCache['regions'] = $this->legacyDbConnection->table('regions')->get()->keyBy('id');
    }

    /**
     * Synchronize a single user
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

        // Sync the provinces - optimized to reduce queries
        $provinceIds = $this->legacyDbConnection
            ->table('province_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('province_id')
            ->toArray();

        if (! empty($provinceIds)) {
            // Retrieve province codes only once if not in cache
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
                    $user->removeRole(UserRole::Guest);
                    $user->assignRole(UserRole::LocalReferent);
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

        // Sync the areas
        $areaIds = $this->legacyDbConnection
            ->table('area_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('area_id')
            ->toArray();

        if (! empty($areaIds)) {
            // Retrieve area names only once if not in cache
            if (! isset($this->legacyCache['areaNames_'.implode('_', $areaIds)])) {
                $this->legacyCache['areaNames_'.implode('_', $areaIds)] = $this->legacyDbConnection
                    ->table('areas')
                    ->whereIn('id', $areaIds)
                    ->pluck('name')
                    ->toArray();
            }

            $areaNames = $this->legacyCache['areaNames_'.implode('_', $areaIds)];

            if (! empty($areaNames)) {
                $areas = $this->modelCache['areas']->whereIn('name', $areaNames);
                if ($areas->isNotEmpty()) {
                    $user->areas()->sync($areas->pluck('id'));
                    $user->removeRole(UserRole::Guest);
                    $user->assignRole(UserRole::LocalReferent);
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

        // Sync the sectors - optimized
        $sectorIds = $this->legacyDbConnection
            ->table('sector_user')
            ->where('user_id', $legacyUser->id)
            ->pluck('sector_id')
            ->toArray();

        if (! empty($sectorIds)) {
            // Retrieve sector names only once if not in cache
            if (! isset($this->legacyCache['sectorNames_'.implode('_', $sectorIds)])) {
                $this->legacyCache['sectorNames_'.implode('_', $sectorIds)] = $this->legacyDbConnection
                    ->table('sectors')
                    ->whereIn('id', $sectorIds)
                    ->pluck('name')
                    ->toArray();
            }

            $sectorNames = $this->legacyCache['sectorNames_'.implode('_', $sectorIds)];

            if (! empty($sectorNames)) {
                $sectors = $this->modelCache['sectors']->whereIn('name', $sectorNames);
                if ($sectors->isNotEmpty()) {
                    $user->sectors()->sync($sectors->pluck('id'));
                    $user->removeRole(UserRole::Guest);
                    $user->assignRole(UserRole::LocalReferent);
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

        // Sync the club
        $clubId = $legacyUser->section_id;
        $user->club_id = null;

        if ($clubId && isset($this->legacyCache['sections'][$clubId])) {
            $legacySection = $this->legacyCache['sections'][$clubId];
            if ($legacySection && isset($this->modelCache['clubs'][$legacySection->cai_code])) {
                $user->club_id = $this->modelCache['clubs'][$legacySection->cai_code]->id;
            }
        }

        // Sync the region
        $user->region_id = null;
        $regionId = $legacyUser->region_id;

        if ($regionId && isset($this->legacyCache['regions'][$regionId])) {
            $legacyRegion = $this->legacyCache['regions'][$regionId];
            if ($legacyRegion && isset($this->modelCache['regions'][$legacyRegion->osmfeatures_id])) {
                $user->region_id = $this->modelCache['regions'][$legacyRegion->osmfeatures_id]->id;
                $user->removeRole(UserRole::Guest);
                $user->assignRole(UserRole::RegionalReferent);
                $shouldHaveRegionalReferent = true;
            }
        }

        // Sync the managed section
        $user->managed_club_id = null;
        $managedSectionId = $legacyUser->manager_section_id;

        if ($managedSectionId && isset($this->legacyCache['sections'][$managedSectionId])) {
            $legacyManagedSection = $this->legacyCache['sections'][$managedSectionId];
            if ($legacyManagedSection && isset($this->modelCache['clubs'][$legacyManagedSection->cai_code])) {
                $user->managed_club_id = $this->modelCache['clubs'][$legacyManagedSection->cai_code]->id;
                $user->removeRole(UserRole::Guest);
                $user->assignRole(UserRole::ClubManager);
                $shouldHaveClubManager = true;
            }
        }

        // Remove roles if conditions are not met
        if (! $shouldHaveLocalReferent && $user->hasRole(UserRole::LocalReferent)) {
            $user->removeRole(UserRole::LocalReferent);
        }

        if (! $shouldHaveRegionalReferent && $user->hasRole(UserRole::RegionalReferent)) {
            $user->removeRole(UserRole::RegionalReferent);
        }

        if (! $shouldHaveClubManager && $user->hasRole(UserRole::ClubManager)) {
            $user->removeRole(UserRole::ClubManager);
        }

        // If no role is assigned and the user is not Administrator or National Referent or other roles,
        // reassign the Guest role
        if (
            ! $user->hasRole(UserRole::Administrator) &&
            ! $user->hasRole(UserRole::NationalReferent) &&
            ! $user->hasRole(UserRole::ItineraryManager) &&
            ! $user->hasRole(UserRole::Validator) &&
            ! $shouldHaveLocalReferent &&
            ! $shouldHaveRegionalReferent &&
            ! $shouldHaveClubManager
        ) {
            $user->assignRole(UserRole::Guest);
        }

        $user->save();
    }

    private function assignRolesAndPermissions($user, $legacyUser)
    {
        if ($legacyUser->is_administrator) {
            $user->removeRole(UserRole::Guest);
            $user->assignRole(UserRole::Administrator);
        }
        if ($legacyUser->is_national_referent) {
            $user->removeRole(UserRole::Guest);
            $user->assignRole(UserRole::NationalReferent);
        }
        if ($legacyUser->is_itinerary_manager) {
            $user->removeRole(UserRole::Guest);
            $user->assignRole(UserRole::ItineraryManager);
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

        if (! $user->hasRole(UserRole::Administrator)) {
            if (isset($legacyResourceValidation['is_signs_validator']) && $legacyResourceValidation['is_signs_validator'] == true) {
                $user->syncRoles([UserRole::Validator]);
                $user->givePermissionTo('validate signs');
            }

            if (isset($legacyResourceValidation['is_source_validator']) && $legacyResourceValidation['is_source_validator'] == true) {
                $user->syncRoles([UserRole::Validator]);
                $user->givePermissionTo('validate source surveys');
            }

            if (isset($legacyResourceValidation['is_geological_site_validator']) && $legacyResourceValidation['is_geological_site_validator'] == true) {
                $user->syncRoles([UserRole::Validator]);
                $user->givePermissionTo('validate geological sites');
            }

            if (isset($legacyResourceValidation['is_archaeological_site_validator']) && $legacyResourceValidation['is_archaeological_site_validator'] == true) {
                $user->syncRoles([UserRole::Validator]);
                $user->givePermissionTo('validate archaeological sites');
            }

            if (isset($legacyResourceValidation['is_archaeological_area_validator']) && $legacyResourceValidation['is_archaeological_area_validator'] == true) {
                $user->syncRoles([UserRole::Validator]);
                $user->givePermissionTo('validate archaeological areas');
            }
        }
    }
}
