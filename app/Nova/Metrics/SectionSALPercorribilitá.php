<?php

namespace App\Nova\Metrics;

use App\Enums\IssueStatus;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Http\Requests\NovaRequest;

class SectionSALPercorribilitá extends Partition
{

    public function name()
    {
        return 'SAL Stato Percorribilitá';
    }
    protected $hikingRoutes;

    public function __construct(iterable $hikingRoutes = [])
    {
        $this->hikingRoutes = $hikingRoutes;
    }
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $sconosciuto = 0;
        $percorribile = 0;
        $nonPercorribile = 0;
        $percorribileParzialmente = 0;
        $issueStatus = IssueStatus::cases();
        $colors = [
            'percorribile parzialmente' => '#ff9900',
            'percorribile' => '#38761d',
            'non percorribile' => '#ff0000',
            'sconosciuto' => '#666666',
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


        $result = array_combine(array_keys($issueStatus), [$sconosciuto, $percorribile, $nonPercorribile, $percorribileParzialmente]);
        return $this->result($result)->colors($colors);
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return  \DateTimeInterface|\DateInterval|float|int
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'section-s-a-l-percorribilit';
    }
}
