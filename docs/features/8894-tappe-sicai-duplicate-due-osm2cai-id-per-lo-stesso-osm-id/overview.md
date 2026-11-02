> Ticket: oc:8894

# [OSM2CAI] Tappe SICAI duplicate: due osm2cai_id per lo stesso osm_id

## Cosa cambia

Tre endpoint della v2 delle API pubbliche smettono di restituire le tappe Sentiero Italia (`app_id = 2`) e restituiscono solo i sentieri (`app_id = 1`):

- `GET /api/v2/hiking-route-osm/{osm_id}` restituisce sempre il sentiero con quell'osm_id, mai la tappa SI figlia;
- `GET /api/v2/ecpois/osm/{osm_id}/{type}` calcola il buffer dei POI sempre sulla geometria del sentiero;
- `GET /api/v2/hiking-routes-collection/bb/{bbox}/{sda}` non include più le tappe SI nel GeoJSON;
- `GET /api/v2/export/hiking-routes/list` elenca solo i sentieri (aggiunto dopo la review formale).

Dopo la modifica tutta la v2 si comporta allo stesso modo: gli elenchi per regione e per bbox filtrano già `app_id = 1`.

## Perché

Tappe SI e sentieri stanno nella stessa tabella `hiking_routes`. La tappa SI è figlia del sentiero (`parent_hiking_route_id`) e ne condivide l'osm_id, ma ha un altro `id` (l'`osm2cai_id` esposto), un altro nome e quasi sempre un'altra geometria (sulle 517 coppie del DB locale, nome diverso in tutte e geometria uguale solo in 29).

Le due ricerche per osm_id fanno `where(osm_id)->first()` senza filtro e senza ordine: Postgres legge la tabella in ordine fisico e restituisce la prima riga che trova, che può essere il sentiero o la tappa e può cambiare dopo ogni `UPDATE` di uno dei due record. In locale `/api/v2/hiking-route-osm/2282464` restituisce la tappa `29651` («SI E02») invece del sentiero `2268`. La collection per bbox restituisce entrambi i record.

È la segnalazione del cliente (Andrea Del Sarto, separata da oc:8893 allo scrum del 28/10): per lo stesso osm_id riceve due osm2cai_id diversi. Allo scrum del 28/10 è stato indicato di non mostrare le tappe SI.

## Requisiti

- [ ] `/api/v2/hiking-route-osm/{osm_id}` cerca solo tra i record con `app_id = 1`: con un sentiero e una tappa SI con lo stesso osm_id restituisce sempre il sentiero.
- [ ] `/api/v2/ecpois/osm/{osm_id}/{type}` cerca il sentiero solo tra i record con `app_id = 1`.
- [ ] `/api/v2/hiking-routes-collection/bb/{bbox}/{sda}` include solo record con `app_id = 1`.
- [ ] `/api/v2/export/hiking-routes/list` include solo record con `app_id = 1` (aggiunto dopo la review formale).
- [ ] Un test API per ciascuno dei tre endpoint crea un sentiero e una tappa SI figlia con lo stesso osm_id e verifica che esca solo il sentiero. Nei test la tappa SI viene creata **prima** del sentiero (così con il codice attuale esce la tappa), con id e osm_id fittizi fuori dai valori reali e `createQuietly`; i test nuovi vanno eseguiti sul codice attuale e devono fallire prima del fix.
- [ ] I test esistenti di `HikingRouteControllerV2Test` continuano a passare.

## Rischi

- **Un consumatore che si aspetta le tappe SI dalla v2 non le riceve più.** Nei dati locali nessun osm_id esiste solo su una tappa SI, quindi nessuna ricerca per osm_id che oggi trova qualcosa restituirà 404. Chi ha già salvato id di tappe SI li può comunque leggere con `/api/v2/hiking-route/{id}`, che non cambia.
- **L'univocità dell'osm_id in app 1 non è garantita da un vincolo**: oggi non ci sono doppioni, ma nel DB non c'è alcun indice unico. Si è scelto di non aggiungere un `orderBy`: se in futuro comparissero due sentieri con lo stesso osm_id tornerebbe la scelta imprevedibile.
- **Il cliente può aver salvato id di tappe SI** ricevuti finora dalla ricerca per osm_id: dopo la correzione la stessa ricerca restituirà l'id del sentiero. È il comportamento richiesto dal cliente, che ne viene informato con la risposta sul ticket al rilascio.
- **Dalla collection per bbox spariscono tutti i record `app_id = 2`**, non solo le tappe SI escursionistiche (layer 6) ma anche le tappe SI MTB (`SiMTBRoute`, stessa tabella), comprese quelle senza un sentiero padre. Nel DB locale sono 12 record senza padre con stato SDA 3 (3 sono record di prova di oc:8620); gli altri hanno stato nullo e già oggi non escono. Accettato come effetto voluto: la v2 non mostra record `app_id = 2`, come già fanno gli elenchi v2 per bbox.
- **I test girano sul DB locale reale** (`DatabaseTransactions`, SQLite commentato in `phpunit.xml`) e l'ordine delle righe senza `ORDER BY` dipende dall'inserimento: un test che crea prima il sentiero passerebbe anche senza il fix. Mitigato come descritto nei requisiti.
- **Il filtro su `app_id` è ripetuto a mano** (già in 5 metodi di `HikingRouteController` con il valore `1` scritto direttamente, più i 4 punti nuovi che usano la costante `HikingRoute::OSM2CAI_APP_ID`): un endpoint futuro può dimenticarlo di nuovo. Si è scelto per ora di non introdurre uno scope sul modello.

## Out of scope

- **API v1**: hanno lo stesso problema e in più restituiscono doppioni negli elenchi per regione e per bbox. Restano come sono: l'import di Geohub (`/api/v1/hiking-routes/region/...`) le usa e un filtro gli farebbe sparire le tappe SI. Da affrontare in un ticket a parte, dopo aver controllato nei log di produzione chi le chiama.
- **404 su osm_id inesistente in `/api/v2/ecpois/osm/{osm_id}/{type}`**: oggi risponde 500; resta così.
- **Vincolo di univocità sull'osm_id in app 1** (indice unico o `orderBy`).
- **Ricerca in Nova** che mostra sentiero e tappa SI con lo stesso osm_id: allo scrum del 02/11 si è deciso di non modificarla.
- **Gruppi montuosi Mitur Abruzzo** (`/api/v2/mitur_abruzzo/mountain_group/{id}`): l'intersezione geometrica non filtra `app_id` e include anche le tappe SI con stato 4 (22 in Abruzzo nel DB locale). Lasciato com'è per scelta del dev.
- **Lettura per id** (`/api/v2/hiking-route/{id}`, `/hiking-route-tdh/{id}`, `/ecpois/{osm2cai_id}/{type}`): restituisce il record chiesto, non cambia.

## Moduli toccati

Repo osm2cai2 (feature custom, nessun submodule):

- `app/Http/Controllers/HikingRouteController.php` — `showByOsmId`, `geojsonByBoundingBox`
- `app/Http/Controllers/EcPoiController.php` — `indexByBufferFromHikingRouteOsmId`
- `app/Http/Controllers/ExportController.php` — `hikingRoutesList`
- `app/Models/HikingRoute.php` — costante `OSM2CAI_APP_ID`
- `tests/Api/ExportControllerTest.php` — test sull'elenco dell'export
- `tests/Api/HikingRouteControllerV2Test.php` — test per le due rotte dei sentieri
- `tests/Api/EcPoiControllerV2Test.php` (nuovo) — test per la ricerca dei POI per osm_id
