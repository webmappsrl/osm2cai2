<?php

namespace App\Nova\Dashboards;

use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class AcquaSorgente extends Dashboard
{
    public function label()
    {
        return 'Riepilogo Acqua Sorgente';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        //get all the ugcPoi with form_id = 'water'
        $ugcPoiWaterCount = \App\Models\UgcPoi::where('form_id', 'water')->count();

        return [
            (new HtmlCard())->view('nova.cards.acqua-sorgente', ['ugcPoiWaterCount' => $ugcPoiWaterCount])->center()->withBasicStyles(),
            (new \App\Nova\Metrics\AcquaSorgenteTrend)->width('1/2'),
        ];
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'acqua-sorgente';
    }
}
