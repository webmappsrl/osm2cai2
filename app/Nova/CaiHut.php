<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Nova\Actions\CacheMiturApi;
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
            Text::make(__('Name'), 'name'),
            Text::make(__('Second Name'), 'second_name'),
            Textarea::make(__('Description'), 'description'),
            Text::make(__('Owner'), 'owner'),
            Number::make(__('Elevation'), 'elevation'),
            BelongsToField::make(__('Region'), 'region', Region::class),
            MapPoint::make(__('Geometry'), 'geometry')->withMeta([
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
            (new CacheMiturApi('CaiHut'))->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Administrator);
            }),
        ];
    }

    /**
     * Determine if the current user can create new resources.
     */
    public static function authorizedToCreate($request)
    {
        return false;
    }
}
