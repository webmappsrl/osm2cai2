<?php

namespace App\Policies;

use App\Models\Sector;
use App\Models\User;

class SectorPolicy
{
    private $allowedRoles = ['Administrator', 'National Referent'];

    private function hasAllowedRole(User $user): bool
    {
        $userRoles = $user->getRoleNames();

        return $userRoles->intersect($this->allowedRoles)->isNotEmpty();
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sector $sector): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Administrator');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sector $sector): bool
    {
        return $this->hasAllowedRole($user) || $user->region_id === $sector->area->province->region_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sector $sector): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sector $sector): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sector $sector): bool
    {
        return false;
    }
}
