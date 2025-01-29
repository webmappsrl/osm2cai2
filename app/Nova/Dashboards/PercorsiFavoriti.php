<?php

namespace App\Nova\Dashboards;

use App\Helpers\Nova\DashboardCardsHelper;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class PercorsiFavoriti extends Dashboard
{
    private $cardsHelper;

    public function __construct()
    {
        $this->cardsHelper = new DashboardCardsHelper();
    }

    public function label()
    {
        return 'Percorsi Favoriti';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return [$this->cardsHelper->getPercorsiFavoritiDashboardCard()];
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'percorsi-favoriti';
    }
}
