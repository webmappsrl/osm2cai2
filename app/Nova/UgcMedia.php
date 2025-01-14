<?php

namespace App\Nova;

use App\Nova\Filters\RelatedUGCFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Webmapp\WmEmbedmapsField\WmEmbedmapsField;
use Wm\MapPoint\MapPoint;
use Wm\MapPointNova3\MapPointNova3;

class UgcMedia extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\UgcMedia::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public function title()
    {
        if ($this->name) {
            return "{$this->name} ({$this->id})";
        } else {
            return "{$this->id}";
        }
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

    public static function label()
    {
        $label = 'Immagini';

        return __($label);
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            DateTime::make('Updated At')
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Text::make('Geohub ID', 'geohub_id')
                ->onlyOnDetail(),
            Text::make('Nome', 'name')
                ->sortable(),
            Textarea::make('Descrizione', 'description'),
            BelongsTo::make('User', 'user')
                ->searchable()
                ->sortable()
                ->nullable(),
            Text::make('Media', function () {
                if ($this->model() instanceof \App\Models\UgcMedia) {
                    return <<<HTML
                    <a href='{$this->getUrl()}' target='_blank'>
                        <img src='{$this->getUrl()}' style='max-width: 100px; max-height: 100px; border: 1px solid #ccc; border-radius: 10%; padding: 2px;' alt='Thumbnail'>
                    </a>
                    HTML;
                }
            })->asHtml(),
            BelongsTo::make('Ugc Poi', 'ugc_poi')
                ->searchable()
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            BelongsTo::make('Ugc Track', 'ugc_track')
                ->searchable()
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            Text::make('Tassonomie Where', 'taxonomy_wheres')
                ->sortable(),
            Text::make('Relative URL', 'relative_url')
                ->hideFromIndex()
                ->displayUsing(function ($value) {
                    return <<<HTML
                    <a href='{$this->getUrl()}' target='_blank'>{$this->getUrl()}</a>
                    HTML;
                })
                ->asHtml()
                ->required(),
            MapPoint::make('geometry')->withMeta([
                'center' => [43.7125, 10.4013],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'minZoom' => 8,
                'maxZoom' => 14,
                'defaultZoom' => 10,
                'defaultCenter' => [43.7125, 10.4013],
            ])->hideFromIndex(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [
            (new RelatedUGCFilter()),
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [];
    }
}
