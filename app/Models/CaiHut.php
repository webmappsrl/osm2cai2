<?php

namespace App\Models;

use App\Models\Region;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CaiHut extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted()
    {
        static::created(function ($caiHut) {
            Artisan::call('osm2cai:add_cai_huts_to_hiking_routes', ['model' => 'CaiHuts', 'id' => $caiHut->id]);
        });
    }


    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
