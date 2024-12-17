<?php

namespace App\Nova\Filters;

use Illuminate\Support\Facades\DB;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ClubFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public $name = 'Club';

    /**
     * Apply the filter to the given query.
     *
     * @param  NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->whereHas('clubs', function ($query) use ($value) {
            $query->where('club_id', $value);
        });
    }

    /**
     * Get the filter's available options.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        $clubs = DB::select('select id, name from clubs');
        $options = [];
        foreach ($clubs as $club) {
            $options[$club->name] = $club->id;
        }

        //order options by name
        ksort($options);

        return $options;
    }
}
