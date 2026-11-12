# Calcolo della portata delle sorgenti

## Come funziona oggi

La portata (L/s) delle schede Acqua Sorgente (`SourceSurvey`, form `water`) si calcola come volume / tempo solo se *Flow Rate Validation* (`water_flow_rate_validated`) è `valid`; altrimenti vale `N/A`. Il campo che conta è questo, non la validazione generale della scheda (`validated`, *Validation Status*).

- Le regole stanno in un solo metodo privato, `UgcPoi::evaluateFlowRate()`, che restituisce insieme il valore e i motivi del `N/A`. `calculateFlowRate()` salva il valore in `raw_data.flow_rate`; `flowRateForDisplay()` compone «N/A (motivo; motivo)» senza salvare.
- Volume, tempo e *Sorgente attiva* (`active`) si leggono da `properties.form`; se la chiave manca o è vuota, da `raw_data` (`UgcPoi::getFormValue()`, usato anche dal GeoJSON delle sorgenti). Le schede inviate dall'app tramite `wm-package` hanno i dati solo in `properties.form`.
- I valori sono testo libero scritto dai rilevatori. Li interpreta `App\Services\FlowRateParser`: accetta `,` e `.` come separatore decimale, le unità più comuni (`l`, `lt`, `litri`, `ml`; `s`, `secondi`, `"`, `''`, `'`, `™`) e i formati da cronometro `mm:ss(.cc)` e `mm ss cc`. Tutto il resto dà `N/A`.
- I casi riconosciuti sono elencati nei data provider di `tests/Unit/Services/FlowRateParserTest.php`, costruiti su tutti i valori non numerici presenti nel DB. Un caso nuovo si aggiunge lì, con il risultato atteso.
- I motivi del `N/A` sono chiavi inglesi tradotte in `lang/vendor/nova/*.json`, uno per caso in `reasonProvider()` di `tests/Unit/Models/UgcPoiFlowRateTest.php`. La regola non ovvia: «portata non validata» va sempre in testa ma non nasconde gli eventuali problemi dei dati, che seguono.

Vincoli da sapere:

- Il campo Nova *Flow Rate L/s* è definito due volte sullo stesso attributo: nell'elenco mostra il valore salvato senza motivo (la colonna è stretta), nel dettaglio e nel form di modifica, in sola lettura, mostra «N/A (motivo)». Entrambi chiamano `calculateFlowRate()`, che scrive `raw_data.flow_rate` solo se il valore è cambiato. Il motivo non viene mai salvato: in `raw_data.flow_rate` c'è un numero o `N/A`.
- Il GeoJSON `survey.geojson` calcola la portata con `computeFlowRate()`, senza salvare (oc:8868): vedi [8868-acqua-sorgente-scarico-geojson.md](8868-acqua-sorgente-scarico-geojson.md). Gli altri export di `SourceSurveyController` (GPX, KML, shapefile) leggono ancora solo `raw_data.flow_rate` salvato, quindi mostrano il valore nuovo solo per le schede già aperte in Nova e non hanno il motivo. L'overlay dello stesso controller lo calcola (e lo salva) solo se manca: per una scheda con un `flow_rate` già salvato mostra quello. L'allineamento di questi export ai dati `wm-package` è un ticket separato (vedi Follow-up in [notes.md](../features/8879-acqua-sorgente-calcolo-portata/notes.md)).

## Perché così

- **Parser conservativo: `N/A` invece di un numero dubbio** (oc:8879): un valore come `0 45` può essere 0,45 o 45 litri. Una portata mancante si nota; una sbagliata di cento volte passa inosservata.
- **Apice singolo e `™` letti come secondi** (oc:8879): sono refusi del segno `"` da tastiera del telefono; con i volumi registrati, 11 minuti per riempire un contenitore non sono plausibili.
- **Parser in una classe senza Eloquent** (oc:8879): `calculateFlowRate()` salva il modello, quindi il parsing va testato a parte, senza scrivere sul DB.
- **Il `N/A` dice perché** (oc:8879, secondo ciclo): chi aveva scritto «1 litro2» vedeva solo `N/A` e non capiva cosa correggere. Il motivo sta nel valore del campo, non nel testo di aiuto, perché Nova 5 mostra l'aiuto solo nel form di modifica.
- **Valore e motivi da un solo metodo** (oc:8879, secondo ciclo): con due metodi separati, una regola cambiata in uno solo farebbe comparire `N/A` senza motivo, o un motivo su una portata calcolabile. Un test sui data provider del parser verifica che il motivo ci sia se e solo se la portata è `N/A`.
- **Motivo «sorgente non attiva»** (oc:8879, secondo ciclo): su una sorgente secca non misurare è corretto, e «volume e tempo non inseriti» farebbe pensare a una dimenticanza.
- **Il dato salvato resta `N/A`** (oc:8879, secondo ciclo): l'overlay confronta il valore con `!== 'N/A'` e gli export GPX, KML e shapefile lo scrivono così com'è; il motivo si calcola solo quando lo si mostra.

## Come ci siamo arrivati

- **Parser permissivo che toglie i caratteri non numerici** (superato, oc:8879): attaccava cifre separate e dava numeri sbagliati senza segnalarlo (`1 litro2` → 12 litri, `500ml` → 500 litri). Non riproporlo: per questo il parser attuale è conservativo.
- **«Portata non validata» come unico motivo** (superato, oc:8879, secondo ciclo): il piano controllava la validazione per prima e si fermava lì. Sulla scheda 8833 il messaggio faceva pensare che bastasse validare, mentre dopo la validazione sarebbe comparso un secondo problema, i dati mancanti. Ora i problemi dei dati si elencano anche quando la portata non è validata.
