> Ticket: oc:8867

# Notes — AcquaSorgente, duplicazioni rilevamenti

Tutte le misure vengono dal dump di produzione del **26/10/2026 20:15** (`backups/osm2cai2/last_dump.zip`,
store Aruba), ripristinato in locale. Database `osm2cai2`: 10.002 UgcPoi, 1.616 UgcTrack.

## Misure di riferimento

**Duplicati con uuid** (raggruppando per `properties->uuid`):

| tabella | app | gruppi | record in eccesso |
|---|---|---|---|
| ugc_pois | 3 AcquaSorgente | 77 | 103 |
| ugc_pois | 1 osm2cai | 48 | 66 |
| ugc_pois | 2 SICAI | 3 | 5 |
| ugc_tracks | 1 osm2cai | 58 | 71 |
| ugc_tracks | 2 SICAI | 5 | 6 |
| ugc_tracks | 3 AcquaSorgente | 1 | 1 |
| | **totale** | **192** | **252** |

Su AcquaSorgente il fenomeno è in corso: record in eccesso creati per mese — ottobre 7 (al 26),
settembre 14, agosto 18, luglio 15, giugno 28, maggio 21.

**Duplicati senza uuid**, riconoscibili per utente + `properties.createdAt` + posizione:
**162 POI, 0 tracce**. Su AcquaSorgente: 38 certi, 9 sospetti (manca anche `createdAt`), su 153
rilevamenti `device` senza uuid; i restanti 106 sono rilevamenti unici senza doppioni.

**Fotografie a rischio**: in 57 gruppi su 77 di AcquaSorgente la scheda più vecchia non ha foto,
e sui duplicati ce ne sono 139.

**Eredità della vecchia bonifica**: 660 copie marcate `properties.duplicated` e riassegnate
all'utente 7736, 462 schede con `duplication_info` ereditato per errore, **102 fotografie rimaste
sulle copie** (148 totali, di cui 46 presenti anche sul padre con lo stesso nome file). Dei 660
padri, 658 esistono ancora.

**Versioni dell'app** (AcquaSorgente): la 3.1.10 duplica un rilevamento su due — 144 su 265 su
android, 19 su 43 su ios — contro il 5% della 3.1.8 e lo 0,6% della 3.0.4. In circolazione dal
17/04/2026.

## Catena causale, con le prove

1. L'app invia sempre un multipart con il geojson serializzato nel campo `feature`
   (`wm-core`, `ugc.service.ts`, `_buildFormData`: `data.append('feature', JSON.stringify(...))`).
   Identico fra 3.1.8 e 3.1.10.
2. La validazione quel campo lo apre (`wm-package/src/Http/Controllers/Controller.php:45-49`:
   `if (isset($data['feature'])) $data = json_decode(...)`), quindi l'uuid entra nel record salvato.
3. Il controllo anti-duplicato no: `UgcPoiController.php:17` legge
   `$request->input('properties.uuid')`, che su quella richiesta vale `null`, e fa
   `return new UgcPoi`. Verificato costruendo la stessa richiesta nei due formati: busta `feature`
   → `NULL`, geojson al primo livello → l'uuid.
4. Per le **tracce** non c'è nemmeno il tentativo: `UgcTrackController::getModelIstance()` fa
   `return new UgcTrack` e basta.
5. Il reinvio nasce dall'app: in `_pushUgcPois` la scheda esce dalla coda solo `if (res)`, e
   `saveApiPoi` ha `catchError(_ => of(null))`, quindi ogni errore diventa un reinvio.

Caso del ticket: `10828` e `10830`, stesso uuid, stesso `createdAt` **e** stesso `updatedAt`
(24/10 11:32:24.633Z), salvati a 18 ore di distanza, uno con 1 foto e uno con 0, entrambi 3.1.10.
Cade nella classe «duplicati che differiscono solo per le foto», 88 coppie.

## Decisioni

- **Fix custom in osm2cai2, non nel package** (dev): osm2cai2 è 188 commit dietro `wm-package`
  (fermo a `451ba071`, 27/05/2026) e l'aggiornamento non è percorribile ora.
- **Due strati separati**: cherry-pick di oc:8718 (temporaneo) e codice custom (definitivo), mai
  mescolati. Registro in `docs/knowledge/custom-in-attesa-dell-aggiornamento-di-wm-package.md`.
