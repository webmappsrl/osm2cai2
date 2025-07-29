<?php

namespace App\Nova;

use App\Traits\Nova\UgcCommonFieldsTrait;
use App\Traits\Nova\UgcCommonMethodsTrait;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Wm\Osm2caiMapMultiLinestring\Osm2caiMapMultiLinestring;
use Wm\WmPackage\Nova\UgcTrack as WmUgcTrack;

class UgcTrack extends WmUgcTrack
{
    use UgcCommonFieldsTrait, UgcCommonMethodsTrait;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\UgcTrack::class;

    /**
     * Get the resource label
     */
    public static function label()
    {
        return static::getResourceLabel('Track');
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(Request $request): array
    {
        $commonFields = $this->getCommonFields();
        $commonFields = array_merge($commonFields, $this->additionalFields($request));

        return $commonFields;
    }

    /**
     * Get additional fields specific to tracks
     */
    public function additionalFields(Request $request)
    {
        $centroid = $this->getCentroid();
        $geojson = $this->getGeojsonForMapView();
        $fields = [
            Text::make(__('Taxonomy Where'), function ($model) {
                $wheres = $model->taxonomy_wheres;
                $words = explode(' ', $wheres);
                $lines = array_chunk($words, 3);
                $formattedWheres = implode('<br>', array_map(function ($line) {
                    return implode(' ', $line);
                }, $lines));

                return $formattedWheres;
            })->asHtml()
                ->onlyOnDetail(),
            Osm2caiMapMultiLinestring::make('geometry')->withMeta([
                'center' => $centroid ?? [42, 10],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'defaultZoom' => 10,
                'geojson' => json_encode($geojson),
            ])->hideFromIndex(),
        ];

        return $fields;
    }

    /**
     * Get permission type for common actions
     */
    protected function getPermissionType(): string
    {
        return 'tracks';
    }

    /**
     * Get additional export fields for tracks
     */
    protected static function getAdditionalExportFields(): array
    {
        return [
            'raw_data->latitude' => __('Latitudine'),
            'raw_data->longitude' => __('Longitudine'),
        ];
    }

    /**
     * Get the cards available for the request.
     */
    public function cards(Request $request): array
    {
        return $this->getCommonCards(static::$model);
    }
}
