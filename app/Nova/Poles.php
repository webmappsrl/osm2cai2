<?php

namespace App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Osm2cai\SignageArrows\SignageArrows;
use Wm\WmPackage\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMap;

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
     * Apply search to the query.
     *
     * Extends parent search to include 'ref' field and handle integer overflow.
     * Validates numeric input to prevent integer overflow errors.
     * If the search term is a number that exceeds integer max value,
     * parent will handle it appropriately (skip id search for very large numbers).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     * @phpstan-ignore-next-line
     */
    protected static function applySearch(Builder $query, string $search): Builder
    {
        // Apply parent search (handles osmfeatures_id, id, name with proper logic)
        $query = parent::applySearch($query, $search);

        $searchTerm = trim($search);
        // Also search in ref field
        return $query->orWhere('ref', 'ilike', "%{$searchTerm}%");
    }

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
            Text::make(__('Ref'), 'ref')->sortable(),
            FeatureCollectionMap::make(__('Geometry'), 'geometry'),
            SignageArrows::make(__('Segnaletica'), 'properties.signage'),
        ]);
    }
}
