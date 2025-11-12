<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Pivots\AreaUser;
use App\Models\Pivots\ProvinceUser;
use App\Models\Pivots\SectorUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Nova\Auth\Impersonatable;
use Spatie\Permission\Models\Permission;
use Wm\WmPackage\Database\Factories\UserFactory;
use Wm\WmPackage\Models\User as WmUser;

/**
 * @mixin \Spatie\Permission\Traits\HasRoles
 */
class User extends WmUser
{
    use Impersonatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'region_name',
        'club_cai_code',
        'phone',
    ];

    public function EcPois()
    {
        return $this->hasMany(EcPoi::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function managedClub()
    {
        return $this->belongsTo(Club::class, 'managed_club_id');
    }

    public function provinces(): BelongsToMany
    {
        return $this->belongsToMany(Province::class, 'province_user', 'user_id', 'province_id')->using(ProvinceUser::class);
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_user', 'user_id', 'area_id')->using(AreaUser::class);
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'sector_user', 'user_id', 'sector_id')->using(SectorUser::class);
    }

    public function ugcTracks()
    {
        return $this->hasMany(UgcTrack::class);
    }

    public function ugcPois()
    {
        return $this->hasMany(UgcPoi::class);
    }

    /**
     * Get the territorial role of the user.
     *
     * This method determines the user's territorial role based on their permissions and assignments:
     * - 'admin' for administrators
     * - 'national' for national referents
     * - 'regional' for users with a region assigned
     * - 'local' for users with provinces, areas or sectors
     * - 'unknown' as fallback
     *
     * @return string The territorial role ('admin'|'national'|'regional'|'local'|'unknown')
     */
    public function getTerritorialRole(): string
    {
        if ($this->hasRole(UserRole::Administrator)) {
            return 'admin';
        }

        if ($this->hasRole(UserRole::NationalReferent)) {
            return 'national';
        }

        if ($this->hasRole(UserRole::RegionalReferent)) {
            return 'regional';
        }

        if ($this->hasRole(UserRole::LocalReferent)) {
            return 'local';
        }

        return 'unknown';
    }

    /**
     * Get all sectors associated with this user through various relationships.
     *
     * This method aggregates sectors from:
     * - The user's assigned region
     * - The user's assigned provinces
     * - The user's assigned areas
     * - The user's directly assigned sectors
     *
     * @return Collection<Sector>
     */
    public function getSectors(): Collection
    {
        $sectorIds = [];

        // Get sectors from user's region
        if (! is_null($this->region)) {
            $sectorIds = $this->region->sectorsIds();
        }

        // Get sectors from user's provinces
        if ($this->provinces->isNotEmpty()) {
            foreach ($this->provinces as $province) {
                $sectorIds = array_merge($sectorIds, $province->sectorsIds());
            }
        }

        // Get sectors from user's areas
        if ($this->areas->isNotEmpty()) {
            foreach ($this->areas as $area) {
                $sectorIds = array_merge($sectorIds, $area->sectorsIds());
            }
        }

        // Get directly assigned sectors
        if ($this->sectors->isNotEmpty()) {
            foreach ($this->sectors as $sector) {
                $sectorIds[] = $sector->id;
            }
        }

        // Remove duplicates and reindex array
        $sectorIds = array_values(array_unique($sectorIds));

        // Return ordered collection of sectors
        return Sector::whereIn('id', $sectorIds)
            ->orderBy('full_code', 'ASC')
            ->get();
    }

    /**
     * Determines if the user can manage a specific hiking route based on their territorial role and permissions.
     *
     * The authorization logic follows these rules:
     * - Users with 'unknown' role cannot manage any routes
     * - Administrators and national referents can manage all routes
     * - Regional referents can manage routes in their assigned region
     * - Local managers can manage routes that intersect with their assigned areas, sectors or provinces
     *
     * @param  HikingRoute  $hr  The hiking route to check permissions for
     * @return bool True if the user can manage the hiking route, false otherwise
     */
    public function canManageHikingRoute(HikingRoute $hr): bool
    {
        if ($this->hasRole(UserRole::Administrator) || $this->hasRole(UserRole::NationalReferent)) {
            return true;
        }

        if ($this->hasRole(UserRole::RegionalReferent)) {
            return $hr->regions->pluck('id')->contains($this->region->id);
        }

        if ($this->hasRole(UserRole::LocalReferent)) {
            return ! $hr->sectors->intersect($this->sectors)->isEmpty() ||
                ! $hr->areas->intersect($this->areas)->isEmpty() ||
                ! $hr->provinces->intersect($this->provinces)->isEmpty();
        }

        return false;
    }

    public function canImpersonate()
    {
        return $this->hasRole(UserRole::Administrator);
    }

    public function canBeImpersonated()
    {
        return ! $this->hasRole(UserRole::Administrator);
    }

    public function canManageClub(Club $club): bool
    {
        return $this->hasRole(UserRole::Administrator) || $this->hasRole(UserRole::NationalReferent) || ($this->hasRole(UserRole::RegionalReferent) && $this->region_id == $club->region_id) || (! is_null($this->managedClub) && $this->managedClub->id == $club->id);
    }

    /**
     * Check if user is a validator for the specified form ID
     *
     * @param  string|null  $formId  The form ID to check validation permissions for
     * @return bool
     */
    public function isValidatorForFormId($formId)
    {
        // If no form ID is provided, allow validation for all forms
        if (empty($formId)) {
            return true;
        }

        // Special case for water form
        if ($formId === 'water') {
            return $this->hasPermissionTo('validate source surveys');
        }

        // Format the form ID for permission name
        $formattedFormId = $this->formatFormIdForPermission($formId);
        $permissionName = 'validate ' . $formattedFormId;

        // If permission doesn't exist in the system, allow validation
        if (! Permission::where('name', $permissionName)->exists()) {
            return false;
        }

        return $this->hasPermissionTo($permissionName);
    }

    /**
     * Format form ID for permission name
     *
     * @param  string  $formId  The form ID to format
     */
    protected function formatFormIdForPermission(string $formId): string
    {
        $formId = str_replace('_', ' ', $formId);

        // Add plural 's' if not already present
        if (! str_ends_with($formId, 's')) {
            $formId .= 's';
        }

        return $formId;
    }

    /**
     * Boot method - Remove chelout listeners if present.
     *
     * @return void
     */
    protected static function booted()
    {
        // Remove any chelout listeners previously added here
        // parent::boot(); // Keep only if necessary
    }

    /**
     * Checks/Assigns the Local Referent role. Called by Pivot Models when a link is created or deleted.
     */
    public function checkAndAssignLocalReferentRole(): void
    {
        $this->refresh();

        $hasTerritoryAssociations = $this->provinces()->exists() ||
            $this->areas()->exists() ||
            $this->sectors()->exists();

        $isHigherRole = $this->hasAnyRole(UserRole::higherRoles());
        $hasLocalRole = $this->hasRole(UserRole::LocalReferent);

        if ($hasTerritoryAssociations) {
            if (! $isHigherRole && ! $hasLocalRole) {
                $this->assignRole(UserRole::LocalReferent);
            }
        } else {
            if (! $isHigherRole && $hasLocalRole) {
                $this->removeRole(UserRole::LocalReferent);
            }
        }
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}
