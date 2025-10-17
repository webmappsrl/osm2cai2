<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;

class Itinerary extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Itinerary::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @return string
     */
    public function title()
    {
        return $this->name;
    }

    public static $perPageViaRelationship = 50;

    public static function label()
    {
        return __('Itineraries');
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            Text::make(__('Name'), 'name')->sortable()->rules('required', 'max:255'),
            Text::make(__('Numero Tappe'), function () {
                return $this->hikingRoutes()->count();
            })->hideWhenCreating()->hideWhenUpdating(),
            Text::make(__('Total KM'), function () {
                $hikingRoutes = $this->hikingRoutes()->get();
                $totalKm = 0;
                foreach ($hikingRoutes as $route) {
                    if ($route->distance_comp !== null) {
                        $totalKm += $route->distance_comp;
                    }
                }

                return round($totalKm, 2);
            })->hideWhenCreating()->hideWhenUpdating(),
            BelongsToMany::make(__('Itineraries'), 'hikingRoutes', HikingRoute::class)->searchable(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(Request $request)
    {
        return [
            (new Actions\GenerateItineraryEdgesAction)->canSee(function ($request) {
                return $request->user()->hasRole('Administrator') || $request->user()->hasRole('Itinerary Manager');
            })->showInline(),
            (new Actions\ImportItinerary)->standalone()->canSee(function ($request) {
                return $request->user()->hasRole('Administrator') || $request->user()->hasRole('Itinerary Manager');
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
