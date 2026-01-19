<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SignageProject;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SignageProjectPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can attach hiking routes to the signage project.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SignageProject  $signageProject
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function attachHikingRoutes(User $user, SignageProject $signageProject)
    {
        // Altrimenti solo il creatore del progetto può aggiungere hiking routes
        return $signageProject->user_id === $user->id;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SignageProject  $signageProject
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, SignageProject $signageProject)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Gli admin e gli utenti con settori assegnati possono creare progetti segnaletica.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        // Gli admin possono sempre creare progetti
        if ($user->hasRole(UserRole::Administrator)) {
            return true;
        }

        // Altrimenti verifica se l'utente ha settori assegnati (direttamente o tramite region/province/area)
        return $user->getSectors()->isNotEmpty();
    }

    /**
     * Determine whether the user can update the model.
     * Gli admin possono sempre modificare, altrimenti solo il creatore.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SignageProject  $signageProject
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, SignageProject $signageProject)
    {
        // Gli admin possono sempre modificare
        if ($user->hasRole(UserRole::Administrator)) {
            return true;
        }

        // Altrimenti solo il creatore del progetto può modificarlo
        return $signageProject->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     * Gli admin possono sempre eliminare, altrimenti solo il creatore.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SignageProject  $signageProject
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, SignageProject $signageProject)
    {
        // Gli admin possono sempre eliminare
        if ($user->hasRole(UserRole::Administrator)) {
            return true;
        }

        // Altrimenti solo il creatore del progetto può eliminarlo
        return $signageProject->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SignageProject  $signageProject
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, SignageProject $signageProject)
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Gli admin possono sempre eliminare definitivamente, altrimenti solo il creatore.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SignageProject  $signageProject
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, SignageProject $signageProject)
    {
        // Gli admin possono sempre eliminare definitivamente
        if ($user->hasRole(UserRole::Administrator)) {
            return true;
        }

        // Altrimenti solo il creatore del progetto può eliminarlo definitivamente
        return $signageProject->user_id === $user->id;
    }
}
