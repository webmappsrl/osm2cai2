<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class SectionSALPercorsi extends Partition
{

    public function name()
    {
        return 'SAL Percorsi Sezione';
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
        $sda0 = 0;
        $sda1 = 0;
        $sda2 = 0;
        $sda3 = 0;
        $sda4 = 0;
        $statuses = ['SDA0', 'SDA1', 'SDA2', 'SDA3', 'SDA4'];
        $colors = [
            'SDA0' => '#969696',
            'SDA1' => '#FFD23F',
            'SDA2' => '#B43E8F',
            'SDA3' => '#1E3888',
            'SDA4' => '#47AC34',
        ];



        foreach ($this->hikingRoutes as $hr) {
            switch ($hr->osm2cai_status) {
                case 0:
                    $sda0++;
                    break;
                case 1:
                    $sda1++;
                    break;
                case 2:
                    $sda2++;
                case 3:
                    $sda3++;
                    break;
                case 4:
                    $sda4++;
                    break;
            }
        }


        $result = array_combine($statuses, [$sda0, $sda1, $sda2, $sda3, $sda4]);
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
        return 'section-s-a-l-percorsi';
    }
}
