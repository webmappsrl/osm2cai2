<?php

namespace App\Nova;

use Laravel\Nova\Fields\Text;
use App\Helpers\Osm2caiHelper;
use Laravel\Nova\Fields\BelongsTo;
use App\Nova\Actions\CacheMiturApi;
use App\Nova\Filters\EcPoiTypeFIlter;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Actions\CalculateIntersections;

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
            Text::make('Type', 'type')->sortable(),
            BelongsTo::make('User')->sortable()->filterable()->searchable(),
            Text::make('Score', 'score')->displayUsing(function ($value) {
                return Osm2caiHelper::getScoreAsStars($value);
            })->sortable(),
            BelongsTo::make('Region')->sortable()->filterable()->searchable(),
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
            return auth()->user()->hasRole('Administrator');
        });
        $actions[] = (new CacheMiturApi('EcPoi'))->canSee(function () {
            return auth()->user()->hasRole('Administrator');
        })->canRun(function () {
            return auth()->user()->hasRole('Administrator');
        });
        return $actions;
    }
}
