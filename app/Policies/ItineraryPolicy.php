<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItineraryPolicy
{
    use HandlesAuthorization;

    public function before(User $user)
    {
        return $user->hasRole(UserRole::ItineraryManager) || $user->hasRole(UserRole::Administrator);
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user) {}

    /**
     * Determine whether the user can view the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Itinerary $itinerary) {}

    /**
     * Determine whether the user can create models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user) {}

    /**
     * Determine whether the user can update the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Itinerary $itinerary) {}

    /**
     * Determine whether the user can delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Itinerary $itinerary) {}

    /**
     * Determine whether the user can restore the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Itinerary $itinerary) {}

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Itinerary $itinerary) {}
}
