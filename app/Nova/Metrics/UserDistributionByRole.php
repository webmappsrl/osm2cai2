<?php

namespace App\Nova\Metrics;

use App\Enums\UserRole;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class UserDistributionByRole extends Partition
{
    protected $users;

    public function __construct(iterable $users)
    {
        $this->users = $users;
    }

    /**
     * Calculate the value of the metric.
     *
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $formattedResults = [
            'Superadmin' => $this->users[UserRole::Administrator] ?? 0,
            'Referente Nazionale' => $this->users[UserRole::NationalReferent] ?? 0,
            'Referente Regionale' => $this->users[UserRole::RegionalReferent] ?? 0,
            'Referente di Zona' => $this->users[UserRole::LocalReferent] ?? 0,
            'Responsabile di Sezione' => $this->users[UserRole::ClubManager] ?? 0,
            'Responsabile Itinerario' => $this->users[UserRole::ItineraryManager] ?? 0,
            'Guest' => $this->users[UserRole::Guest] ?? 0,
        ];

        return $this->result($formattedResults);
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int
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
        return 'user-distribution-by-role';
    }
}
