<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\IssueLastUpdatePerMonth;
use App\Nova\Metrics\ValidatedHrPerMonth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class Utenti extends Dashboard
{
    public function label()
    {
        return 'Riepilogo utenti';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $users = Cache::remember('users', 60, function () {
            return \App\Models\User::all();
        });

        $mostActiveUsers = Cache::remember('mostActiveUsers', 60, function () {
            return DB::select("
                SELECT u.id AS user_id, u.name AS user_name, COUNT(DISTINCT hr.id) AS numero_validazioni
                FROM users u
                JOIN hiking_routes hr ON u.id = hr.validator_id
                WHERE hr.osm2cai_status = '4'
                GROUP BY u.id, u.name
                ORDER BY numero_validazioni DESC
                LIMIT 5
            ");
        });

        return [
            new \App\Nova\Metrics\TotalUsers,
            new \App\Nova\Metrics\UserDistributionByRole($users),
            new \App\Nova\Metrics\UserDistributionByRegion($users),
            (new HtmlCard())
                ->width('1/2')
                ->view('nova.cards.most-active-users', ['users' => $mostActiveUsers])
                ->withBasicStyles(),
            (new ValidatedHrPerMonth()),
            (new IssueLastUpdatePerMonth()),
        ];
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'utenti';
    }
}
