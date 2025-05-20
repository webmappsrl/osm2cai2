<?php

namespace App\Nova;

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
        'id', 'ref', 'osmfeatures_id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return array_merge(parent::fields($request), [
            Text::make('Ref', 'ref')->sortable(),
        ]);
    }
}
