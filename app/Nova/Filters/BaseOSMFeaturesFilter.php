<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

abstract class BaseOSMFeaturesFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if ($value === $this->getEmptyOptionValue()) {
            return $this->applyEmpty($request, $query);
        }

        return $this->applyValue($request, $query, $value);
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [
            $this->getEmptyOptionLabel() => $this->getEmptyOptionValue(),
        ] + $this->getEntityOptions($request);
    }

    /**
     * Label for the "empty" option (e.g. "Senza regione").
     */
    abstract protected function getEmptyOptionLabel(): string;

    /**
     * Value for the "empty" option (e.g. "no_region").
     */
    abstract protected function getEmptyOptionValue(): string;

    /**
     * Entity options for the select (label => value).
     *
     * @return array<string, mixed>
     */
    abstract protected function getEntityOptions(NovaRequest $request): array;

    /**
     * Apply filter for records without the attribute set.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract protected function applyEmpty(NovaRequest $request, $query);

    /**
     * Apply filter for a specific attribute value.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract protected function applyValue(NovaRequest $request, $query, $value);
}
