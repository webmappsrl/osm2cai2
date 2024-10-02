<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Club extends Model
{
    use HasFactory;

    protected $table = 'clubs';

    protected $fillable = [
        'id',
        'name',
        'cai_code',
        'geometry',
        'addr_city',
        'addr_street',
        'addr_housenumber',
        'addr_postcode',
        'website',
        'phone',
        'email',
        'opening_hours',
        'wheelchair',
        'fax',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
