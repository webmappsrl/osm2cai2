<?php

namespace App\Nova;

use App\Nova\Actions\ExportUgcPoiTo;
use App\Traits\Nova\UgcCommonFieldsTrait;
use App\Traits\Nova\UgcCommonMethodsTrait;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\MapPoint\MapPoint;
use Wm\WmPackage\Nova\UgcPoi as WmUgcPoi;

class UgcPoi extends WmUgcPoi
{
    use UgcCommonFieldsTrait, UgcCommonMethodsTrait;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\UgcPoi::class;

    /**
     * Get the resource label
     */
    public static function label()
    {
        return static::getResourceLabel('Poi');
    }

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(Request $request): array
    {
        $commonFields = $this->getCommonFields();

        // Aggiungi MapPoint dopo tutti i campi comuni
        $commonFields[] = MapPoint::make('geometry')->withMeta([
            'center' => [43.7125, 10.4013],
            'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
            'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
            'minZoom' => 8,
            'maxZoom' => 14,
            'defaultZoom' => 10,
            'defaultCenter' => [43.7125, 10.4013],
        ])->hideFromIndex()
            ->required();

        return $commonFields;
    }

    /**
     * Get permission type for common actions
     */
    protected function getPermissionType(): string
    {
        return 'pois';
    }

    /**
     * Get the cards available for the request.
     */
    public function cards(Request $request): array
    {
        return $this->getCommonCards(static::$model);
    }

    /**
     * Get the actions available for the resource.
     */
    public function actions(NovaRequest $request): array
    {
        $actions = parent::actions($request);

        // Remove the default ExportTo
        $actions = array_filter($actions, function ($action) {
            return !($action instanceof \Wm\WmPackage\Nova\Actions\ExportTo);
        });

        // Add custom ExportTo action with filtered columns
        $actions[] = new ExportUgcPoiTo(
            $this->getExcludedKeys(),
            $this->getIncludedPropertiesExceptions(),
            $this->getPropertiesColumnLabels()
        );

        return array_values($actions);
    }

    /**
     * Get the list of excluded keys (table columns and properties keys)
     * Keys starting with "properties." are properties keys (written in full)
     * Others are table columns
     *
     * @return array
     */
    protected function getExcludedKeys(): array
    {
        return [
            // Table columns
            'geohub_id',
            'description',
            'raw_data',
            'taxonomy_wheres',
            'form_id',
            'app_id',
            'created_by',
            'geohub_app_id',
            'geometry',
            // Properties keys
            'properties',
            'properties.form',
            'properties.media',
            'properties.name',
            'properties.description',
            'properties.uuid',
            'properties.app_id',
            'properties.device',
            'properties.photos',
            'properties.nominatim',
            'properties.photoKeys',
            'properties.position',
            'properties.storedPhotoKeys',
            'properties.displayPosition',
            'properties.sync_id',
            'properties.type',
            'properties.id',
            'properties.feature_image',
            'properties.image_gallery',
            'properties.geohub_id',
            'properties.geohub_app_id',
            'properties.ec_poi_id',
        ];
    }

    /**
     * Get the list of properties keys to include even if their parent key is excluded
     *
     * @return array
     */
    protected function getIncludedPropertiesExceptions(): array
    {
        return [
            'properties.form.info',
            'properties.position.latitude',
            'properties.position.longitude',
            'properties.form.flow_rate',
            'properties.form.conductivity',
            'properties.form.temperature',
        ];
    }

    /**
     * Get custom labels for properties columns
     * Key is the column name (e.g., "properties.form.temperature")
     * Value is the label to display in Excel (e.g., "Temperatura")
     *
     * @return array
     */
    protected function getPropertiesColumnLabels(): array
    {
        return [
            'properties.form.info' => __('Form Info'),
            'properties.form.temperature' => __('Temperature'),
            'properties.form.conductivity' => __('Conductivity'),
            'properties.position.latitude' => __('Latitude'),
            'properties.position.longitude' => __('Longitude'),
            'properties.updatedAt' => __('Updated At'),
            'properties.createdAt' => __('Created At'),
        ];
    }
}
