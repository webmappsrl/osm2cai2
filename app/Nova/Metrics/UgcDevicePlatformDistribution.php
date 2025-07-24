<?php

namespace App\Nova\Metrics;

use App\Models\UgcPoi;
use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

class UgcDevicePlatformDistribution extends Partition
{
    public function calculate(NovaRequest $request): PartitionResult
    {
        $data = UgcPoi::query()
            ->selectRaw("COALESCE(NULLIF(TRIM(properties->'device'->>'platform'), ''), 'null') as device_platform, count(*) as count")
            ->groupBy('device_platform')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'device_platform')
            ->toArray();

        $result = [
            '🍏 iOS' => $data['ios'] ?? 0,
            '🤖 Android' => $data['android'] ?? 0,
            '💻 Platform' => ($data['web'] ?? 0) + ($data['null'] ?? 0),
        ];

        // Mostra solo le etichette con conteggio > 0
        $result = array_filter($result, fn($v) => $v > 0);

        arsort($result);

        return $this->result($result);
    }

    public function name()
    {
        return __('Device Platform');
    }

    public function cacheFor(): DateTimeInterface|null
    {
        return null;
    }
}
