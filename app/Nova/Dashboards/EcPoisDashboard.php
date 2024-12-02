<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\EcPoisScorePartition;
use App\Nova\Metrics\EcPoisTrend;
use App\Nova\Metrics\EcPoisTypePartition;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class EcPoisDashboard extends Dashboard
{
    public function label()
    {
        return 'POIS';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return [
            new EcPoisTrend,
            (new HtmlCard())->view('nova.cards.ec-pois', ['ecPoiCount' => \App\Models\EcPoi::count()])->center()->withBasicStyles(),
            new EcPoisScorePartition,
            new EcPoisTypePartition,
        ];
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'ec-pois';
    }
}
