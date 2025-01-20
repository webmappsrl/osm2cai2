<?php

namespace App\Nova;

use App\Nova\Filters\RegionFilter;
use Illuminate\Http\Request;
use Imumz\Nova4FieldMap\Nova4FieldMap;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\MapMultiPolygon\MapMultiPolygon;
use Laravel\Nova\Fields\BelongsToMany;

class MountainGroups extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\MountainGroups>
     */
    public static $model = \App\Models\MountainGroups::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @return string
     */
    public function title()
    {
        return $this->name;
    }

    public static function label()
    {
        return __('Gruppi Montuosi');
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
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            Text::make(__('Nome'), 'name')->sortable(),
            Textarea::make(__('Descrizione'), 'description')->hideFromIndex(),
            MapMultiPolygon::make('Geometry')->withMeta([
                'center' => ['42.795977075', '10.326813853'],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
            ])->hideFromIndex(),
            BelongsToMany::make(__('Regioni'), 'regions', Region::class)
                ->searchable(),
            Text::make(__('POI Generico'), function () {
                return $this->ecPois->count();
            })->sortable(),
            Text::make(__('POI Rifugio'), function () {
                return $this->caiHuts->count();
            })->sortable(),
            Text::make(__('Percorsi POI Totali'), function () {
                return $this->ecPois->count() + $this->caiHuts->count();
            })->sortable(),
            Text::make(__('Attivitá o Esperienze'), function () {
                return $this->hikingRoutes->count();
            })->sortable(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            new RegionFilter(),
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
