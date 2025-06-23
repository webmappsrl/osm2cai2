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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value)
    {
        if ($value == 'null') {
            return $query->whereNull('properties->form->id');
        } else {
            return $query->where('properties->form->id', $value);
        }
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(Request $request)
    {
        // Ottieni il resource corrente dal request
        $resource = $request->newResource();
        $modelClass = $resource::$model;

        // Controlla se ci sono altri filtri applicati
        $filters = $request->get('filters', '');
        $activeFilters = $filters ? json_decode(base64_decode($filters), true) : [];

        // Inizia con una query base
        $query = $modelClass::with('app');

        // Se ci sono altri filtri attivi, applicali per ottenere un sottoinsieme più rilevante
        if (! empty($activeFilters)) {
            // Applica gli altri filtri alla query (escludi questo filtro se presente)
            foreach ($activeFilters as $filterClass => $value) {
                if ($filterClass !== self::class && $value !== null) {
                    // Qui potresti applicare logiche specifiche per filtri noti
                    // Per ora prendiamo comunque il primo record dalla query filtrata
                }
            }
        }

        // Ottieni un'istanza esistente dal database con la relazione app caricata
        $model = $query->first();

        if (! $model || ! $model->app) {
            return [];
        }

        $forms = $model->app->acquisitionForms();

        $formIds = [];
        foreach ($forms as $form) {
            $formIds[$form['name']] = $form['id'];
        }

        return $formIds;
    }
}
