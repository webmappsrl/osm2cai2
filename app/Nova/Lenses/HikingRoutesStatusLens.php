<?php

namespace App\Nova\Lenses;

use App\Nova\Filters\AreaFilter;
use App\Nova\Filters\ClubFilter;
use App\Nova\Filters\ProvinceFilter;
use App\Nova\Filters\RegionFilter;
use App\Nova\Filters\SectorFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Lenses\Lens;

class HikingRoutesStatusLens extends Lens
{
    public $name = 'SDA0';

    public static $sda = 0;

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param LensRequest $request
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    public static function query(LensRequest $request, $query)
    {
        if (auth()->user()->hasRole('Regional Referent')) {
            $value = auth()->user()->region->id;

            return $request->withOrdering($request->withFilters(
                $query->where('osm2cai_status', static::$sda)
                    ->whereHas('regions', function ($query) use ($value) {
                        $query->where('region_id', $value);
                    })
            ));
        } else {
            return $request->withOrdering($request->withFilters(
                $query->where('osm2cai_status', static::$sda)
            ));
        }
    }

    /**
     * Get the fields available to the lens.
     *
     * @param Request $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            Text::make('Regioni', function () {
                $val = 'ND';
                if (Arr::accessible($this->regions)) {
                    if (count($this->regions) > 0) {
                        $val = implode(', ', $this->regions->pluck('name')->toArray());
                    }
                }

                return $val;
            })->onlyOnIndex(),
            Text::make('Province', function () {
                $val = 'ND';
                if (Arr::accessible($this->provinces)) {
                    if (count($this->provinces) > 0) {
                        $val = implode(', ', $this->provinces->pluck('name')->toArray());
                    }
                }

                return $val;
            })->onlyOnIndex(),
            Text::make('Aree', function () {
                $val = 'ND';
                if (Arr::accessible($this->areas)) {
                    if (count($this->areas) > 0) {
                        $val = implode(', ', $this->areas->pluck('name')->toArray());
                    }
                }

                return $val;
            })->onlyOnIndex(),
            Text::make('Settori', function () {
                $val = 'ND';
                if (Arr::accessible($this->sectors)) {
                    if (count($this->sectors) > 0) {
                        $val = implode(', ', $this->sectors->pluck('name')->toArray());
                    }
                }

                return $val;
            })->onlyOnIndex(),
            Text::make('REF', 'ref')->onlyOnIndex(),
            Text::make('Cod. REI', 'ref_REI')->onlyOnIndex(),
            Text::make('Ultima ricognizione', 'survey_date')->onlyOnIndex(),
            Number::make('STATO', 'osm2cai_status')->sortable()->onlyOnIndex(),
            Number::make('OSMID', 'relation_id')->onlyOnIndex(),

        ];
    }

    /**
     * Get the cards available on the lens.
     *
     * @param Request $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the lens.
     *
     * @param Request $request
     * @return array
     */
    public function filters(Request $request)
    {
        if (auth()->user()->hasRole('Regional Referent')) {
            return [
                (new ProvinceFilter()),
                (new AreaFilter()),
                (new SectorFilter()),
                (new ClubFilter()),
            ];
        } else {
            return [
                (new RegionFilter()),
                (new ProvinceFilter()),
                (new AreaFilter()),
                (new SectorFilter()),
                (new ClubFilter()),
            ];
        }
    }

    /**
     * Get the actions available on the lens.
     *
     * @param Request $request
     * @return array
     */
    public function actions(Request $request)
    {
        return parent::actions($request);
    }

    /**
     * Get the URI key for the lens.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'hiking-routes-status-lens';
    }
}

class HikingRoutesStatus0Lens extends HikingRoutesStatusLens
{
    public static $sda = 0;

    public function __construct($resource = null)
    {
        $this->name = auth()->user()->hasRole('Regional Referent') ? 'SDA0 '.auth()->user()->region->name : 'SDA0';
        parent::__construct($resource);
    }

    public function uriKey()
    {
        return 'hiking-routes-status-0-lens';
    }
}

class HikingRoutesStatus1Lens extends HikingRoutesStatusLens
{
    public static $sda = 1;

    public function __construct($resource = null)
    {
        $this->name = auth()->user()->hasRole('Regional Referent') ? 'SDA1 '.auth()->user()->region->name : 'SDA1';
        parent::__construct($resource);
    }

    public function uriKey()
    {
        return 'hiking-routes-status-1-lens';
    }
}

class HikingRoutesStatus2Lens extends HikingRoutesStatusLens
{
    public static $sda = 2;

    public function __construct($resource = null)
    {
        $this->name = auth()->user()->hasRole('Regional Referent') ? 'SDA2 '.auth()->user()->region->name : 'SDA2';
        parent::__construct($resource);
    }

    public function uriKey()
    {
        return 'hiking-routes-status-2-lens';
    }
}

class HikingRoutesStatus3Lens extends HikingRoutesStatusLens
{
    public static $sda = 3;

    public function __construct($resource = null)
    {
        $this->name = auth()->user()->hasRole('Regional Referent') ? 'SDA3 '.auth()->user()->region->name : 'SDA3';
        parent::__construct($resource);
    }

    public function uriKey()
    {
        return 'hiking-routes-status-3-lens';
    }
}

class HikingRoutesStatus4Lens extends HikingRoutesStatusLens
{
    public static $sda = 4;

    public function __construct($resource = null)
    {
        $this->name = auth()->user()->hasRole('Regional Referent') ? 'SDA4 '.auth()->user()->region->name : 'SDA4';
        parent::__construct($resource);
    }

    public function uriKey()
    {
        return 'hiking-routes-status-4-lens';
    }
}
