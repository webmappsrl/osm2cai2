<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SiPoi;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy for the Sentiero Italia CAI Welcome Points.
 *
 * Without this policy, Laravel would not resolve any specific policy for
 * SiPoi (auto-discovery of App\Models\EcPoi does not propagate to subclasses).
 * Here, besides Administrators (handled by before()), users with the
 * SicaiManager role are also authorized to view and edit Sentiero Italia
 * content.
 */
class SiPoiPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability)
    {
        if ($user->hasRole(UserRole::Administrator)) {
            return true;
        }
    }

    protected function isSicaiManager(User $user): bool
    {
        return $user->hasRole(UserRole::SicaiManager);
    }

    public function viewAny(User $user): bool
    {
        return $this->isSicaiManager($user);
    }

    public function view(User $user, SiPoi $siPoi): bool
    {
        return $this->isSicaiManager($user);
    }

    public function create(User $user): bool
    {
        return $this->isSicaiManager($user);
    }

    public function update(User $user, SiPoi $siPoi): bool
    {
        return $this->isSicaiManager($user);
    }

    public function delete(User $user, SiPoi $siPoi): bool
    {
        return $this->isSicaiManager($user);
    }

    public function restore(User $user, SiPoi $siPoi): bool
    {
        return $this->isSicaiManager($user);
    }

    public function forceDelete(User $user, SiPoi $siPoi): bool
    {
        return $this->isSicaiManager($user);
    }
}
