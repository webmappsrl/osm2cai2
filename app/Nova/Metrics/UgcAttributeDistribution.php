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
     * Se true, conta utenti univoci basati sull'ultima versione utilizzata
     */
    protected bool $countUniqueUsers = false;

    /**
     * Costruttore parametrico
     */
    public function __construct(string $label, string $path, string $modelClass, bool $countUniqueUsers = false)
    {
        parent::__construct();
        $this->customLabel = $label;
        $this->path = $path;
        $this->modelClass = $modelClass;
        $this->countUniqueUsers = $countUniqueUsers;
    }

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): PartitionResult
    {
        if ($this->countUniqueUsers) {
            return $this->calculateUniqueUsers();
        }

        return $this->calculateStandard();
    }

    /**
     * Calcolo standard: conta tutte le occorrenze
     */
    protected function calculateStandard(): PartitionResult
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
     * Calcolo per utenti univoci: conta utenti basati sull'ultima versione utilizzata
     *
     * Per ogni utente, identifica l'ultima versione dell'app con cui ha creato UGC
     * e conta l'utente solo in quella versione. Questo garantisce che la somma
     * delle fette corrisponda al numero totale di utenti unici.
     */
    protected function calculateUniqueUsers(): PartitionResult
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
