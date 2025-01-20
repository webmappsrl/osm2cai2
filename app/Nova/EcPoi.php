<?php

namespace App\Nova;

use App\Helpers\Osm2caiHelper;
use App\Nova\Actions\CacheMiturApi;
use App\Nova\Actions\CalculateIntersections;
use App\Nova\Filters\EcPoiTypeFIlter;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Exporters\ModelExporter;
use Wm\WmPackage\Nova\Actions\ExportTo;

class EcPoi extends OsmfeaturesResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\EcPoi>
     */
    public static $model = \App\Models\EcPoi::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'type',
        'osmfeatures_id',
    ];

    public static function label()
    {
        $label = 'Punti di Interesse';

        return __($label);
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return array_merge(parent::fields($request), [
            Text::make(__('Type'), 'type')->sortable(),
            BelongsTo::make(__('User'), 'user')->sortable()->filterable()->searchable(),
            Text::make(__('Score'), 'score')->displayUsing(function ($value) {
                return Osm2caiHelper::getScoreAsStars($value);
            })->sortable()->hideWhenCreating()->hideWhenUpdating(),
            BelongsTo::make(__('Region'), 'region')->sortable()->filterable()->searchable(),
        ]);
    }

    public function filters(NovaRequest $request)
    {
        $filters = parent::filters($request);
        $filters[] = (new EcPoiTypeFIlter);

        return $filters;
    }

    public function actions(NovaRequest $request)
    {
        $actions = parent::actions($request);
        $actions[] = (new CalculateIntersections('EcPoi'))->canSee(function () {
            return auth()->user()->hasRole('Administrator');
        })->canRun(function () {
            return true;
        });
        $actions[] = (new CacheMiturApi('EcPoi'))->canSee(function () {
            return auth()->user()->hasRole('Administrator');
        })->canRun(function () {
            return true;
        });
        $actions[] = (new ExportTo(
            $this->getExportColumns(),
            [],
            'ec-poi',
            ModelExporter::DEFAULT_STYLE
        ))->canSee(function () {
            return true;
        })->canRun(function () {
            return true;
        });

        return $actions;
    }

    private function getExportColumns()
    {
        return [
            'id' => 'ID',
            'osmfeatures_data.properties.osm_id' => 'OSM ID',
            'osmfeatures_data.properties.osm_type' => 'OSM Type',
            'osmfeatures_data.properties.osm_url' => 'OSM URL',
            'osmfeatures_data.properties.name' => 'Name',
            'osmfeatures_data.properties.class' => 'Class',
            'osmfeatures_data.properties.elevation' => 'Elevation',
            'osmfeatures_data.properties.wikipedia' => 'Wikipedia',
            'osmfeatures_data.properties.wikidata' => 'Wikidata',
            'osmfeatures_data.properties.wikimedia_commons' => 'Wikimedia Commons',
            'osmfeatures_data.properties.score' => 'Score',
        ];
    }
}
