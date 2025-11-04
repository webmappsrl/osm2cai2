<?php

namespace App\Models;

use App\Traits\SpatialDataTrait;
use App\Traits\UgcCommonModelTrait;
use Wm\WmPackage\Models\UgcTrack as WmUgcTrack;
use Wm\WmPackage\Services\GeometryComputationService;

class UgcTrack extends WmUgcTrack
{
    use SpatialDataTrait, UgcCommonModelTrait;

    protected $table = 'ugc_tracks';

    protected $fillable = ['geohub_id', 'name', 'description', 'geometry', 'user_id', 'updated_at', 'raw_data', 'taxonomy_wheres', 'metadata', 'app_id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->initializeUgcCommonModelTrait();
    }
    
    protected static function boot()
    {
        parent::boot();
        // Il bootUgcCommonModelTrait viene chiamato automaticamente
    }

    /**
     * Convert 2D geometry to 3D when setting geometry attribute
     */
    public function setGeometryAttribute($value)
    {
        $this->attributes['geometry'] = GeometryComputationService::make()->convertTo3DGeometry($value);
    }

    /**
     * Return the json version of the ugctrack, avoiding the geometry
     */
    public function getJsonProperties(): array
    {
        $array = $this->toArray();

        $propertiesToClear = ['geometry'];
        foreach ($array as $property => $value) {
            if (is_null($value) || in_array($property, $propertiesToClear)) {
                unset($array[$property]);
            }
        }

        if (isset($array['raw_data'])) {
            $array['raw_data'] = json_encode($array['raw_data']);
        }

        return $array;
    }

}
