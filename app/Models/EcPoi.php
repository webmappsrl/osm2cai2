<?php

namespace App\Models;

use App\Observers\EcPoiObserver;
use App\Traits\AwsCacheable;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use App\Traits\SpatialDataTrait;
use App\Traits\TagsMappingTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;
use Wm\WmPackage\Models\EcPoi as WmEcPoi;

class EcPoi extends WmEcPoi implements OsmfeaturesSyncableInterface
{
    use AwsCacheable, HasFactory, OsmfeaturesGeometryUpdateTrait, OsmfeaturesImportableTrait, SpatialDataTrait, TagsMappingTrait;

    protected $fillable = [
        'name',
        'geometry',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
        'type',
        'score',
        'user_id',
        'tags',
        'properties',
        'app_id',
        'osmid',
    ];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'json',
    ];

    /**
     * Boot the model and set default values for translatable fields
     */
    protected static function boot()
    {
        parent::boot();

        static::observe(EcPoiObserver::class);
    }

    /**
     * Set the properties attribute
     */
    public function setPropertiesAttribute($value)
    {
        $this->attributes['properties'] = is_null($value) ? '[]' : json_encode($value);
    }

    /**
     * Get the properties attribute
     */
    public function getPropertiesAttribute($value)
    {
        if (is_null($value)) {
            return [];
        }

        return is_string($value) ? json_decode($value, true) : $value;
    }

    /**
     * Returns the OSMFeatures API endpoint for listing features for the model.
     */
    public static function getOsmfeaturesEndpoint(): string
    {
        return 'https://osmfeatures.maphub.it/api/v1/features/places/';
    }

    /**
     * Returns the query parameters for listing features for the model.
     *
     * The array keys should be the query parameter name and the values
     * should be the expected value.
     *
     * @return array<string,string>
     */
    public static function getOsmfeaturesListQueryParameters(): array
    {
        return [];
    }

    /**
     * Update the local database after a successful OSMFeatures sync.
     */
    public static function osmfeaturesUpdateLocalAfterSync(string $osmfeaturesId): void
    {
        $model = self::where('osmfeatures_id', $osmfeaturesId)->first();
        if (! $model) {
            throw WmOsmfeaturesException::modelNotFound($osmfeaturesId);
        }

        $osmfeaturesData = is_string($model->osmfeatures_data) ? json_decode($model->osmfeatures_data, true) : $model->osmfeatures_data;

        if (! $osmfeaturesData) {
            Log::channel('wm-osmfeatures')->info('No data found for Ec Poi ' . $osmfeaturesId);

            return;
        }

        // check if geometry has changed
        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        // check if name has changed
        if ($osmfeaturesData['properties']['name'] !== $model->name) {
            $updateData['name'] = $osmfeaturesData['properties']['name'];
        }

        // check if score has changed
        if ($osmfeaturesData['properties']['score'] !== $model->score) {
            $updateData['score'] = $osmfeaturesData['properties']['score'];
        }

        if (! empty($updateData)) {
            $model->update($updateData);
        }
    }

    // Rimuovo il metodo User() perché è già definito nella classe padre come user()
    // Se hai bisogno di personalizzare la relazione, usa un nome diverso

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function mountainGroups()
    {
        return $this->belongsToMany(MountainGroups::class, 'mountain_group_ec_poi', 'ec_poi_id', 'mountain_group_id');
    }

    public function clubs()
    {
        return $this->belongsToMany(Club::class, 'ec_poi_club', 'ec_poi_id', 'club_id');
    }

    public function nearbyCaiHuts()
    {
        return $this->belongsToMany(CaiHut::class, 'ec_poi_cai_hut', 'ec_poi_id', 'cai_hut_id')->withPivot(['buffer']);
    }

    public function nearbyHikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class, 'hiking_route_ec_poi', 'ec_poi_id', 'hiking_route_id')->withPivot(['buffer']);
    }

    // TODO: La tabella ec_poi_municipality non esiste, da cancellare?
    public function municipalities()
    {
        return $this->belongsToMany(Municipality::class, 'ec_poi_municipality', 'ec_poi_id', 'municipality_id');
    }

    /**
     * Clean all pivot table relationships before deleting.
     *
     * This method is called by the Observer before deleting an EcPoi
     * to prevent foreign key constraint errors.
     *
     * @return void
     */
    public function cleanRelations()
    {
        $this->mountainGroups()->detach();
        $this->clubs()->detach();
        $this->nearbyCaiHuts()->detach();
        $this->nearbyHikingRoutes()->detach();
    }
}
