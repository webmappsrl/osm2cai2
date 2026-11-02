<?php

namespace App\Models;

use App\Enums\ValidatedStatusEnum;
use App\Services\FlowRateParser;
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
     * Calculates the water flow rate for UGC POIS ACQUASORGENTE and stores it in raw_data
     *
     * @return float|string
     */
    public function calculateFlowRate()
    {
        $flowRate = $this->computeFlowRate();

        // Update raw_data with the calculated flow rate
        $rawData = $this->getRawDataArray();
        $rawData['flow_rate'] = $flowRate;
        $this->raw_data = $rawData;
        $this->saveQuietly();

        return $flowRate;
    }

    /**
     * Computes the water flow rate (L/s) without saving the model.
     * Returns 'N/A' when the flow rate is not validated or the values are not interpretable.
     */
    public function computeFlowRate(): float|string
    {
        if ($this->water_flow_rate_validated != ValidatedStatusEnum::VALID->value) {
            return 'N/A';
        }

        $volume = FlowRateParser::parseVolume($this->getFlowRateInput('range_volume'));
        $time = FlowRateParser::parseTime($this->getFlowRateInput('range_time'));

        if ($volume === null || $time === null || $time == 0) {
            return 'N/A';
        }

        return round($volume / $time, 3);
    }

    /**
     * Surveys sent by the app through wm-package keep the form data in properties.form,
     * older surveys in raw_data.
     */
    private function getFlowRateInput(string $key): mixed
    {
        $value = $this->properties['form'][$key] ?? null;
        if ($value !== null && trim((string) $value) !== '') {
            return $value;
        }

        return $this->getRawDataArray()[$key] ?? null;
    }

    private function getRawDataArray(): array
    {
        $rawData = $this->raw_data;
        if (! is_array($rawData)) {
            $rawData = is_string($rawData) ? json_decode($rawData, true) : [];
            $rawData = is_array($rawData) ? $rawData : [];
        }

        return $rawData;
    }
}
