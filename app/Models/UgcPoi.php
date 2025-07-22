<?php

namespace App\Models;

use App\Traits\UgcCommonModelTrait;
use Wm\WmPackage\Models\UgcPoi as WmUgcPoi;
use Wm\WmPackage\Services\GeometryComputationService;

class UgcPoi extends WmUgcPoi
{
    use UgcCommonModelTrait;

    protected $table = 'ugc_pois';

    protected $fillable = [
        'user_id',
        'app_id',
        'name',
        'geometry',
        'properties',
        'geohub_id',
        'description',
        'raw_data',
        'taxonomy_wheres',
        'validated',
        'water_flow_rate_validated',
        'validator_id',
        'validation_date',
        'note',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->initializeUgcCommonModelTrait();
    }

    /**
     * Convert 2D geometry to 3D when setting geometry attribute
     */
    public function setGeometryAttribute($value)
    {
        $this->attributes['geometry'] = GeometryComputationService::make()->convertTo3DGeometry($value);
    }
}
