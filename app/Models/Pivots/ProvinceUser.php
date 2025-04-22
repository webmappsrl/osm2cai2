<?php

namespace App\Models\Pivots;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProvinceUser extends Pivot
{
    public $incrementing = true;

    // Define the relationship to the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (ProvinceUser $pivot) {
            $pivot->user?->checkAndAssignLocalReferentRole();
        });

        static::deleted(function (ProvinceUser $pivot) {
            $user = $pivot->user ?? User::find($pivot->user_id);
            $user?->checkAndAssignLocalReferentRole();
        });
    }
}
