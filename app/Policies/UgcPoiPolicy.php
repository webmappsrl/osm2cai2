<?php

namespace App\Policies;

use App\Models\UgcPoi;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UgcPoiPolicy
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
     * @param  UgcPoi  $ugcPoi
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, UgcPoi $ugcPoi)
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
     * @param  UgcPoi  $ugcPoi
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, UgcPoi $ugcPoi)
    {
        // User must have at least one permission
        if (count($user->getAllPermissions()) < 1) {
            return false;
        }

        // Allow update if no form_id is specified
        if (empty($ugcPoi->form_id)) {
            return true;
        }

        // Check if user is admin or the owner of a non-validated POI
        $isAdminOrOwner = $user->hasRole('Administrator') ||
            ($ugcPoi->user_id === $user->id && $ugcPoi->validated === 'not_validated');

        // Determine the permission name based on form_id
        $permissionName = $this->getPermissionNameFromFormId($ugcPoi->form_id);

        // User can update if they are admin/owner or have the specific permission
        return $isAdminOrOwner || $user->hasPermissionTo($permissionName);
    }

    /**
     * Get the permission name from the form ID.
     *
     * @param  string  $formId
     * @return string
     */
    private function getPermissionNameFromFormId(string $formId): string
    {
        if ($formId === 'water') {
            $resourceName = 'source surveys';
        } else {
            $resourceName = str_replace('_', ' ', $formId);

            // Add 's' at the end if not already plural
            if (!str_ends_with($resourceName, 's')) {
                $resourceName .= 's';
            }
        }

        return 'validate ' . $resourceName;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  UgcPoi  $ugcPoi
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, UgcPoi $ugcPoi)
    {
        // Admin can delete any POI, owner can delete only if not validated
        return $user->hasRole('Administrator') ||
            ($user->id === $ugcPoi->user_id && $ugcPoi->validated !== 'valid');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  UgcPoi  $ugcPoi
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, UgcPoi $ugcPoi)
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  UgcPoi  $ugcPoi
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, UgcPoi $ugcPoi)
    {
        return true;
    }
}
