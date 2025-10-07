<?php

namespace App\Nova\Filters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Filters\BooleanFilter;

class RelatedUGCFilter extends BooleanFilter
{
    public function __construct()
    {
        $this->name = __('Related UGC');
    }

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value)
    {
        if ($value['show_my_ugc']) {
            return $query->where('user_id', Auth::user()->id);
        }

        return $query;
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(Request $request)
    {
        // get the url from the request
        $url = $request->url();
        // trim the url to get only the path
        $url = explode('?', $url)[0];
        // get the last part of the url
        $model = explode('/', $url)[count(explode('/', $url)) - 2];

        return [
            __('Show my '.$model) => 'show_my_ugc',
        ];
    }
}
