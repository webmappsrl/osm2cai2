> Ticket: oc:8620

# Tappa aggiornata da Nova sparisce dal layer nell'indice Elasticsearch

## Cosa cambia

`SiHikingRoute` e `SiMTBRoute` smettono di presentarsi al database con un'identità polimorfica
propria e usano quella del padre, `App\Models\HikingRoute` — l'unico valore realmente presente
nelle tabelle pivot.

Da quel momento salvare una tappa dalla risorsa Nova `si-hiking-routes` (o `si-mtb-routes`)
riscrive su Elasticsearch un documento completo, con `layers` e `taxonomyActivities`
valorizzati, invece di svuotarli.

## Perché

Le relazioni polimorfiche di `EcTrack` (`layers`, `taxonomyActivities`, media, segnaletica)
filtrano il pivot su `layerable_type`, cioè sul valore restituito da `getMorphClass()`.
`GeometryModel::getMorphClass()` (`wm-package/src/Models/Abstracts/GeometryModel.php:259`)
compone quel valore dal nome della classe:

```php
return 'App\\Models\\'.class_basename($this);
```

Per `SiHikingRoute` produce `App\Models\SiHikingRoute`, valore che **nel database non esiste su
nessuna riga**: il pivot `layerables` contiene 27.268 righe, tutte `App\Models\HikingRoute`. La
relazione torna vuota, e `EcTrack::toSearchableArray()` (`wm-package/src/Models/EcTrack.php:746`)
scrive `'layers' => []`.

La tabella è una sola (`hiking_routes`) e le sottoclassi sono viste Nova filtrate per `app_id`,
come dichiarato in `app/Models/SiHikingRoute.php:20-21`. Cambia soltanto da quale classe PHP
passa la lettura — e le due classi vedono cose diverse. Verificato in locale nei due sensi sullo
stesso record 29904:

| Operazione | `layers` risultante |
|---|---|
| `SiHikingRoute::find(29904)->searchable()` | `[]` — rompe |
| `HikingRoute::find(29904)->searchable()` | `[6,4]`, `taxonomyActivities: ['hiking']` — ripara |

Da qui il comportamento osservato: si salva dalla vista SI e la tappa sparisce dal layer; si
rilancia Reindex Scout da `resources/apps/2` e torna, perché quell'action carica le tracce con
`$app->ecTracks()`, che le istanzia come `HikingRoute`. Il dato sul database non si è mai mosso.

La pista annotata nel ticket — `EcTrackObserver::syncAutoLayersAfterNovaTrackEdit()` che filtra
su `nova-api/ec-tracks*` — è un limite reale di quel fallback, ma non è la causa di questo bug:
spiegherebbe un mancato riallineamento, non un documento riscritto con le relazioni vuote.

Il problema **non si risolve aggiornando wm-package**: `getMorphClass()` è identico nella
versione in uso (v1.5.0-19) e nella più recente in azienda (v1.5.0-147, forestas). Negli altri
progetti non si manifesta solo perché lì la sottoclasse si chiama `EcTrack`, quindi il nome
composto coincide per costruzione. osm2cai2 è l'unico consumer con una catena a tre livelli
(`EcTrack` → `HikingRoute` → `SiHikingRoute`) e nomi propri.

Il repo ha già adottato questo stesso rimedio per i POI: `app/Models/SiPoi.php:61` ritorna
`EcPoi::class`, motivato proprio con le relazioni polimorfiche.

## Requisiti

- [ ] Salvando una tappa dalla risorsa Nova `si-hiking-routes`, il documento Elasticsearch
      conserva `layers` e `taxonomyActivities` valorizzati
- [ ] La tappa resta presente nella query per layer
      (`/api/v2/elasticsearch?app=geohub_app_2&layer=6`) dopo il salvataggio
- [ ] Lo stesso vale per `si-mtb-routes`, affetta dal medesimo difetto
- [ ] `SiHikingRoute` e `SiMTBRoute` risolvono `layers`, `taxonomyActivities`, media e
      segnaletica esattamente come `HikingRoute`
- [ ] Ogni futura sottoclasse di `HikingRoute` eredita l'identità corretta senza interventi
- [ ] Nessuna modifica a `wm-package`
- [ ] Dopo il deploy, i documenti già corrotti sono ripristinati con Reindex Scout su
      `resources/apps/2` (1.188 tracce)
- [ ] Test automatici di regressione: relazioni equivalenti fra le classi, `toSearchableArray()`
      di una `SiHikingRoute` con i layer valorizzati, stesso controllo su `SiMTBRoute`, e il
      ciclo completo salvataggio → documento indicizzato
- [ ] Controllo delle colonne `*_type` subito prima del deploy, per escludere righe con
      l'identità vecchia create nel frattempo

## Rischi

- **Storico Nova interrotto.** `action_events` contiene 565 righe `App\Models\SiHikingRoute` e
  663 `App\Models\SiMTBRoute`. Dopo il fix la scheda Nova di quelle tappe non mostra più le
  azioni registrate finora: le righe restano nel database, ma nessuno le interroga più.
  Nessun dato di dominio è coinvolto, nessuna API ne risente. **Accettato dal dev**, nessuna
  migration di normalizzazione prevista.

