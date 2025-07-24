<?php

namespace App\Nova\Metrics;

use App\Models\UgcPoi;
use App\Enums\ValidatedStatusEnum;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class UgcValidatedStatusDistribution extends Partition
{
    public function calculate(NovaRequest $request)
    {
        // Conta i POI per ogni stato di validazione
        $data = UgcPoi::query()
            ->select('validated', DB::raw('count(*) as count'))
            ->groupBy('validated')
            ->pluck('count', 'validated')
            ->toArray();
        // Mappa value => label
        $labels = [
            ValidatedStatusEnum::VALID->value => __('Valid'),
            ValidatedStatusEnum::INVALID->value => __('Invalid'),
            ValidatedStatusEnum::NOT_VALIDATED->value => __('Not Validated'),
        ];
        // Mappa value => emoji
        $emojis = [
            ValidatedStatusEnum::VALID->value => '✅',
            ValidatedStatusEnum::INVALID->value => '❌',
            ValidatedStatusEnum::NOT_VALIDATED->value => '⏳',
        ];
        // Costruisce array emoji + label => count
        $result = [];
        foreach ($labels as $value => $label) {
            $result[$emojis[$value] . ' ' . $label] = $data[$value] ?? 0;
        }

        return $this->result($result);
    }

    /**
     * Get the URI key for the metric.
     */
    public function uriKey(): string
    {
        return 'ugc-validated-status-distribution';
    }

    /**
     * Get the name of the metric.
     */
    public function name()
    {
        return __('Validated Status');
    }
}
