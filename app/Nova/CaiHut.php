<?php

namespace App\Nova;

use App\Nova\Actions\CacheMiturApi;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo as BelongsToField;
use Laravel\Nova\Fields\Code;
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
    public static $title = 'id';

    public static function label()
    {
        $label = 'Rifugi';

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
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            ID::make('Unico Id')->hideFromIndex(),
            Text::make('Name'),
            Text::make('Second Name'),
            Textarea::make('Description'),
            Text::make('Owner'),
            Number::make('Elevation'),
            BelongsToField::make('Region'),
            MapPoint::make('geometry')->withMeta([
                'center' => [42, 10],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'minZoom' => 8,
                'maxZoom' => 17,
                'defaultZoom' => 13,
            ])->hideFromIndex(),
            Text::make('Aws Cached Data', function () {
                return '<a href="'.$this->getPublicAwsUrl('wmfemitur').'" target="_blank" style="text-decoration:underline;">'.$this->getPublicAwsUrl('wmfemitur').'</a>';
            })->onlyOnDetail()->asHtml(),

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
            (new CacheMiturApi('CaiHut'))->canSee(function () {
                return auth()->user()->hasRole('Administrator');
            })->canRun(function () {
                return auth()->user()->hasRole('Administrator');
            }),
        ];
    }
}
