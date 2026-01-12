<?php

namespace App\Nova\Metrics;

use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

class UgcAttributeDistribution extends Partition
{
    /**
     * Etichetta visualizzata sulla metrica
     */
    protected string $customLabel;

    /**
     * Path SQL dell'attributo da contare (es: properties->'device'->>'appVersion')
     */
    protected string $path;

    /**
     * Classe del modello da utilizzare
     */
    protected string $modelClass;

    /**
     * Costruttore parametrico
     */
    public function __construct(string $label, string $path, string $modelClass)
    {
        parent::__construct();
        $this->customLabel = $label;
        $this->path = $path;
        $this->modelClass = $modelClass;
    }

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): PartitionResult
    {
        $data = $this->modelClass::query()
            ->selectRaw("{$this->path} as value, count(*) as count")
            ->groupBy('value')
            ->get()
            ->pluck('count', 'value')
            ->toArray();

        return $this->normalizeAndFormatData($data);
    }

    /**
     * Normalizza e formatta i dati per il risultato
     */
    protected function normalizeAndFormatData(array $data): PartitionResult
    {
        // Sostituisco chiavi null o vuote con 'No Attribute'
        $normalizedData = [];
        foreach ($data as $key => $count) {
            $label = (is_null($key) || $key === '' || $key === false) ? 'No Attribute' : $key;
            if (isset($normalizedData[$label])) {
                $normalizedData[$label] += $count;
            } else {
                $normalizedData[$label] = $count;
            }
        }

        // Ordina per conteggio decrescente
        arsort($normalizedData);

        // Raggruppa versioni con pochi utenti in "Others"
        // Usa una soglia assoluta invece della percentuale, più appropriata per il conteggio utenti
        $threshold = 5; // Versioni con meno di 5 utenti vengono raggruppate
        $others = 0;
        $keysToRemove = [];

        foreach ($normalizedData as $version => $count) {
            if ($count < $threshold) {
                $others += $count;
                $keysToRemove[] = $version;
            }
        }

        // Rimuovi le chiavi dopo l'iterazione per evitare problemi
        foreach ($keysToRemove as $key) {
            unset($normalizedData[$key]);
        }

        if ($others > 0) {
            $normalizedData['Others'] = $others;
        }

        return $this->result($normalizedData);
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     */
    public function cacheFor(): ?DateTimeInterface
    {
        return null;
    }

    /**
     * Get the name of the metric.
     */
    public function name()
    {
        return __($this->customLabel);
    }
}
