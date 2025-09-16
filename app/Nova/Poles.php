<?php

namespace App\Nova;

use App\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMap;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Poles extends OsmfeaturesResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Poles>
     */
    public static $model = \App\Models\Poles::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'ref';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'ref',
        'osmfeatures_id',
    ];

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        $parentFields = collect(parent::fields($request))
            ->reject(function ($field) {
                return property_exists($field, 'attribute') && $field->attribute === 'geometry';
            })
            ->values()
            ->all();

        return array_merge($parentFields, [
            Text::make('Ref', 'ref')->sortable(),
            FeatureCollectionMap::make(__('geometry')),
        ]);
    }
}
