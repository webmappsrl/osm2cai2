# Calcolo della portata delle sorgenti

## Come funziona oggi

La portata (L/s) delle schede Acqua Sorgente (`SourceSurvey`, form `water`) si calcola come volume / tempo solo se *Flow Rate Validation* (`water_flow_rate_validated`) è `valid`; altrimenti vale `N/A`. Il campo che conta è questo, non la validazione generale della scheda (`validated`, *Validation Status*).

- `UgcPoi::computeFlowRate()` fa il calcolo senza salvare. `UgcPoi::calculateFlowRate()` lo richiama, scrive il risultato in `raw_data.flow_rate` e fa `saveQuietly()`.
- Volume e tempo si leggono da `properties.form.range_volume` / `range_time`; se la chiave manca o è vuota, da `raw_data`. Le schede inviate dall'app tramite `wm-package` hanno i dati solo in `properties.form`.
- I valori sono testo libero scritto dai rilevatori. Li interpreta `App\Services\FlowRateParser`: accetta `,` e `.` come separatore decimale, le unità più comuni (`l`, `lt`, `litri`, `ml`; `s`, `secondi`, `"`, `''`, `'`, `™`) e i formati da cronometro `mm:ss(.cc)` e `mm ss cc`. Tutto il resto dà `N/A`.
- I casi riconosciuti sono elencati nei data provider di `tests/Unit/Services/FlowRateParserTest.php`, costruiti su tutti i valori non numerici presenti nel DB. Un caso nuovo si aggiunge lì, con il risultato atteso.

Vincoli da sapere:

- Il campo Nova *Flow Rate L/s* chiama `calculateFlowRate()` a ogni visualizzazione, anche nella lista: aprire una scheda riscrive `raw_data.flow_rate`.
- Gli export di `SourceSurveyController` (GeoJSON, GPX, KML, shapefile) leggono solo `raw_data.flow_rate` salvato, quindi mostrano il valore nuovo solo per le schede già aperte in Nova. L'overlay dello stesso controller lo calcola (e lo salva) solo se manca: per una scheda con un `flow_rate` già salvato mostra quello. L'allineamento degli export ai dati `wm-package` è un ticket separato (vedi Follow-up in [notes.md](../features/8879-acqua-sorgente-calcolo-portata/notes.md)).

## Perché così

- **Parser conservativo: `N/A` invece di un numero dubbio** (oc:8879): un valore come `0 45` può essere 0,45 o 45 litri. Una portata mancante si nota; una sbagliata di cento volte passa inosservata.
- **Apice singolo e `™` letti come secondi** (oc:8879): sono refusi del segno `"` da tastiera del telefono; con i volumi registrati, 11 minuti per riempire un contenitore non sono plausibili.
- **Parser in una classe senza Eloquent** (oc:8879): `calculateFlowRate()` salva il modello, quindi il parsing va testato a parte, senza scrivere sul DB.

## Come ci siamo arrivati

- **Parser permissivo che toglie i caratteri non numerici** (superato, oc:8879): attaccava cifre separate e dava numeri sbagliati senza segnalarlo (`1 litro2` → 12 litri, `500ml` → 500 litri). Non riproporlo: per questo il parser attuale è conservativo.
