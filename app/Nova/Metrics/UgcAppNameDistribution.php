<?php

namespace App\Nova\Metrics;

use App\Models\UgcPoi;
use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;
use Wm\WmPackage\Models\App;

class UgcAppNameDistribution extends Partition
{
    public function calculate(NovaRequest $request): PartitionResult
    {
        $data = UgcPoi::query()
            ->selectRaw("COALESCE((properties->'app'->>'id'), 'null') as app_id, count(*) as count")
            ->groupByRaw("COALESCE((properties->'app'->>'id'), 'null')")
            ->orderByDesc('count')
            ->get();

        $counts = $data->pluck('count', 'app_id')->toArray();

        $result = [];
        foreach ($counts as $appId => $count) {
            if ($appId === 'null' || $appId === null || $appId === '') {
                $name = 'Unknown';
            } else {
                $app = App::find($appId);
                $name = $app ? ($app->name ?? $app->id) : $appId;
            }
            $result[$name] = $count;
        }

        $result = array_filter($result, fn($v) => $v > 0);
        arsort($result);

        return $this->result($result);
    }

    public function name()
    {
        return __('App Name');
    }

    public function cacheFor(): DateTimeInterface|null
    {
        return null;
    }
}
