<?php

namespace App\Policies;

use App\Enums\ValidatedStatusEnum;
use App\Models\UgcPoi;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UgcPoiPolicy
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
    public function view(User $user, UgcPoi $ugcPoi)
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
    public function update(User $user, UgcPoi $ugcPoi)
    {
        // Administrators can update any POI
        if ($user->hasRole('Administrator')) {
            return true;
        }

        // Owners can update their own POI only if it's not validated
        if ($ugcPoi->user_id === $user->id && $ugcPoi->validated === ValidatedStatusEnum::NOT_VALIDATED->value) {
            return true;
        }

        // Users with specific permissions can update POIs with a form_id
        // The permission is derived from the form_id
        if (! empty($ugcPoi->form_id)) {
            $permissionName = $this->getPermissionNameFromFormId($ugcPoi->form_id);

            return $user->hasPermissionTo($permissionName);
        }

        // If none of the above conditions are met (not admin, not owner of unvalidated,
        // and no form_id or owner of validated), deny access.
        return false;
    }

    /**
     * Get the permission name from the form ID.
     */
    private function getPermissionNameFromFormId(string $formId): string
    {
        if ($formId === 'water') {
            $resourceName = 'source surveys';
        } else {
            $resourceName = str_replace('_', ' ', $formId);

            // Add 's' at the end if not already plural
            if (! str_ends_with($resourceName, 's')) {
                $resourceName .= 's';
            }
        }

        return 'validate '.$resourceName;
    }

    /**
     * Determine whether the user can delete the model.
     *
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
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, UgcPoi $ugcPoi)
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, UgcPoi $ugcPoi)
    {
        return true;
    }
}
