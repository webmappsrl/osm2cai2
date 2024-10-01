<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Area;
use App\Models\Club;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Province;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Wm\WmPackage\Model\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'region_name',
        'club_cai_code',
        'phone'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function EcPois()
    {
        return $this->hasMany(EcPoi::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_name', 'name');
    }

    public function section()
    {
        return $this->belongsTo(Club::class);
    }

    public function provinces()
    {
        return $this->belongsToMany(Province::class, 'province_user', 'user_id', 'province_name', 'id', 'name');
    }

    public function areas()
    {
        return $this->belongsToMany(Area::class, 'area_user', 'user_id', 'area_name', 'id', 'name');
    }

    public function sectors()
    {
        return $this->belongsToMany(Sector::class, 'sector_user', 'user_id', 'sector_name', 'id', 'name');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_cai_code', 'cai_code');
    }
}
