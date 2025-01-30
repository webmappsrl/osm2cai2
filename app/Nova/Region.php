<?php

namespace App\Nova;

use App\Helpers\Osm2caiHelper;
use App\Nova\Actions\CacheMiturApi;
use App\Nova\Actions\CalculateIntersections;
use App\Nova\Actions\DownloadCsvCompleteAction;
use App\Nova\Actions\DownloadGeojson;
use App\Nova\Actions\DownloadGeojsonCompleteAction;
use App\Nova\Actions\DownloadKml;
use App\Nova\Actions\DownloadShape;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Wm\MapMultiPolygon\MapMultiPolygon;
use Wm\WmPackage\Nova\Actions\ExportTo;

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
        'name' => 'asc',
    ];

    public static function indexQuery(NovaRequest $request, $query)
    {
        if (empty($request->get('orderBy'))) {
            $query->getQuery()->orders = [];

            return $query->orderBy(key(static::$indexDefaultOrder), reset(static::$indexDefaultOrder));
        }

        return $query->ownedBy(auth()->user());
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $cacheKey = "region_{$this->id}_stats";
        $cacheDuration = 60 * 60 * 1; // 1 hour

        $stats = cache()->remember($cacheKey, $cacheDuration, function () {
            $provincesCount = count($this->provinces);

            $areasCount = $this->provinces->sum(function ($province) {
                return count($province->areas);
            });

            $sectorsCount = $this->provinces->sum(function ($province) {
                return $province->areas->sum(function ($area) {
                    return count($area->sectors);
                });
            });

            $hikingRoutes = $this->hikingRoutes()
                ->selectRaw('osm2cai_status, count(*) as count')
                ->groupBy('osm2cai_status')
                ->pluck('count', 'osm2cai_status')
                ->toArray();

            return [
                'provinces' => $provincesCount,
                'areas' => $areasCount,
                'sectors' => $sectorsCount,
                'hiking_routes' => $hikingRoutes,
            ];
        });

        return [
            Text::make('Region', 'name')->sortable(),
            Text::make(__('CAI Code'), 'code')->sortable(),
            Number::make(__('# Province'), function () use ($stats) {
                return $stats['provinces'];
            }),
            Number::make(__('# Aree'), function () use ($stats) {
                return $stats['areas'];
            }),
            Number::make(__('# Settori'), function () use ($stats) {
                return $stats['sectors'];
            }),
            Number::make(__('# 4'), function () use ($stats) {
                return $stats['hiking_routes'][4] ?? 0;
            }),
            Number::make(__('# 3'), function () use ($stats) {
                return $stats['hiking_routes'][3] ?? 0;
            }),
            Number::make(__('# 2'), function () use ($stats) {
                return $stats['hiking_routes'][2] ?? 0;
            }),
            Number::make(__('# 1'), function () use ($stats) {
                return $stats['hiking_routes'][1] ?? 0;
            }),
            Number::make(__('# 0'), function () use ($stats) {
                return $stats['hiking_routes'][0] ?? 0;
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
            })->showInline(),
            (new DownloadShape)->canRun(function ($request) {
                return true;
            })->showInline(),
            (new DownloadKml)->canRun(function ($request) {
                return true;
            })->showInline(),
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
            })->showInline(),
            (new CalculateIntersections('Region'))->canSee(function ($request) {
                return $request->user()->hasRole('Administrator');
            })->canRun(function ($request) {
                return $request->user()->hasRole('Administrator');
            })->showInline(),

        ];
    }
}
