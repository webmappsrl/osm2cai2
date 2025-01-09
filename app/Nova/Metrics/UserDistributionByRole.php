<?php

namespace App\Nova\Metrics;

use App\Models\User;
use Illuminate\Support\Facades\DB;
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
     * @param  NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $formattedResults = [
            'Superadmin' => $this->users['Administrator'] ?? 0,
            'Referente Nazionale' => $this->users['National Referent'] ?? 0,
            'Referente Regionale' => $this->users['Regional Referent'] ?? 0,
            'Referente di Zona' => $this->users['Local Referent'] ?? 0,
            'Responsabile di Sezione' => $this->users['Club Manager'] ?? 0,
            'Responsabile Itinerario' => $this->users['Itinerary Manager'] ?? 0,
            'Guest' => $this->users['Guest'] ?? 0
        ];

        return $this->result($formattedResults);
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return  \DateTimeInterface|\DateInterval|float|int
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
