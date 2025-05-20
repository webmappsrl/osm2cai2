<?php

namespace App\Policies;

use App\Models\Province;
use App\Models\User;

class ProvincePolicy
{
    private $allowedRoles = ['Administrator', 'National Referent', 'Regional Referent'];

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
        return $this->hasAllowedRole($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Province $province): bool
    {
        $provinces = $user->provinces;

        return $this->hasAllowedRole($user) || $provinces->contains($province->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Province $province): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Province $province): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Province $province): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Province $province): bool
    {
        return false;
    }
}
