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
     * @param  User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user
     * @param  UgcTrack  $ugcTrack
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, UgcTrack $ugcTrack)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user
     * @param  UgcTrack  $ugcTrack
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
     * @param  User  $user
     * @param  UgcTrack  $ugcTrack
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, UgcTrack $ugcTrack)
    {
        return $user->hasRole('Administrator') || ($user->id === $ugcTrack->user_id && $ugcTrack->validated !== 'valid');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  UgcTrack  $ugcTrack
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, UgcTrack $ugcTrack)
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  UgcTrack  $ugcTrack
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, UgcTrack $ugcTrack)
    {
        return true;
    }
}
