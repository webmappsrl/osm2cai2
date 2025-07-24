<?php

namespace App\Nova;

use App\Nova\Metrics\UgcAppNameDistribution;
use App\Nova\Metrics\UgcDevicePlatformDistribution;
use App\Nova\Metrics\UgcValidatedStatusDistribution;
use App\Nova\Metrics\UgcAttributeDistribution;
use App\Traits\Nova\UgcCommonFieldsTrait;
use App\Traits\Nova\UgcCommonMethodsTrait;
use Illuminate\Http\Request;
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

    public function cards(Request $request): array
    {
        return [
            new UgcAppNameDistribution,
            new UgcAttributeDistribution('App Version', "properties->'device'->>'appVersion'"),
            new UgcAttributeDistribution('App Form', "properties->'form'->>'id'"),
            new UgcDevicePlatformDistribution,
            new UgcValidatedStatusDistribution,
        ];
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
}
