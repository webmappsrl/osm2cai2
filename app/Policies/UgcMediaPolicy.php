<?php

namespace App\Policies;

use App\Models\UgcMedia;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UgcMediaPolicy
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
     * @param  UgcMedia  $ugcMedia
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, UgcMedia $ugcMedia)
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
     * @param  UgcMedia  $ugcMedia
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, UgcMedia $ugcMedia)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  UgcMedia  $ugcMedia
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, UgcMedia $ugcMedia)
    {
        return $user->hasRole('Administrator') || $user->id === $ugcMedia->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  UgcMedia  $ugcMedia
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, UgcMedia $ugcMedia)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  UgcMedia  $ugcMedia
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, UgcMedia $ugcMedia)
    {
        //
    }
}
