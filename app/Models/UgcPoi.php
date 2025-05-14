<?php

namespace App\Models;

use App\Enums\ValidatedStatusEnum;
use App\Models\UgcMedia;
use App\Models\User;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UgcPoi extends Model
{
    use HasFactory, SpatialDataTrait;

    protected $table = 'ugc_pois';

    protected $guarded = [];

    protected $casts = [
        'raw_data' => 'array',
        'validation_date' => 'datetime',
        'raw_data->date' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Get the registered at date attribute.
     *
     * Checks for 'date' or 'createdAt' in raw_data, falling back to model's created_at.
     * This handles changes in the raw_data structure from the app.
     * See: https://osm2cai.cai.it/resources/source-surveys/4779 (new) and https://osm2cai.cai.it/resources/source-surveys/1235 (old)
     *
     * @return Carbon|null
     */
    public function getRegisteredAtAttribute()
    {
        if (isset($this->raw_data['date'])) {
            return Carbon::parse($this->raw_data['date']);
        }

        if (isset($this->raw_data['createdAt'])) {
            return Carbon::parse($this->raw_data['createdAt']);
        }

        return $this->created_at;
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            $model->user_id = auth()->id() ?? $model->user_id;
            $model->app_id = $model->app_id ?? 'osm2cai';
            $model->save();

            if (isset($model->geometry)) {
                $model->fillRawDataLatitudeAndLongitude();
            }
        });
    }

    //getter for the name attribute
    public function getNameAttribute()
    {
        return $this->raw_data['title'] ?? $this->name ?? null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    public function ugc_media(): HasMany
    {
        return $this->hasMany(UgcMedia::class);
    }

    /**
     * Return the json version of the ugc poi, avoiding the geometry
     *
     * @return array
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

    /**
     * Create a geojson from the ugc poi
     *
     * @return array
     */
    public function getGeojson(): ?array
    {
        $feature = $this->getEmptyGeojson();
        if (isset($feature['properties'])) {
            $feature['properties'] = $this->getJsonProperties();

            return $feature;
        } else {
            return null;
        }
    }

    /**
     * Calculate the geometry based on the raw_data
     *
     * @return string
     */
    public function calculateGeometryFromRawData(): string
    {
        $latitude = $this->raw_data['position']['latitude'];
        $longitude = $this->raw_data['position']['longitude'];

        return DB::select(
            'SELECT ST_AsGeoJSON(ST_SetSRID(ST_MakePoint(?, ?), 4326))::json as geom',
            [$longitude, $latitude]
        )[0]->geom;
    }

    /**
     * Fill raw_data latitude and longitude based on the geometry
     */
    public function fillRawDataLatitudeAndLongitude(): void
    {
        //check if the latitude and longitude are already set
        if (isset($this->raw_data['position']['latitude']) && isset($this->raw_data['position']['longitude'])) {
            return;
        }

        //get latitude and longitude from geometry using postgis
        $geometry = $this->geometry;

        $coordinates = DB::select(
            'SELECT ST_Y(geometry) as latitude, ST_X(geometry) as longitude 
             FROM (SELECT geometry::geometry FROM (SELECT ?::geometry as geometry) g) as t',
            [$geometry]
        )[0];

        //save rawdata in a variable because with the array cast it is not possible to set the raw_data attribute
        $rawData = $this->raw_data ?? [];

        if (! isset($rawData['position'])) {
            $rawData['position'] = [];
        }

        $rawData['position']['latitude'] = floatval($coordinates->latitude);
        $rawData['position']['longitude'] = floatval($coordinates->longitude);

        // override the raw_data attribute with the new raw data
        $this->raw_data = $rawData;

        $this->save();
    }

    /**
     * Calculates the water flow rate based on raw data for UGC POIS ACQUASORGENTE
     *
     * @return string
     */
    public function calculateFlowRate()
    {
        $rawData = $this->raw_data;
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
     * @param string $value
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
