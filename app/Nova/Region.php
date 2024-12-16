<?php

namespace App\Nova;

use Laravel\Nova\Panel;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Text;
use App\Helpers\Osm2caiHelper;
use Laravel\Nova\Fields\Number;
use App\Nova\Actions\DownloadKml;
use Laravel\Nova\Fields\DateTime;
use App\Nova\Actions\CacheMiturApi;
use App\Nova\Actions\DownloadShape;
use App\Nova\Actions\DownloadGeojson;
use Wm\MapMultiPolygon\MapMultiPolygon;
use Wm\WmPackage\Nova\Actions\ExportTo;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Actions\DownloadCsvCompleteAction;
use App\Nova\Actions\DownloadGeojsonCompleteAction;

class Region extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Region>
     */
    public static $model = \App\Models\Region::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @return string
     */
    public function title()
    {
        return $this->name;
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'code',
    ];

    public static function label()
    {
        return 'Regioni';
    }

    private static $indexDefaultOrder = [
        'name' => 'asc'
    ];

    public static function indexQuery(NovaRequest $request, $query)
    {
        if (empty($request->get('orderBy'))) {
            $query->getQuery()->orders = [];

            return $query->orderBy(key(static::$indexDefaultOrder), reset(static::$indexDefaultOrder));
        }

        return $query;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $provincesCount = cache()->remember('region_' . $this->id . '_provinces_count', 60 * 60 * 24, function () {
            return count($this->provinces);
        });

        $areasCount = cache()->remember('region_' . $this->id . '_areas_count', 60 * 60 * 24, function () {
            $count = 0;
            foreach ($this->provinces as $province) {
                $count += count($province->areas);
            }
            return $count;
        });

        $sectorsCount = cache()->remember('region_' . $this->id . '_sectors_count', 60 * 60 * 24, function () {
            $count = 0;
            foreach ($this->provinces as $province) {
                foreach ($province->areas as $area) {
                    $count += count($area->sectors);
                }
            }
            return $count;
        });

        $hikingRoutes4Count = cache()->remember('region_' . $this->id . '_hiking_routes_4_count', 60 * 60 * 24, function () {
            return $this->hikingRoutes()->where('osm2cai_status', '=', 4)->count();
        });

        $hikingRoutes3Count = cache()->remember('region_' . $this->id . '_hiking_routes_3_count', 60 * 60 * 24, function () {
            return $this->hikingRoutes()->where('osm2cai_status', '=', 3)->count();
        });

        $hikingRoutes2Count = cache()->remember('region_' . $this->id . '_hiking_routes_2_count', 60 * 60 * 24, function () {
            return $this->hikingRoutes()->where('osm2cai_status', '=', 2)->count();
        });

        $hikingRoutes1Count = cache()->remember('region_' . $this->id . '_hiking_routes_1_count', 60 * 60 * 24, function () {
            return $this->hikingRoutes()->where('osm2cai_status', '=', 1)->count();
        });

        $hikingRoutes0Count = cache()->remember('region_' . $this->id . '_hiking_routes_0_count', 60 * 60 * 24, function () {
            return $this->hikingRoutes()->where('osm2cai_status', '=', 0)->count();
        });

        return [
            Text::make('Region', 'name')->sortable(),
            Text::make(__('CAI Code'), 'code')->sortable(),
            Number::make(__('# Province'), function () use ($provincesCount) {
                return $provincesCount;
            }),
            Number::make(__('# Aree'), function () use ($areasCount) {
                return $areasCount;
            }),
            Number::make(__('# Settori'), function () use ($sectorsCount) {
                return $sectorsCount;
            }),
            Number::make(__('# 4'), function () use ($hikingRoutes4Count) {
                return $hikingRoutes4Count;
            }),
            Number::make(__('# 3'), function () use ($hikingRoutes3Count) {
                return $hikingRoutes3Count;
            }),
            Number::make(__('# 2'), function () use ($hikingRoutes2Count) {
                return $hikingRoutes2Count;
            }),
            Number::make(__('# 1'), function () use ($hikingRoutes1Count) {
                return $hikingRoutes1Count;
            }),
            Number::make(__('# 0'), function () use ($hikingRoutes0Count) {
                return $hikingRoutes0Count;
            }),

            MapMultiPolygon::make('Geometry')->withMeta([
                'center' => ['42.795977075', '10.326813853'],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
            ])->hideFromIndex(),
            Text::make('Osmfeatures ID', function () {
                return Osm2caiHelper::getOpenstreetmapUrlAsHtml($this->osmfeatures_id);
            })->asHtml()->hideFromIndex(),
            DateTime::make('Osmfeatures updated at', 'osmfeatures_updated_at')->hideFromIndex(),
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
        return [
            (new DownloadGeojson)->canRun(function ($request) {
                return true;
            }),
            (new DownloadShape)->canRun(function ($request) {
                return true;
            }),
            (new DownloadKml)->canRun(function ($request) {
                return true;
            }),
            (new DownloadGeojsonCompleteAction)->canRun(function ($request) {
                return true;
            })->showInline(),
            (new DownloadCsvCompleteAction)->canRun(function ($request) {
                return true;
            })->showInline(),
            (new CacheMiturApi('Region'))->canSee(function ($request) {
                return $request->user()->hasRole('Administrator');
            })->canRun(function ($request) {
                return $request->user()->hasRole('Administrator');
            }),

        ];
    }
}
