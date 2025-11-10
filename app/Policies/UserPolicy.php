<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Province;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct() {}

    public function viewAny(User $user): bool
    {
        $territorialRole = $user->getTerritorialRole();

        return $territorialRole !== 'local' && $territorialRole !== 'unknown';
    }

    public function view(User $user, User $model): bool
    {
        $territorialRole = $user->getTerritorialRole();

        return $territorialRole === 'admin' || $territorialRole === 'regional' || $territorialRole === 'national';
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Administrator);
    }

    public function update(User $user, User $model): bool
    {
        $territorialRole = $user->getTerritorialRole();

        return $territorialRole === 'admin' || $territorialRole === 'regional' || $territorialRole === 'national';
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::Administrator);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::Administrator);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole(UserRole::Administrator);
    }

    public function attachProvince(User $user, User $model, Province $province)
    {
        return $this->canManageTerritoryAssociations($user);
    }

    public function detachProvince(User $user, User $model, Province $province)
    {
        return $this->canManageTerritoryAssociations($user);
    }

    public function attachArea(User $user, User $model, Area $area)
    {
        return $this->canManageTerritoryAssociations($user);
    }

    public function detachArea(User $user, User $model, Area $area)
    {
        return $this->canManageTerritoryAssociations($user);
    }

    public function attachSector(User $user, User $model, Sector $sector)
    {
        return $this->canManageTerritoryAssociations($user);
    }

    public function detachSector(User $user, User $model, Sector $sector)
    {
        return $this->canManageTerritoryAssociations($user);
    }

    /**
     * Check if user has permission to manage territory associations
     */
    private function canManageTerritoryAssociations(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Administrator, UserRole::NationalReferent, UserRole::RegionalReferent]);
    }
}
