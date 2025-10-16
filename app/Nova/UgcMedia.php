<?php

namespace App\Nova;

use App\Nova\Filters\RelatedUGCFilter;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Wm\MapPoint\MapPoint;

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
        $label = 'Images';

        return __($label);
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            DateTime::make(__('Updated At'))
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Text::make(__('Geohub ID'), 'geohub_id')
                ->onlyOnDetail(),
            Text::make(__('Name'), 'name')
                ->sortable(),
            Textarea::make(__('Description'), 'description'),
            BelongsTo::make(__('User'), 'user', User::class)
                ->searchable()
                ->sortable()
                ->nullable(),
            Text::make(__('Media'), function () {
                if ($this->model() instanceof \App\Models\UgcMedia) {
                    $url = $this->getUrl();
                    if (! $url) {
                        return '/';
                    }

                    return <<<HTML
                        <a href='{$url}' target='_blank'>
                            <img src='{$url}' style='max-width: 100px; max-height: 100px; border: 1px solid #ccc; border-radius: 10%; padding: 2px;' alt='Thumbnail'>
                        </a>
                        HTML;
                }
            })->asHtml(),
            BelongsTo::make(__('Ugc Poi'), 'ugcPoi', UgcPoi::class)
                ->searchable()
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            BelongsTo::make(__('Ugc Track'), 'ugcTrack', UgcTrack::class)
                ->searchable()
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            Text::make(__('Tassonomie Where'), 'taxonomy_wheres')
                ->sortable(),
            Text::make(__('Relative URL'), 'relative_url')
                ->hideFromIndex()
                ->displayUsing(function ($value) {
                    $url = $this->getUrl();
                    if (! $url) {
                        return '/';
                    }

                    return "<a href='{$url}' target='_blank'>{$url}</a>";
                })
                ->asHtml()
                ->required(),
            MapPoint::make(__('Map'), 'geometry')->withMeta([
                'center' => [43.7125, 10.4013],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'minZoom' => 8,
                'maxZoom' => 14,
                'defaultZoom' => 10,
                'defaultCenter' => [43.7125, 10.4013],
            ])->onlyOnDetail(),
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
        return [
            (new RelatedUGCFilter),
        ];
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
        return [];
    }
}
