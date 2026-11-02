> Ticket: oc:8894

# Notes — Tappe SICAI con lo stesso osm_id

## Divergenze dal piano, task per task

### Task 1: helper e id fittizi

Il piano prevedeva id fittizi (`999000002`…) da asserire nelle risposte. `id` non è in `$fillable` di `HikingRoute`, quindi `createQuietly` lo scarta e l'id arriva dalla sequence: le asserzioni usano l'id del modello restituito. Dopo la review i test nuovi passano `null` come id e tengono l'osm_id in una variabile; il parametro dell'helper è `int $appId = HikingRoute::OSM2CAI_APP_ID` e le tappe usano la costante di test `SI_APP_ID = 2`.

### Task 3: helper dei POI

L'helper non ha il parametro `id` (non fillable) e, dopo la review, si chiama `createTestHikingRoute(int $osmId, string $wkt, int $appId)` come nell'altro file di test; il POI si crea con `createTestEcPoi()` e ha `app_id = 1`. `user_id` resta vuoto: la colonna accetta null.

### Task 4: Pint e cleanup dopo la review

Pint riformatta anche righe non toccate (spaziatura delle concatenazioni in `HikingRouteController`, ~80 righe in `HikingRoute`): sono state ripristinate e il diff contiene solo le righe del ticket. Per questo `pint --test` segnala ancora `HikingRouteController.php`, come già prima del ticket. Dopo la review formale sono stati aggiunti il filtro su `/api/v2/export/hiking-routes/list` (con test) e la costante `HikingRoute::OSM2CAI_APP_ID`, usata nei 4 punti nuovi.

## Bug trovati

- **Export v2 con le tappe SI** (`ExportController::hikingRoutesList`): elencava tutti i 26.774 record, comprese le 1.200 tappe `app_id = 2`. Trovato dalla review formale, corretto in questo ticket.
- **Collection v2 per bbox con proprietà sempre vuote** (`HikingRouteController::geojsonByBoundingBox`, precedente al ticket): la query usa `DB::table`, quindi `osmfeatures_data` arriva come stringa e `relation_id`, `ref`, `from`, `to` ecc. escono sempre `null` (verificato in locale su 36 sentieri). Non corretto: vedi Follow-up.
- **`catch (Exception $e)` inefficaci in `HikingRouteController`** (precedente al ticket): manca `use Exception`, quindi i blocchi `catch` cercano `App\Http\Controllers\Exception` e non intercettano nulla. Non corretto.

## Decisioni

- Tag Orchestrator: nessuno associato, su richiesta del dev.
- Solo v2: la v1 resta com'è perché l'import di Geohub usa `/api/v1/hiking-routes/region/...`.
- Niente `orderBy` sulle ricerche per osm_id: gli osm_id in app 1 sono univoci (0 doppioni nel DB locale), anche se nessun vincolo lo garantisce.
- Niente 404 su `/api/v2/ecpois/osm/{osm_id}/{type}`: con il filtro, un osm_id presente solo su una tappa SI risponde 500 invece di 200 con i POI della tappa. Nel DB locale questi casi sono 0 su 517.
- Niente scope sul modello: filtro scritto nella query, con la costante `HikingRoute::OSM2CAI_APP_ID` nei punti nuovi; i 5 `where('app_id', 1)` esistenti restano invariati.
- Modifica a posteriori, dopo la review formale: aggiunto l'export v2 ai requisiti; i gruppi montuosi Mitur Abruzzo (`/api/v2/mitur_abruzzo/mountain_group/{id}`, 22 tappe SI con stato 4 in Abruzzo) restano fuori per scelta del dev.
- Avviso al cliente: nessun avviso prima del rilascio. È il comportamento che ha chiesto e lo saprà dalla risposta sul ticket.

## Follow-up

- API v1: stesso problema e doppioni negli elenchi per regione e bbox. Verificare nei log nginx di produzione chi le chiama prima di intervenire.
- Gruppi montuosi Mitur Abruzzo con le tappe SI.
- Proprietà sempre `null` nella collection v2 per bbox.
- `use Exception` mancante in `HikingRouteController`.
- Valore `app_id` scritto a mano in circa 20 punti di `app/`: valutare uno scope o un enum per gli id delle app.
