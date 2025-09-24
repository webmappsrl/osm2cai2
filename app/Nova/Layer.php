<?php

namespace App\Nova;

use App\Models\HikingRoute;
use App\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMap;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Nova\Fields\LayerFeatures\LayerFeatures;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;
use Wm\WmPackage\Nova\Layer as WmNovaLayer;

class Layer extends WmNovaLayer
{
    public static $with = [];

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
                Text::make('name'),
            ]),
            Number::make(__('Rank'), 'rank', function () {
                if (is_array($this->properties) && isset($this->properties['rank'])) {
                    return (int) $this->properties['rank'];
                }

                return $this->rank ?? 0;
            })->onlyOnIndex()->sortable(),
            BelongsTo::make(__('App'), 'appOwner', \Wm\WmPackage\Nova\App::class),
            BelongsTo::make('Owner', 'layerOwner', User::class)
                ->nullable()
                ->searchable(),
            Images::make(__('Image'), 'default'),
            MorphToMany::make(__('Activities'), 'taxonomyActivities', TaxonomyActivity::class),
            FeatureCollectionMap::make(__('geometry'))->onlyOnDetail(),
            PropertiesPanel::makeWithModel(__('Properties'), 'properties', $this, true)->collapsible(),
            LayerFeatures::make('tracks', $this->resource, HikingRoute::class)->hideWhenCreating()->withMeta(['model_class' => HikingRoute::class]),
        ];
    }
}
