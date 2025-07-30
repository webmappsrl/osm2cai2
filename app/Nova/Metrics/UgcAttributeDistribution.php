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

        // Calcolo il totale per la percentuale
        $total = array_sum($normalizedData);
        $others = 0;
        foreach ($normalizedData as $version => $count) {
            $percent = $total > 0 ? ($count / $total) * 100 : 0;
            if ($percent < 1) {
                $others += $count;
                unset($normalizedData[$version]);
            }
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
