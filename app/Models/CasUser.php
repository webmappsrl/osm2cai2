<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A model for each person who use succesfully use the cas login
 * a CasUser has a "standard" User with a belongsTo and some cas attributes(see fillable class property)
 */
class CasUser extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'user_uuid',
        'uid',
        'cas_id',
        'firstname',
        'lastname',
        'roles'
    ];


    /**
     * get the User model
     *
     * @return User
     */
    public function user() {
        return $this->belongsTo( User::class );
    }
}
