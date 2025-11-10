<?php

namespace App\Policies;

use App\Models\CaiHut;
use App\Models\User;
use App\Enums\UserRole;

class CaiHutPolicy
{
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
    public function view(User $user, CaiHut $caiHut): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Administrator) || $user->hasRole(UserRole::NationalReferent);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CaiHut $caiHut): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CaiHut $caiHut): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CaiHut $caiHut): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CaiHut $caiHut): bool
    {
        return true;
    }
}
