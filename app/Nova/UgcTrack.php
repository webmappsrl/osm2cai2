<?php

namespace App\Nova;

use App\Nova\AbstractUgc;
use App\Nova\Actions\DownloadFeatureCollection;
use App\Nova\Actions\DownloadGeojsonZip;
use App\Nova\Actions\DownloadGeojsonZipUgcTracks;
use App\Nova\Filters\RelatedUGCFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Webmapp\WmEmbedmapsField\WmEmbedmapsField;
use Wm\MapMultiLinestring\MapMultiLinestring;
use Wm\MapMultiLinestringNova\MapMultiLinestringNova;
use Wm\MapMultiLinestringNova3\MapMultiLinestringNova3;
use Wm\Osm2caiMapMultiLinestring\Osm2caiMapMultiLinestring;
use Wm\WmPackage\Nova\Actions\EditFields;

class UgcTrack extends AbstractUgc
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\UgcTrack::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public function title()
    {
        if ($this->name) {
            return "{$this->name} ({$this->id})";
        } else {
            return "{$this->id}";
        }
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
    ];

    public static function label()
    {
        $label = 'Track';

        return __($label);
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        $fields = parent::fields($request);

        return array_merge($fields, $this->additionalFields($request));
    }

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
            $this->getCodeField('Raw data'),
            $this->getCodeField('Metadata'),
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
     * Get the cards available for the request.
     *
     * @param  Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        $parentActions = parent::actions($request);
        $specificActions = [
            (new EditFields('Validate Resource', ['validated'], $this))->canSee(function () {
                return auth()->user()->hasPermissionTo('validate tracks');
            }),
        ];

        return array_merge($parentActions, $specificActions);
    }

    public static function getExportFields(): array
    {
        return array_merge(parent::getExportFields(), [
            'raw_data->latitude' => __('Latitudine'),
            'raw_data->longitude' => __('Longitudine'),
        ]);
    }
}
