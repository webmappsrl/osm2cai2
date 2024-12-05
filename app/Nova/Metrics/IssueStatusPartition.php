<?php

namespace App\Nova\Metrics;

use App\Enums\IssuesStatusEnum;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class IssueStatusPartition extends Partition
{
    protected $hikingRoutes;

    public function __construct(iterable $hikingRoutes, string $name, string $uriKey)
    {
        $this->hikingRoutes = $hikingRoutes;
        $this->name = $name;
        $this->uriKey = $uriKey;
    }

    /**
     * Calculate the value of the metric.
     *
     * @param  NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $sconosciuto = 0;
        $percorribile = 0;
        $nonPercorribile = 0;
        $percorribileParzialmente = 0;
        $issueStatus = IssuesStatusEnum::cases();

        $colors = [
            'sconosciuto' => '#666666',
            'percorribile' => '#38761d',
            'non percorribile' => '#ff0000',
            'percorribile parzialmente' => '#ff9900',
        ];

        foreach ($this->hikingRoutes as $hr) {
            switch ($hr->issues_status) {
                case 'sconosciuto':
                    $sconosciuto++;
                    break;
                case 'percorribile':
                    $percorribile++;
                    break;
                case 'non percorribile':
                    $nonPercorribile++;
                case 'percorribile parzialmente':
                    $percorribileParzialmente++;
                    break;
            }
        }
        $issueStatus = array_map(fn($status) => $status->value, $issueStatus);

        $result = array_combine(array_values($issueStatus), [$sconosciuto, $percorribile, $nonPercorribile, $percorribileParzialmente]);

        return $this->result($result)->colors($colors);
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return  \DateTimeInterface|\DateInterval|float|int
     */
    public function cacheFor()
    {
        return now()->addDay();
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return $this->uriKey;
    }

    public function name()
    {
        return $this->name;
    }
}
