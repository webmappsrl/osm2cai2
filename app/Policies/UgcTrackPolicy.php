<?php

namespace App\Policies;

use App\Models\UgcTrack;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UgcTrackPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, UgcTrack $ugcTrack)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, UgcTrack $ugcTrack)
    {
        $user_can_update = $user->hasRole('Administrator') || ($ugcTrack->user_id === $user->id && $ugcTrack->validated === 'not_validated');

        $permission = 'validate tracks';

        return $user_can_update || $user->hasPermissionTo($permission);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, UgcTrack $ugcTrack)
    {
        return $user->hasRole('Administrator') || ($user->id === $ugcTrack->user_id && $ugcTrack->validated !== 'valid');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, UgcTrack $ugcTrack)
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, UgcTrack $ugcTrack)
    {
        return true;
    }
}
