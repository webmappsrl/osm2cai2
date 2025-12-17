<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TrailSurvey;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrailSurveyPolicy
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
    public function view(User $user, TrailSurvey $trailSurvey)
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
        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, TrailSurvey $trailSurvey)
    {
        return $user->hasRole(UserRole::Administrator) || $user->id === $trailSurvey->owner_id;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, TrailSurvey $trailSurvey)
    {
        return $user->hasRole(UserRole::Administrator) || $user->id === $trailSurvey->owner_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, TrailSurvey $trailSurvey)
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, TrailSurvey $trailSurvey)
    {
        return false;
    }
}
