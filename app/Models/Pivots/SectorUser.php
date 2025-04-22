<?php

namespace App\Models\Pivots;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SectorUser extends Pivot
{
    public $incrementing = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::created(function (SectorUser $pivot) {
            $pivot->user?->checkAndAssignLocalReferentRole();
        });

        static::deleted(function (SectorUser $pivot) {
            $user = $pivot->user ?? User::find($pivot->user_id);
            $user?->checkAndAssignLocalReferentRole();
        });
    }
}
