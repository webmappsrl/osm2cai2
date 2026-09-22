# Identità polimorfica delle tracce

## Come funziona oggi

`hiking_routes` è una tabella sola. `HikingRoute` ne è il modello; `SiHikingRoute` e
`SiMTBRoute` la estendono senza aggiungere una tabella propria: sono viste Nova filtrate per
`app_id`, con campi e relazioni diversi.

Tutte le relazioni polimorfiche di una traccia — layer (`layerables`), attività
(`taxonomy_activityables`), media, segnaletica (`signage_projectables`) — usano **una sola
identità**: `App\Models\HikingRoute`. Nessuna riga del database contiene
`App\Models\SiHikingRoute` o `App\Models\SiMTBRoute`, e non deve contenerne.

L'identità è fissata da `HikingRoute::getMorphClass()`, che ritorna sempre
`HikingRoute::class`. Le sottoclassi la ereditano, comprese quelle che verranno.

**Chi scrive una nuova sottoclasse di `HikingRoute` non deve fare nulla**: l'identità corretta
arriva per eredità. Chi invece volesse dare a una sottoclasse un'identità propria deve prima
occuparsi dei dati esistenti, perché le righe già scritte non la conoscono.

## Perché così

- **Un'unica identità per la gerarchia** (oc:8620): `GeometryModel::getMorphClass()` in
  wm-package compone il valore come `'App\Models\'.class_basename($this)`. Per le sottoclassi
  produce nomi che nel database non esistono, quindi le relazioni tornano vuote e
  `toSearchableArray()` indicizza `'layers' => []` su Elasticsearch: la traccia sparisce dal
  layer pur avendo il dato intatto a database. Il difetto colpisce ogni lettura fatta da una
  sottoclasse, non solo l'indicizzazione — Elasticsearch è solo il consumatore che lo rende
  visibile.

- **L'override sta nel padre, non nelle sottoclassi** (oc:8620): chiude il difetto dove nasce e
  copre le sottoclassi future. Metterlo in ciascuna sottoclasse sarebbe più esplicito ma
  andrebbe ricordato ogni volta, e questa volta non ce ne si è ricordati.

- **Stesso criterio già adottato per i POI**: `SiPoi::getMorphClass()` ritorna `EcPoi::class`,
  per la stessa ragione (media salvati con `model_type = App\Models\EcPoi`).

- **Aggiornare wm-package non risolve** (oc:8620): `getMorphClass()` è identico nella versione
  in uso e nella più recente in azienda. Negli altri progetti il difetto non si vede solo perché
  lì la sottoclasse si chiama `EcTrack`, quindi il nome composto coincide per costruzione.
  osm2cai2 è l'unico consumer con una catena a tre livelli e nomi propri.

## Come ci siamo arrivati

- **Reindicizzare come `HikingRoute` dopo il salvataggio** (oc:8620, scartata): avrebbe
  riparato il documento Elasticsearch lasciando intatto il difetto di lettura. Sullo stesso
  record, `SiHikingRoute` vedeva zero layer e zero attività dove `HikingRoute` ne vedeva due e
  una: ogni altro consumatore avrebbe continuato a leggere relazioni vuote, senza più il
  sintomo evidente che ha permesso di trovare il problema.

- **`Relation::morphMap()` centralizzato** (oc:8620, rimandata): renderebbe il contratto
  esplicito e verificabile in un punto solo, invece di un override per gerarchia. Non adottata
  perché tocca tutti i modelli e il lavoro era un hotfix.

## Effetti da conoscere

`getMorphClass()` governa **tutte** le relazioni polimorfiche, non solo i layer. Un cambio di
identità rende irraggiungibili le righe già scritte con il valore precedente.

In `action_events` — il registro delle azioni di Nova — esistono righe scritte con le vecchie
identità delle sottoclassi: la scheda Nova di quelle tracce non mostra le azioni precedenti a
oc:8620. Le righe restano nel database, semplicemente nessuno le interroga più. Effetto noto e
accettato: riguarda un log dell'admin, non i dati del prodotto.
