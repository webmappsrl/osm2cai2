<?php

namespace App\Nova;

use App\Nova\Actions\CacheMiturApi;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo as BelongsToField;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\MapPoint\MapPoint;

class CaiHut extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\CaiHut>
     */
    public static $model = \App\Models\CaiHut::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    public static function label()
    {
        $label = 'Huts';

        return __($label);
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

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            Text::make(__('Name')),
            Text::make(__('Second Name')),
            Textarea::make(__('Description')),
            Text::make(__('Owner')),
            Number::make(__('Elevation')),
            BelongsToField::make(__('Region')),
            MapPoint::make(__('Geometry'))->withMeta([
                'center' => [42, 10],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'minZoom' => 8,
                'maxZoom' => 17,
                'defaultZoom' => 13,
            ])->hideFromIndex(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [
            (new CacheMiturApi('CaiHut'))->canSee(function () {
                return auth()->user()->hasRole('Administrator');
            })->canRun(function () {
                return auth()->user()->hasRole('Administrator');
            }),
        ];
    }
}
