<?php

namespace App\Policies;

use App\Enums\ValidatedStatusEnum;
use App\Models\User;
use App\Models\UgcPoi;
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
     * Determine whether the user can update the ugcPoi model.
     *
     * This method implements a permission system with the following rules:
     * 1. Administrators have full access to any POI
     * 2. Validated POIs cannot be modified by non-admin users
     * 3. POI owners can update their own non-validated POIs
     * 4. Form validators can update non-validated POIs for their assigned forms
     *
     * @param  User  $user  The user attempting to update the POI
     * @param  ugcPoi  $ugcPoi  The POI to be updated
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, UgcPoi $ugcPoi)
    {
        // RULE 1: Administrators have complete access
        // Admins can modify any POI regardless of its validation status
        if ($user->hasRole('Administrator')) {
            return true;
        }

        // RULE 2: Validation status check
        // If the POI has already been validated (status other than NOT_VALIDATED),
        // it cannot be modified by non-admin users
        // This preserves the integrity of validated data
        if ($ugcPoi->validated !== ValidatedStatusEnum::NOT_VALIDATED->value) {
            return false;
        }

        // RULE 3: Retrieve the form ID associated with the POI
        // The form_id can be stored in two ways:
        // - As a direct model attribute
        // - In the POI's JSON properties under ['form']['id']
        $formId = $ugcPoi->form_id;
        if ($ugcPoi->properties && isset($ugcPoi->properties['form']['id'])) {
            $formId = $ugcPoi->properties['form']['id'];
        }

        // RULE 4: Owner rights
        // The user who created the POI can always modify it,
        // as long as it hasn't been validated yet (checked above)
        if ($ugcPoi->user_id === $user->id) {
            return true;
        }

        // RULE 5: Validator rights
        // Users with validation permissions for the specific form
        // can modify non-validated POIs from that form
        // This allows validators to correct/improve content
        if (! empty($formId) && $user->isValidatorForFormId($formId)) {
            return true;
        }

        // RULE 6: Deny all other cases
        // If none of the previous conditions are met,
        // the update is not allowed
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ugcPoi $ugcPoi)
    {
        // Admin can delete any POI, owner can delete only if not validated
        return $user->hasRole('Administrator') ||
            ($user->id === $ugcPoi->user_id && $ugcPoi->validated !== ValidatedStatusEnum::VALID->value);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ugcPoi $ugcPoi)
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ugcPoi $ugcPoi)
    {
        return true;
    }
}
