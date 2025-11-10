<?php

namespace App\Models;

use App\Enums\ValidatedStatusEnum;
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
        $this->casts = array_merge($this->casts ?? [], [
            'raw_data' => 'array',
        ]);
        $this->initializeUgcCommonModelTrait();
    }

    /**
     * Convert 2D geometry to 3D when setting geometry attribute
     */
    public function setGeometryAttribute($value)
    {
        $this->attributes['geometry'] = GeometryComputationService::make()->convertTo3DGeometry($value);
    }

    /**
     * Calculates the water flow rate based on raw data for UGC POIS ACQUASORGENTE
     *
     * @return string
     */
    public function calculateFlowRate()
    {
        $rawData = $this->raw_data;
        if (! is_array($rawData)) {
            $rawData = is_string($rawData) ? json_decode($rawData, true) : [];
            $rawData = is_array($rawData) ? $rawData : [];
        }
        $flowRate = 'N/A';

        if ($this->water_flow_rate_validated == ValidatedStatusEnum::VALID->value) {
            $volume = $this->formatNumericValue($rawData['range_volume'] ?? '');
            $time = $this->formatNumericValue($rawData['range_time'] ?? '');

            if (is_numeric($volume) && is_numeric($time) && $time != 0) {
                $flowRate = round($volume / $time, 3);
            }
        }

        // Update raw_data with the calculated flow rate
        $rawData['flow_rate'] = $flowRate;
        $this->raw_data = $rawData;
        $this->saveQuietly();

        return $flowRate;
    }

    /**
     * Formats a numeric value for calculation.
     *
     * @param  string  $value
     * @return string
     */
    private function formatNumericValue($value)
    {
        if (strpos($value, '.') !== false) {
            return $value;
        }

        $value = preg_replace('/[^0-9,]/', '', $value);

        return str_replace(',', '.', $value);
    }
}