- **Documenti già corrotti in produzione.** Il fix impedisce nuove corruzioni ma non ripara
  quelle avvenute, e non sappiamo quante tappe siano state salvate dalle risorse SI finora.
  *Mitigazione:* Reindex Scout su `resources/apps/2` dopo il deploy, che copre tutte le 1.188
  tracce dell'app e non solo le due segnalate.

- **Superficie dell'override.** `getMorphClass()` governa tutte le relazioni polimorfiche, non
  solo i layer. *Mitigazione:* censite tutte e 20 le colonne `*_type` del database; nelle
  quattro tabelle di dominio (`layerables`, `taxonomy_activityables`, `media`,
  `signage_projectables`) non esiste alcuna riga scritta con le sottoclassi — l'allineamento
  ripara senza orfanare nulla. Le action `DownloadGeojson/Shape/Kml`, che usano
  `getMorphClass()` per costruire un URL, sono registrate solo su `Area`, `Club` e `Region`.
  Policy e menu Nova si risolvono sulla classe PHP reale, non sull'identità morph.

- **Divergenza con wm-package.** L'override vive nell'app mentre il metodo che lo rende
  necessario sta nel package: se un domani `GeometryModel::getMorphClass()` cambiasse
  strategia, questo override resterebbe a coprire un problema non più esistente.
  *Mitigazione:* commento nel codice che spiega cosa copre e perché, sul modello di `SiPoi`.

- **Layer non ricalcolati dopo un cambio di attività (emerso dalla Challenge).** Il fix fa
  leggere i layer dal pivot, ma il pivot viene riallineato solo quando cambia `osm2cai_status`
  (`HikingRouteObserver::saved()`), mentre il fallback di wm-package che li ricalcola dalle
  taxonomy ignora le richieste diverse da `nova-api/ec-tracks*`. Cambiando l'attività di una
  tappa da Nova SI, il documento uscirà quindi con layer *presenti ma vecchi* invece che vuoti:
  il fix toglie il sintomo diagnostico di un problema sottostante che non causa.
  *Mitigazione:* verifica in locale prima del deploy; se il pivot resta indietro, ticket
  separato con la prova. Nessun allargamento di scope su wm-package.

### Rilievi della Challenge caduti alla verifica

Tre rilievi sono stati smentiti dai dati e non sono stati recepiti:

- *«Le relazioni polimorfiche restituiranno `HikingRoute` invece di `SiHikingRoute`, bypassando
  gli override SI»* — succede già oggi: `Layer::find(6)->ecTracks()->first()` restituisce
  `App\Models\HikingRoute`, perché il pivot contiene solo quel valore. Il fix non introduce il
  comportamento, lo trova già presente.
- *«Il Reindex Scout rimuoverà i documenti che non passano `shouldBeSearchable()`»* — sulle
  1.188 tracce `app_id = 2`: zero con geometria nulla, zero con `osm2cai_status = 0`.
- *«`SiMTBRoute` è trattata per analogia senza verifica»* — verificato: stessi override di
  relazione di `SiHikingRoute` (meno `siPois` e `getFeatureCollectionMap`), stessa tabella,
  stesso filtro `app_id = 2` nella `indexQuery` Nova.

Scartato consapevolmente anche il suggerimento di sostituire l'override con un
`Relation::morphMap()` centralizzato: è la soluzione architetturalmente più pulita, ma tocca
tutti i modelli e non è materia di un hotfix.

### Prova del fix, eseguita prima della pianificazione

Verificato in locale su una sottoclasse usa-e-getta, senza modificare alcun file del repo:

```
SENZA fix (SiHikingRoute):   layers=0   taxonomyActivities=0
CON fix   (override morph):  layers=2   taxonomyActivities=1
documento indicizzato CON fix: layers=[6,4]  taxonomyActivities=["hiking"]
```

## Out of scope

- Qualsiasi modifica a `wm-package`, incluso il suo aggiornamento di versione — verificato che
  non risolverebbe il problema
- Normalizzazione di `action_events` (effetto accettato, vedi Rischi)
- Il limite di `syncAutoLayersAfterNovaTrackEdit()`, che ignora le richieste Nova diverse da
  `nova-api/ec-tracks*`: difetto reale di wm-package, indipendente da questo bug
- Ripensare l'architettura delle risorse SI come viste filtrate di `HikingRoute`

## Moduli toccati

Tutto nel repo principale **osm2cai2**. Nessun file in `wm-package/` o `wm-osmfeatures/`.

| File | Intervento |
|---|---|
| `app/Models/HikingRoute.php` | nuovo `getMorphClass()` che fissa `App\Models\HikingRoute` per sé e per le sottoclassi |
| `tests/` | test di regressione sull'equivalenza delle relazioni fra `HikingRoute` e `SiHikingRoute` |
| `docs/features/8620-morph-class-sihikingroute-layers-elasticsearch/` | overview, plan, notes |
