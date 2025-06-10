<?php

namespace App\Nova;

use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;
use Wm\WmPackage\Nova\Layer as WmNovaLayer;

class Layer extends WmNovaLayer
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Layer::class;

    /**
     * Get the URI key for the resource.
     *
     * @return string
     */
    public static function uriKey()
    {
        return 'layers';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            NovaTabTranslatable::make([
                Text::make(__('Name'), 'name')->required(),
            ]),
            Number::make(__('Rank'), 'rank', function () {
                if (is_array($this->properties) && isset($this->properties['rank'])) {
                    return (int) $this->properties['rank'];
                }

                return $this->rank ?? 0;
            })->onlyOnIndex()->sortable(),
            BelongsTo::make(__('App'), 'appOwner', \Wm\WmPackage\Nova\App::class),
            BelongsTo::make('Owner', 'layerOwner', \App\Nova\User::class)->nullable(),
            Images::make(__('Image'), 'default'),
            PropertiesPanel::make(__('Properties'), 'layer')->collapsible(),
            Panel::make('Ec Tracks', [
                //      LayerFeatures::make('ecTracks', $this->resource, WmEcTrack::class)->hideWhenCreating(),
            ]),
        ];
    }
}
