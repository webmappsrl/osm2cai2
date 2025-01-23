<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\Province;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function viewAny(User $user): bool
    {
        $territorialRole = $user->getTerritorialRole();
        if ($territorialRole == 'local' || $territorialRole == 'unknown') {
            return false;
        } else {
            return true;
        }
    }

    public function view(User $user, User $model): bool
    {
        $territorialRole = $user->getTerritorialRole();

        if ($territorialRole === 'admin' || $territorialRole === 'regional' || $territorialRole === 'national') {
            return true;
        } else {
            return false;
        }
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Administrator');
    }

    public function update(User $user, User $model): bool
    {
        $territorialRole = $user->getTerritorialRole();

        if ($territorialRole === 'admin' || $territorialRole === 'regional' || $territorialRole === 'national') {
            return true;
        } else {
            return false;
        }
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('Administrator');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasRole('Administrator');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('Administrator');
    }

    public function attachProvince(User $user, User $model, Province $province)
    {
        return $user->hasRole('Administrator');
    }

    public function detachProvince(User $user, User $model, Province $province)
    {
        return $user->hasRole('Administrator');
    }

    public function attachArea(User $user, User $model, Area $area)
    {
        return $user->hasRole('Administrator');
    }

    public function detachArea(User $user, User $model, Area $area)
    {
        return $user->hasRole('Administrator');
    }

    public function attachSector(User $user, User $model, Sector $sector)
    {
        return $user->hasRole('Administrator');
    }

    public function detachSector(User $user, User $model, Sector $sector)
    {
        return $user->hasRole('Administrator');
    }
}
