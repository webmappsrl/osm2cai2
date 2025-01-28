<?php

namespace App\Nova\Dashboards;

use App\Models\User;
use App\Models\HikingRoute;
use Laravel\Nova\Dashboard;
use Illuminate\Support\Facades\Cache;
use App\Helpers\Nova\DashboardCardsHelper;
use App\Nova\Metrics\IssueStatusPartition;

class Percorribilità extends Dashboard
{
    protected $user;
    private $cardsService;

    public function __construct(User $user = null)
    {
        $this->user = $user;
        $this->cardsService = new DashboardCardsHelper();
    }

    public function label()
    {
        return 'Riepilogo Percorribilità';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return $this->cardsService->getPercorribilitàDashboardCardsData($this->user);
    }



    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'percorribilità';
    }
}
