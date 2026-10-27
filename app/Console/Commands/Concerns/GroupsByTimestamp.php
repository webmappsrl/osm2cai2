<?php

namespace App\Console\Commands\Concerns;

use App\Services\UgcDuplicatesByTimestampService;
use Illuminate\Support\Collection;

/**
 * STRATO 2 di oc:8867 — aggancio dell'opzione --by-timestamp a un command di fusione degli UGC.
 *
 * Il command che lo usa dichiara `{--by-timestamp}` nella propria signature e aggiunge ai gruppi del
 * proprio servizio quelli restituiti da groupsByTimestamp(). Qui non c'è logica di fusione: i gruppi
 * hanno la stessa forma di quelli per uuid e passano per lo stesso ciclo e le stesse guardie.
 */
trait GroupsByTimestamp
{
    /**
     * Gruppi di duplicati senza uuid riconosciuti per istante di compilazione; vuoti se l'opzione
     * --by-timestamp non è attiva (è un'inferenza, resta spenta per default).
     *
     * @param  class-string  $modelClass
     */
    protected function groupsByTimestamp(string $modelClass, ?int $appId): Collection
    {
        // Se il command non dichiara {--by-timestamp} nella signature (es. agganciato al command del
        // package dimenticando la riga) il raggruppamento resta spento, invece di esplodere a runtime.
        if (! $this->input->hasOption('by-timestamp') || ! $this->option('by-timestamp')) {
            return collect();
        }

        return UgcDuplicatesByTimestampService::make()->groups($modelClass, $appId);
    }
}
