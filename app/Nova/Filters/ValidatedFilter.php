<?php

namespace App\Nova\Filters;

use App\Enums\ValidatedStatusEnum;
use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;

class ValidatedFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public function __construct()
    {
        $this->name = __('Validation Status');
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
        return $query->where('validated', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(Request $request)
    {
        return [
            '✅ '.__('Valid') => ValidatedStatusEnum::VALID->value,
            '❌ '.__('Invalid') => ValidatedStatusEnum::INVALID->value,
            '⏳ '.__('Not Validated') => ValidatedStatusEnum::NOT_VALIDATED->value,
        ];
    }
}
