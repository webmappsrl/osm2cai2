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
        // Mappa i ruoli ai loro nomi in italiano per la visualizzazione
        $roleLabels = [
            UserRole::Administrator->value => 'Superadmin',
            UserRole::NationalReferent->value => 'Referente Nazionale',
            UserRole::RegionalReferent->value => 'Referente Regionale',
            UserRole::LocalReferent->value => 'Referente di Zona',
            UserRole::ClubManager->value => 'Responsabile di Sezione',
            UserRole::ItineraryManager->value => 'Responsabile Itinerario',
            UserRole::Guest->value => 'Guest',
            UserRole::Contributor->value => 'Contributore',
            UserRole::Editor->value => 'Editore',
            UserRole::Author->value => 'Autore',
            UserRole::Validator->value => 'Validatore',
        ];

        $formattedResults = [];
        foreach ($roleLabels as $roleValue => $roleLabel) {
            $formattedResults[$roleLabel] = $this->users[$roleValue] ?? 0;
        }

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
