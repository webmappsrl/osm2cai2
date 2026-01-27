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
     * Validates numeric input to prevent integer overflow errors.
     * If the search term is a number that exceeds integer max value,
     * it will only search in text fields (ref, osmfeatures_id) instead of id.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     * @phpstan-ignore-next-line
     */
    protected static function applySearch(Builder $query, string $search): Builder
    {
        $searchTerm = trim($search);

        // Check if search term is numeric (allows integers, including very large ones)
        if (is_numeric($searchTerm) && ctype_digit($searchTerm)) {
            // PostgreSQL integer max value: 2,147,483,647
            $maxInteger = '2147483647';

            // Compare as strings to avoid PHP integer overflow issues
            // If the number exceeds integer max, search only in text fields
            // This prevents "Numeric value out of range" errors
            if (strlen($searchTerm) > strlen($maxInteger) || 
                (strlen($searchTerm) === strlen($maxInteger) && strcmp($searchTerm, $maxInteger) > 0)) {
                // Number is too large for integer type - search only in text fields
                return $query->where(function ($q) use ($searchTerm) {
                    $q->where('ref', 'ilike', "%{$searchTerm}%")
                        ->orWhere('osmfeatures_id', 'ilike', "%{$searchTerm}%");
                });
            }

            // Valid integer value - safe to search in id field as well
            return $query->where(function ($q) use ($searchTerm) {
                $q->where('id', $searchTerm)
                    ->orWhere('ref', 'ilike', "%{$searchTerm}%")
                    ->orWhere('osmfeatures_id', 'ilike', "%{$searchTerm}%");
            });
        }

        // For non-numeric search, search only in text fields (ref and osmfeatures_id)
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('ref', 'ilike', "%{$searchTerm}%")
                ->orWhere('osmfeatures_id', 'ilike', "%{$searchTerm}%");
        });
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
