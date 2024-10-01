<?php

namespace App\Models;

use App\Models\User;
use App\Traits\GeojsonableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sector extends Model
{
    use HasFactory, GeojsonableTrait;

    protected $fillable = [
        'id',
        'name',
        'geometry',
        'code',
        'full_code',
        'num_expected',
        'human_name',
        'manager',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
