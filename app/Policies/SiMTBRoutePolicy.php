<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SiMTBRoute;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy for the Sentiero Italia CAI MTB routes.
 *
 * Without this policy, Laravel would automatically resolve HikingRoutePolicy
 * (because SiMTBRoute extends HikingRoute), which prevents any modification
 * for non-Administrator users. Here, besides Administrators (handled by
 * before()), users with the SicaiManager role are also authorized to view and
 * edit Sentiero Italia content.
 */
class SiMTBRoutePolicy
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

    public function view(User $user, SiMTBRoute $siMTBRoute): bool
    {
        return $this->isSicaiManager($user);
    }

    public function create(User $user): bool
    {
        return $this->isSicaiManager($user);
    }

    public function update(User $user, SiMTBRoute $siMTBRoute): bool
    {
        return $this->isSicaiManager($user);
    }

    public function delete(User $user, SiMTBRoute $siMTBRoute): bool
    {
        return $this->isSicaiManager($user);
    }

    public function restore(User $user, SiMTBRoute $siMTBRoute): bool
    {
        return $this->isSicaiManager($user);
    }

    public function forceDelete(User $user, SiMTBRoute $siMTBRoute): bool
    {
        return $this->isSicaiManager($user);
    }
}
