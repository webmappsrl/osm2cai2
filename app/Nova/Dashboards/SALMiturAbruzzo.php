<?php

namespace App\Nova\Dashboards;

use App\Models\Region;
use Laravel\Nova\Card;
use Laravel\Nova\Dashboard;
use Illuminate\Support\Facades\DB;
use Ericlagarda\NovaTextCard\TextCard;
use App\Helpers\Nova\DashboardCardsHelper;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class SALMiturAbruzzo extends Dashboard
{
    private $cardsService;

    public function __construct()
    {
        $this->cardsService = new DashboardCardsHelper();
    }

    public function label()
    {
        return 'Riepilogo MITUR-Abruzzo';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {

        return $this->cardsService->getSALMiturAbruzzoDashboardCards();
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'sal-mitur-abruzzo';
    }
}
