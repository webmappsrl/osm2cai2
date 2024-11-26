<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;

class Area extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Area>
     */
    public static $model = \App\Models\Area::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name',
        'code',
        'full_code',
    ];

    private static array $indexDefaultOrder = [
        'name' => 'asc'
    ];

    public static function label(): string
    {
        return 'Aree';
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        if (empty($request->get('orderBy'))) {
            $query->getQuery()->orders = [];
            $query->orderBy(key(static::$indexDefaultOrder), reset(static::$indexDefaultOrder));
        }

        return $query->whereHas('users', function ($query) {
            $query->where('users.id', auth()->id());
        });
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make(__('Name'), 'name')->sortable(),
            Text::make(__('Code'), 'code')->sortable(),
            Text::make(__('Full code'), 'full_code')->sortable(),
            Text::make(__('Region'), 'province_id', function () {
                return $this->province->region->name ?? null;
            }),
            Text::make(__('Province'), 'province_id', function () {
                return $this->province->name ?? null;
            }),
            Number::make(__('Sectors'), 'sectors', function () {
                return count($this->sectors) ?? null;
            }),
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
        return [];
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
