<?php

namespace App\Nova\Filters;

use App\Enums\SicaiSituazioneEnum;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class SicaiSituazioneFilter extends Filter
{
    public $component = 'select-filter';

    public function __construct()
    {
        $this->name = __('Situation');
    }

    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->whereRaw("properties->'sicai'->>'situazione' = ?", [$value]);
    }

    public function options(NovaRequest $request): array
    {
        return collect(SicaiSituazioneEnum::cases())
            ->mapWithKeys(fn($c) => [$c->value => $c->value])
            ->all();
    }
}
