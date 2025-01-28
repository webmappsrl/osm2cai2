<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;
use Illuminate\Support\Facades\DB;
use App\Helpers\Nova\DashboardCardsHelper;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class PercorsiFavoriti extends Dashboard
{

    private $cardsService;

    public function __construct()
    {
        $this->cardsService = new DashboardCardsHelper();
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
        return [$this->cardsService->getPercorsiFavoritiDashboardCard()];
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