- **Il command attuale si elimina**, non si deprecia, e il vecchio nome non resta come alias:
  in `docs/superpowers/plans/2026-05-05-migrazione-server-cai.md:43` c'è una riga pronta con
  `--execute=1`, che con un alias trasparente cancellerebbe record.
- **Sparisce l'utente di servizio 7736** (dev), e l'unico posto delle copie rimosse diventa
  `ugc_duplicates_archive`.
- **L'utente originale si ripristina** da `duplication_info.parent_ugc_poi_user_id` prima di
  archiviare: il vecchio command l'aveva sovrascritto.
- **La sanatoria sta in questo ticket** (dev), non in uno separato.
- **Gli EcPoi restano fuori** (dev). Semantica del dominio, non deducibile dal codice: quando un
  operatore valida un UGC nasce un EcPoi, e **da quel momento i due hanno vita propria**. L'EcPoi
  certifica quell'UGC in quell'istante ed è visibile a tutti; se l'UGC poi cambia, all'EcPoi non
  interessa. Quindi `properties->ugc->ugc_poi_id` è un riferimento storico, non una relazione
  viva: non va riscritto quando l'UGC viene cancellato, e un EcPoi che punta a un id inesistente
  non è un errore da riparare. I 100 EcPoi collegati a record che la bonifica cancella non sono
  un problema; i punti pubblicati in doppio sono un problema dell'operatore che li ha promossi.
- **Il webhook da geohub si chiude** (dev): serviva durante la migrazione, quando le app vecchie
  inviavano ancora a geohub e geohub inoltrava la stessa richiesta a osm2cai, perché le app non si
  aggiornavano tutte insieme. Oggi le versioni vecchie hanno una home che non permette più l'invio
  di nuovi UGC, e i dati lo confermano: ultimo POI dal webhook il 01/04/2026, ultima traccia
  l'08/11/2025. Rimozione netta, senza risposta di cortesia: non c'è più nessuno che chiama.
- **Il raggruppamento per istante resta spento per default**: è un'inferenza, non una chiave
  dichiarata.
