<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\PartitionResult;

class UgcAttributeDistributionByLatestUserValue extends UgcAttributeDistribution
{
    /**
     * Costruttore parametrico
     */
    public function __construct(string $label, string $path, string $modelClass)
    {
        parent::__construct($label, $path, $modelClass);
    }

    /**
     * Calculate the value of the metric.
     *
     * Calcolo per utenti univoci: conta utenti basati sull'ultima versione utilizzata
     *
     * Per ogni utente, identifica l'ultima versione dell'app con cui ha creato UGC
     * e conta l'utente solo in quella versione. Questo garantisce che la somma
     * delle fette corrisponda al numero totale di utenti unici.
     */
    public function calculate(NovaRequest $request): PartitionResult
    {
        // Query per ottenere l'ultima versione dell'app per ogni utente
        // DISTINCT ON di PostgreSQL restituisce il primo record per ogni user_id
        // ordinato per updated_at DESC (ultimo UGC creato)
        $latestVersions = $this->modelClass::query()
            ->selectRaw("
                DISTINCT ON (user_id)
                user_id,
                {$this->path} as value
            ")
            ->whereNotNull('user_id')
            ->whereRaw("{$this->path} IS NOT NULL")
            ->orderBy('user_id')
            ->orderByDesc('updated_at')
            ->get();

        // Raggruppa per versione e conta gli utenti univoci
        // Ogni utente viene conteggiato una sola volta nella sua versione più recente
        $data = [];
        foreach ($latestVersions as $record) {
            $value = $record->value;
            if (! isset($data[$value])) {
                $data[$value] = 0;
            }
            $data[$value]++;
        }

        return $this->normalizeAndFormatData($data);
    }
}
