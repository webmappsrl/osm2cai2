<?php

namespace App\Models;

use App\Models\User;
use App\Traits\GeojsonableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Area extends Model
{
    use HasFactory, GeojsonableTrait;

    protected $fillable = [
        'code',
        'name',
        'geometry',
        'full_code',
        'num_expected',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