- **Il report CSV resta** (scelta nostra, diverge dal package che l'ha tolto): è il modo in cui
  oggi si revisionano i gruppi prima di applicare.
- **Niente requisito di rollback per la bonifica.** La bonifica si collauda in locale sul dump di
  produzione — gli stessi dati veri su cui girerà — e si va in produzione solo se l'esito è quello
  atteso. Il rollback è quindi relativo: l'errore lo si vede prima, in locale, dove tornare
  indietro costa un `db:restore`. Il rischio «`migrate:rollback` cancella l'archivio dei duplicati»
  resta **ipotetico**: non è mai successo e niente nel repo lo esegue in automatico. Resta scritto
  qui come avvertenza, non come requisito.

## Strade escluse, con il motivo

- **Raggruppare per utente + posizione senza vincolo temporale**: fonderebbe i monitoraggi
  periodici. Sui dati reali 1.121 coppie sono monitoraggi successivi della stessa sorgente —
  trovato il caso di una sorgente rilevata ogni mese per un anno, con temperature da 13,7 a 7.
- **Usare la distanza fra i salvataggi come criterio**: scartato dopo aver trovato `8825`/`9380`,
  stesso `createdAt` ma salvati a un mese di distanza. Il discriminante è `createdAt`, non la
  distanza fra i `created_at`.
- **Portare il criterio per istante in `wm-package`**: su camminiditalia tutti i rilevamenti hanno
  l'uuid (0 su 369 fra POI e tracce), quindi là non troverebbe nulla. La tabella
  `ugc_duplicates_archive` è già in produzione lì, con 18 copie archiviate il 12/10/2026.
- **Tenere il record più completo invece del più vecchio**: sposterebbe l'identità del
  rilevamento, rompendo validazioni e riferimenti esterni.

## Correzioni di rotta

Annotate perché sono conclusioni date per buone e poi smentite dai dati: chi rilegge non deve
ricascarci.

- «Il command non sposta i media, li elenca soltanto» — **falso**: li sposta
  (`FixDuplicatedUgcPois.php:386`, `$media->model_id = <padre>`), ma solo con `--execute` e solo
  dopo lo scarto per hash fatto **scaricando le immagini da AWS**. Se il download fallisce il
  media esce in silenzio dalla lista: è il candidato più probabile per le 102 foto rimaste.
- «Il perimetro è AcquaSorgente» — **falso**: il difetto è nello store, riguarda POI e tracce di
  tutte le app. 252 record in eccesso, non 103.
- «I backup di produzione sono fermi dal 20/05/2026» — **falso**: quello è il bucket AWS
  `wmdumps`, dismesso. I backup veri girano ogni sera su Aruba (`wmfe`, root `backups/`).
- «I record `platform` sono vuoti» — **falso**: avevo guardato `raw_data`, mentre i dati stanno in
  `properties.form`, popolato in 773 su 776.

## Follow-up

- **`db:download` è rotto**: cerca `backups/maphub/osm2cai2/last_dump.sql.gz` su un endpoint
  ereditato da `AWS_ENDPOINT` (in locale MinIO), mentre il file è `backups/osm2cai2/last_dump.zip`
  sullo store Aruba. Rotto anche in produzione. Ticket a sé.
- **I 1.583 rilevamenti `created_by = 'platform'`** di AcquaSorgente: completi di form e foto
  (1.401 immagini con nomi originali del telefono, non i posizionali `image_N.jpg` dell'app),
  senza uuid né device né `createdAt`, in arrivo fino al 23/10/2026. Non si duplicano mai. Da dove
  entrino non è stato determinato. Ticket a sé.
- **Perché 153 rilevamenti `device` sono senza uuid**: fenomeno esaurito, ultimo caso il
  28/06/2026, su tutte le versioni e piattaforme. Ipotesi non verificata: schede create da
  versioni che l'uuid non lo generavano, rimaste in coda. Si può verificare nel repo dell'app.
- **Le 102 foto rimaste sulle copie**: la causa esatta non è provata. Si saprebbe leggendo il log
  del canale `ugc` in produzione.

## Divergenze dello strato 1 rispetto a oc:8718

Il codice di `store()`, `findExistingByUuid()`, `fillModelWithRequest()` e `UgcMediaHashService` è copiato
dal package (`origin/main`) senza modifiche alla logica.

**Divergenze rispetto a `origin/main`** (sono le uniche da considerare il giorno dell'aggiornamento
del package):

- **Trait al posto della classe astratta**: nel package la correzione sta in `Abstracts\UgcController`,
  che qui non si tocca; vive nel trait `App\Http\Controllers\Api\Concerns\FindsExistingUgcByUuid`, usato
  da `UgcPoiController` e `UgcTrackController` locali.
- **`UgcMediaHashService` in `App\Services`** invece di `Wm\WmPackage\Services\Models`; cambia solo il
  namespace, la classe base resta `Wm\WmPackage\Services\BaseService`.
- **`UgcDuplicatesService` in `App\Services`** invece di `Wm\WmPackage\Services\Models`, con
  `moveMedia()` **`public`** invece di `private`: la sanatoria (Task 8) è un Artisan command e deve
  spostare i media senza riscrivere la logica. Divergenza deliberata, l'unica di logica: SQL e corpo dei
  metodi sono identici a `origin/main`.
- **`ugc_duplicates_archive.app_id` è `string`, non `integer`**: in osm2cai2 `ugc_pois.app_id` e
  `ugc_tracks.app_id` sono varchar e fra i valori reali c'è `iNaturalist` (103 POI); con un integer
  l'`INSERT ... SELECT` di `UgcDuplicatesService::mergeGroup` fallisce. Corretta la migration di Task 4
  (nata in questo branch, mai eseguita altrove). All'aggiornamento di `wm-package` la sua migration ha la
  guardia `hasTable` e non ricreerà la tabella: la colonna resterà `string`. Il test
  `UgcDuplicatesArchiveSchemaTest` sorveglia che non regredisca.

**Scostamenti rispetto alla versione del package in uso qui** (il codice è invece **identico a
`origin/main`**, quindi non sono divergenze da cercare all'aggiornamento):

- **`fillModelWithRequest` sovrascritto nel trait**: la versione in uso usa
  `addMultipleMediaFromRequest` e non ha lo scarto delle immagini per hash.
- **`getModelIstance()` chiamato senza `$request`** in `store()`: la versione in uso chiama
  `getModelIstance($request)` e cerca l'uuid con `$request->input('properties.uuid')`, che non lo
  vede dentro il campo multipart `feature`; chiamandolo senza `$request` il difetto si aggira senza
  toccare il package.
