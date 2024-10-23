<?php

namespace App\Nova\Filters;

use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;

class UgcFormIdFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public $name = 'Form ID';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value)
    {
        if ($value == 'null')
            return $query->whereNull('form_id');
        else
            return $query->where('form_id', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function options(Request $request)
    {
        //select all the form_id from the ugc_pois table distinct
        $formIds = [
            'Sentieristica' => 'paths',
            'Segnalazione Problemi' => 'report',
            'Punti di Interesse' => 'poi',
            'Acqua Sorgente' => 'water',
            'Segni dell\'uomo' => 'signs',
            'Aree Archeologiche' => 'archaeological_area',
            'Siti Archeologici' => 'archaeological_site',
            'Siti Geologici' => 'geological_site',
        ];

        return $formIds;
    }
}
