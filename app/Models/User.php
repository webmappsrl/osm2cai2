<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Area;
use App\Models\Club;
use App\Models\Region;
use App\Models\Sector;
use App\Models\UgcPoi;
use App\Models\Province;
use App\Models\UgcTrack;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Permission;
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

    public function ugcTracks()
    {
        return $this->hasMany(UgcTrack::class);
    }

    public function ugcPois()
    {
        return $this->hasMany(UgcPoi::class);
    }

    public function isValidatorForFormId($formId)
    {
        $formId = str_replace('_', ' ', $formId);
        //if form id is empty, return false
        if (empty($formId)) {
            return true;
        }
        //if permission does not exist, return true
        if (!Permission::where('name', 'validate ' . $formId . 's')->exists()) {
            return true;
        }
        if ($formId === 'water') {
            return $this->hasPermissionTo('validate source surveys');
        }
        $permissionName = 'validate ' . $formId;
        if (!str_ends_with($formId, 's')) {
            $permissionName .= 's';
        }
        return $this->hasPermissionTo($permissionName);
    }
}
