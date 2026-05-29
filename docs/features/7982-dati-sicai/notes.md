> Ticket: oc:7982

# Notes — Dati SICAI: Fix valori campi DEM nell'export Excel

## Deviazioni dal piano

- Il piano prevedeva due command separati (cleanup + fix). Durante la Reverse Interaction è emerso che un unico command idempotente è più pulito: il command esistente è stato esteso per coprire entrambi i casi.
- Il perimetro è stato allargato durante la pianificazione: inizialmente solo `app_id=2, layer_id=6`, poi esteso a tutti i record `app_id=2` su indicazione dell'utente.

## Bug trovati

- Nessuno in fase di implementazione.

## Decisioni

- `safeArray()` aggiunto come metodo privato nel command (duplicato da `HasDemClassification`) per evitare dipendenze dal trait Nova in un Artisan command.
- La condizione `osmid !== null` prima di leggere `osm_data` è obbligatoria per replicare esattamente `classifyField` — senza questo check i record senza osmid userebbero erroneamente i valori OSM.
- `saveQuietly()` confermato per evitare di triggerare observer e job in coda.

## Follow-up

- 10 record (IDs: 29246, 29455, 29549, 29660, 29721, 29749, 30037, 30039, 30095, 30121) non hanno nessuna sorgente DEM/OSM/manual — i campi top-level sono stati impostati a `null`. Ricalcolare il DEM per questi record con il job apposito.
- Fix dell'`EcTrackExcelExporter` in `wm-package` per usare `classifyField` tracciato in oc:7984 — risolverebbe la root cause strutturale e renderebbe l'exporter indipendente dallo stato dei valori top-level.
