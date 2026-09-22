> Ticket: oc:8620

# Notes — Morph class di SiHikingRoute

## Deviazioni dal piano

### Task 3 — la verifica dei "layer stantii" è decaduta

Il piano prevedeva di cambiare l'attività di una tappa dalla risorsa Nova SI e osservare se il
pivot `layerables` si riallineava. La prova non è eseguibile: la risorsa `si-hiking-routes` non
espone alcun campo per le attività. Il form di modifica (`sicaiEditFields`,
`app/Nova/SiHikingRoute.php:182-194`) contiene solo la descrizione tradotta, la relazione
`ecPois` e le tab SICAI/INFO.

Il rischio sollevato dalla Challenge — documenti con layer presenti ma vecchi — **non è quindi
raggiungibile da quell'interfaccia**, perché da lì non si modifica ciò da cui i layer dipendono.
Nessun ticket separato aperto.

Al suo posto è stata eseguita la verifica end-to-end del fix sul percorso reale (vedi Decisioni).

## Bug trovati

### Container Redis fuori dalla rete Docker (ambiente locale)

Durante il Task 3 il salvataggio da Nova falliva con
`getaddrinfo for redis failed [tcp://redis:6379]`, e il login in Nova non funzionava. Causa:
`redis-osm2cai2` era in esecuzione ma non collegato alla rete `osm2cai2_laravel`, con
`horizon-osm2cai2` in restart perpetuo di conseguenza.

Risolto riattaccandolo **con l'alias del nome del servizio**, che `docker network connect` non
aggiunge da sé:

```bash
docker network connect --alias redis osm2cai2_laravel redis-osm2cai2
```

Senza `--alias redis` il container risulta connesso ma l'app continua a non risolverlo, perché
lo cerca come `redis` e non come `redis-osm2cai2`.

Problema di ambiente locale, nessuna relazione con il ticket.

## Decisioni

- **`action_events` non viene normalizzato.** Le 565 righe `App\Models\SiHikingRoute` e 663
  `App\Models\SiMTBRoute` restano come sono: dopo il fix la scheda Nova di quelle tappe non
  mostrerà più lo storico delle azioni precedenti. Effetto accettato esplicitamente dal dev —
  è il log dell'admin, nessun dato di dominio è coinvolto.

- **Override in `HikingRoute`, non nelle due sottoclassi.** Chiude il difetto nel punto in cui
  nasce (il nome composto da `class_basename`) e copre anche le sottoclassi future. L'alternativa
  — una riga in `SiHikingRoute` e una in `SiMTBRoute` — sarebbe più esplicita ma andrebbe
  ricordata ogni volta.

- **Scartata l'alternativa di reindicizzare come `HikingRoute` dopo il salvataggio.** Proposta
  dal dev durante la pianificazione, avrebbe risolto il documento Elasticsearch lasciando però
  intatto il difetto di lettura: sullo stesso record, `SiHikingRoute` vedeva `layers = 0` e
  `taxonomyActivities = 0` dove `HikingRoute` vedeva 2 e 1. Ogni altro consumatore (export, API,
  job, future feature su quella risorsa) avrebbe continuato a leggere relazioni vuote, senza più
  il sintomo evidente che ha permesso di trovare il bug.

- **Verifica end-to-end eseguita sul percorso reale.** Salvataggio della descrizione di SI V03
  (id 29904) dalla risorsa Nova `si-hiking-routes` in locale, con il fix attivo:

  ```
  layers             = [6, 4]                  (prima del fix: [])
  taxonomyActivities = ['hiking']              (prima del fix: [])
  __class_name       = App\Models\HikingRoute  (prima: App\Models\SiHikingRoute)
  ```

  È lo stesso gesto che ha generato la segnalazione del cliente.

- **I test verificati come non tautologici.** Con il metodo `getMorphClass()` commentato, tutti
  e quattro i test falliscono; ripristinandolo, tutti e quattro passano.

## Follow-up

- **Test che lasciano record nel database locale.** `HikingRouteMorphClassTest` non usa
  `RefreshDatabase` né ripulisce nel `tearDown`: ogni esecuzione lascia una traccia, un layer
  «Layer di test oc:8620» e una riga di pivot. Sul DB locale sono comparsi ~23 record residui.
  Da valutare se introdurre una pulizia, tenendo conto che nel repo i test girano su un
  PostgreSQL/PostGIS reale e `RefreshDatabase` avrebbe conseguenze sull'intera suite.

- **Cinque test rossi preesistenti nella suite Unit**, verificati identici anche senza il fix
  (rilanciati con la modifica messa da parte con `git stash`):
  `HikingRouteSignageExporterTest > get descripti…`, `SiPoiTaxonomyTest` (fallisce su
  `UnableToCheckFileExistence`, storage MinIO) e tre casi di
  `SignageMapControllerRoundTravelTimeTest` sull'arrotondamento dei tempi di percorrenza.
  Nessuno riguarda questo ticket.

- **`Relation::morphMap()` centralizzato.** Sarebbe la soluzione strutturale al posto del terzo
  override sparso nel repo (dopo `SiPoi`, e quelli di `App` e `User` in wm-package): renderebbe
  il contratto esplicito e verificabile in un punto solo. Scartato perché tocca tutti i modelli
  e non è materia di un hotfix.

- **`syncAutoLayersAfterNovaTrackEdit()` di wm-package** filtra le richieste su
  `nova-api/ec-tracks*` (`wm-package/src/Observers/EcTrackObserver.php:115-124`) e quindi non
  scatta mai per le risorse SI. È un limite reale del fallback, indipendente da questo bug e
  fuori scope per il vincolo di non toccare il package.
