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
all'utente 7736, 462 schede con `duplication_info` ereditato per errore, **536 fotografie
attaccate alle copie** (su 295 copie; app 1: 388, app 3: 148). Zero copie hanno fotografie mentre la
scheda tenuta ne è priva: il danno «schede senza foto» non esiste in questi dati. (Il «102
fotografie rimaste sulle copie» scritto prima era un conteggio errato, non riproducibile.) Dei 660
padri, 658 esistono ancora; le 2 copie senza padre (`506`, `513`, app 1, senza foto, utente
originale 96) sono l'unico superstite del loro gruppo e la sanatoria le restituisce all'autore
senza cancellarle.

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
  media esce in silenzio dalla lista: è un punto debole noto, ma non provato come causa delle
  fotografie rimaste sulle copie.
- «Il perimetro è AcquaSorgente» — **falso**: il difetto è nello store, riguarda POI e tracce di
  tutte le app. 252 record in eccesso, non 103.
- «I backup di produzione sono fermi dal 20/05/2026» — **falso**: quello è il bucket AWS
  `wmdumps`, dismesso. I backup veri girano ogni sera su Aruba (`wmfe`, root `backups/`).
- «I record `platform` sono vuoti» — **falso**: avevo guardato `raw_data`, mentre i dati stanno in
  `properties.form`, popolato in 773 su 776.

## Ordine di esecuzione in produzione (vincolante)

1. `php artisan migrate`
2. `osm2cai:backfill-ugc-media-hash --execute` (popola gli sha256)
3. `osm2cai:recover-previous-duplicate-fix` (dry-run, si leggono i numeri), poi `--execute`
4. `osm2cai:fix-duplicated-ugc` (dry-run, si leggono i numeri, comprese le fotografie che sposterebbe e
   scarterebbe), poi `--execute`
5. Solo se serve: `osm2cai:fix-duplicated-ugc --by-timestamp`, dry-run e poi `--execute`

**La sanatoria va prima della bonifica.** `UgcDuplicatesService::SERVER_PROPERTY_KEYS` protegge dal merge
solo `id`, `created_at`, `updated_at`, `taxonomy_where`, `taxonomyWheres`: fondendo una copia marcata dalla
vecchia bonifica, le chiavi `duplicated`, `duplication_info` e `has_been_duplicated` passano sulla scheda
tenuta. Quella scheda poi non si ripulisce più, perché `ripulisciChiaviEreditate()` esclude per costruzione
ogni riga che porti la chiave `duplicated`: un record legittimo resta marcato in modo permanente. Per questo
`osm2cai:fix-duplicated-ugc` ha una guardia: se in `ugc_pois` esiste anche una sola riga con la chiave
`properties->duplicated` (di qualunque valore) esce con errore e non fa nulla, **sia in dry-run sia con
`--execute`**. In dry-run l'errore è preferito al semplice avviso perché i numeri che il dry-run
annuncerebbe prima della sanatoria non sono quelli della bonifica dopo (le copie marcate cambiano i gruppi),
e perché lo schedulato delle 06:00, che gira in dry-run, deve segnalare la condizione invece di produrre un
report fuorviante. Effetto collaterale da sapere: fino a quando la sanatoria non è stata eseguita in
produzione, lo schedulato delle 06:00 esce con errore ogni giorno. Se dopo la sanatoria restano righe con la
chiave `duplicated` con valore diverso dal boolean `true`, la sanatoria non le tocca e la guardia continua a
fermare la bonifica: vanno riviste a mano.

## Esiti del collaudo sul dump di produzione (27/10/2026)

Eseguito in locale sul database `osm2cai2` (dump del 26/10/2026), nell'ordine che conta: **prima la
sanatoria, poi la bonifica per uuid**, perché i record senza uuid che il raggruppamento per istante
troverebbe sono in realtà copie già marcate dalla vecchia bonifica; fonderli fra loro invece di
riunirli al padre rifarebbe il danno. Tutti i numeri annunciati dal dry-run coincidono con quelli
realizzati.

| Misura | Prima | Dopo la sanatoria | Dopo la bonifica per uuid |
|---|---|---|---|
| `ugc_pois` | 10.002 | 9.344 (-658) | 9.170 (-174) |
| `ugc_tracks` | 1.616 | 1.616 | 1.541 (-75) |
| POI copie marcate `duplicated` | 660 | 0 | 0 |
| POI con chiavi ereditate (non copie) | 462 | 0 | 0 |
| Media UGC (POI 12.778 + tracce 309) | 13.087 | 13.087 | 13.087 |
| Media attaccati alle copie marcate | 536 | 0 | 0 |
| Media orfani (senza record) | 0 | 0 | 0 |
| Utente di servizio 7736 | esiste, 660 POI | eliminato | eliminato |
| `ugc_duplicates_archive` | 0 | 658 | 907 (+249) |
| Media con sha256 | 0 | 0 | 0 |

- **Dry-run, numeri annunciati**: sanatoria 660 copie marcate (658 da sanare, 2 orfane `506`/`513`),
  536 media sulle copie, 462 schede da ripulire; bonifica per uuid 192 gruppi, **252** record in
  eccesso (POI 174 + tracce 78), di cui **249 da unire** (189 gruppi: 128 di POI, 61 di tracce) e 3
  gruppi da verificare per padre senza utente (`235`/`419`, `302`/`320`, `286`/`336`, tracce). Il dry-run
  della bonifica rifatto dopo la sanatoria è risultato **identico** a quello di prima.
- **Realizzato**: 658 copie archiviate e cancellate (0 fallite), 2 orfane ripristinate come rilevamenti
  normali dell'utente 96, 462 schede ripulite, utente 7736 eliminato; poi 189 gruppi uniti, 249 record
  cancellati (174 POI + 75 tracce), 3 gruppi lasciati com'erano (le 6 tracce esistono ancora).
- **I record cancellati sono esattamente quelli annunciati**: l'insieme degli id spariti da
  `ugc_pois`/`ugc_tracks` (907) coincide con quello di `ugc_duplicates_archive`, e i 249 della bonifica
  per uuid sono tutti e soli gli id elencati dal dry-run. Nessun `original_id` archiviato esiste ancora e
  nessun `reference_id` punta a un record inesistente.
- **Nessuna fotografia persa**: 13.087 media UGC prima e dopo, stessi identificativi. **767 media
  riassegnati** al padre (536 dalla sanatoria + 231 dalla bonifica; la somma di `media_ids` in archivio
  è 767) e **0 doppioni scartati**. In locale i file su Aruba non sono leggibili, quindi `hashOf()`
  restituisce `null` e, per scelta prudente, ogni media viene spostato invece che scartato: **lo scarto
  dei doppioni per hash non è esercitato da questo collaudo**, si collauderà solo in produzione dopo il
  popolamento degli hash.
- **`osm2cai:backfill-ugc-media-hash --execute`**: 13.087 media da trattare, 0 hash calcolati, 13.087
  falliti in 13 secondi. Atteso e innocuo in locale (file non leggibili), il command esce con SUCCESS e
  non scrive nulla. In produzione l'esito atteso è il contrario: 0 falliti.
- **`--by-timestamp`**: 0 gruppi per istante di compilazione, **sia prima sia dopo la sanatoria**.
  Prima, perché il raggruppamento esclude già le copie marcate (`23d26a26`); dopo, perché le copie non
  esistono più. Coerente con l'ipotesi che i 162 POI senza uuid duplicati citati nelle misure fossero le
  copie della vecchia bonifica e non reinvii nuovi (non c'è la prova diretta: il conteggio dei 162 non è
  stato rifatto). Resta l'opzione per i casi futuri, spenta per default. Dopo la
  bonifica, con `--by-timestamp`, l'unico risultato sono i 3 gruppi «padre senza utente» già noti.
- **Scostamento sui conti**: le «252 righe in eccesso» di riferimento comprendono i 3 gruppi bloccati
  (3 record), quindi i record cancellati sono 249 e non 252. Non è un errore: la differenza è la guardia.
- **Prevenzione con le rotte in cache**: `route:cache` poi `UgcStoreDeduplicationTest`: 8 passati. Il
  bind nel container sopravvive alla compilazione delle rotte. `config:cache` non si è eseguito (lo ha
  bloccato il sistema dei permessi e non serve: con la config in cache i test girerebbero con il `.env`
  del dump invece del `.env.testing`); a fine prova `route:clear`, nessun file di cache residuo.
- **Suite completa** (`php artisan test`): nessun test in `tests/Feature/Ugc` fallisce. La prima esecuzione
  sul database di test aveva dato «376 passati, 120 falliti», ma non erano regressioni del branch: erano test
  che si appoggiano a dati preesistenti (la tabella `apps` vuota, la colonna `osmfeatures_data` assente) su un
  database `osm2cai2_testing` indietro rispetto al codice. Riallineato l'ambiente, la **baseline reale è di 8
  test falliti, che falliscono identici anche su `develop`** (percorsi, segnaletica ed Elasticsearch): nessuno
  riguarda questo lavoro.

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
- **Le fotografie rimaste sulle copie marcate (536)**: la causa esatta non è provata. Si saprebbe leggendo il log
  del canale `ugc` in produzione.

## Da sapere sullo strato 2

- **La struttura di `ugc_duplicates_archive` è scritta in due punti**: l'INSERT in
  `UgcDuplicatesService::mergeGroup()` (strato 1) e quello in `RecoverPreviousDuplicateFixCommand`
  (strato 2). Voluto: lo strato 2 deve sopravvivere alla cancellazione dello strato 1, quindi non
  dipende da un metodo condiviso. Se la tabella cambia, vanno aggiornati entrambi.

## Limiti noti, decisi di non correggere

- **Le fotografie scartate come doppioni non lasciano traccia prima di essere cancellate.** I loro id non sono
  registrati da nessuna parte (l'archivio `ugc_duplicates_archive.media_ids` ricorda solo quelle *spostate*),
  e se la cancellazione fallisce restano agganciate a un record ormai inesistente (orfane; l'id si legge solo
  nel log `duplicated-ugc`). Esposizione attuale misurata: **zero scarti** nel collaudo (i file su Aruba non
  sono leggibili, quindi nessun hash e nessun doppione scartato).
- **Le bonifiche non considerano la tabella legacy `ugc_media`** (FK con `ON DELETE SET NULL`, 9.247 righe)
  **né i pivot `trail_survey_ugc_poi` e `trail_survey_ugc_track`** (`cascadeOnDelete`): cancellare una copia
  azzera il riferimento nella prima e fa sparire le righe dei secondi. Esposizione attuale misurata: **zero
  record coinvolti** oggi. Il percorso però esiste, e un rilancio futuro su dati più vecchi lo incontrerebbe.

## Divergenze dello strato 1 rispetto a oc:8718

Il codice di `store()`, `findExistingByUuid()`, `fillModelWithRequest()` e `UgcMediaHashService` è copiato
dal package (`origin/main`) senza modifiche alla logica.

**Divergenze rispetto a `origin/main`** (sono le uniche da considerare il giorno dell'aggiornamento
del package):

- **Nome del command**: `osm2cai:fix-duplicated-ugc` invece di `wm:fix-duplicated-ugc`. Il file
  `app/Console/Commands/FixDuplicatedUgcCommand.php` è **misto**: nasce come porting del command del
  package, ma contiene anche l'aggancio dello strato 2 (`use GroupsByTimestamp`, opzione
  `--by-timestamp`, voce `(per istante)` nei report). Per questo non si cancella alla cieca: vedi il
  registro in `docs/knowledge/custom-in-attesa-dell-aggiornamento-di-wm-package.md`.
- **Bind nel container** in `AppServiceProvider::register()` dei due controller del package verso quelli
  locali, al posto di rotte riscritte (il package carica lo stesso file di rotte due volte, con
  prefissi `api/v2` e `api`: riusarne i nomi farebbe fallire `route:cache`). Non esiste nel package.
- **Canale di log `duplicated-ugc` ridichiarato in `config/logging.php`**: il package lo dichiara già in
  `config/wm-logging.php` (anche nella versione in uso) e `WmPackageServiceProvider` lo fonde nella config
  dell'app, quindi quello locale è **ridondante**, con gli stessi valori (`daily`, 14 giorni). Resta per
  non dipendere dal merge; all'aggiornamento si può togliere senza effetti.
- **Schedule**: `Kernel` esegue `osm2cai:fix-duplicated-ugc` alle 06:00 in sola segnalazione, al posto
  del vecchio `osm2cai:fix-duplicated-ugc-pois`.
- **Webhook da geohub rimosso** (`WebhookController` e rotte `webhook/ugc/*`): non è una divergenza dal
  package, è codice di osm2cai2 che non serve più.
- **Trait al posto della classe astratta**: nel package la correzione sta in `Abstracts\UgcController`,
  che qui non si tocca; vive nel trait `App\Http\Controllers\Api\Concerns\FindsExistingUgcByUuid`, usato
  da `UgcPoiController` e `UgcTrackController` locali.
- **`UgcMediaHashService` in `App\Services`** invece di `Wm\WmPackage\Services\Models`; cambia solo il
  namespace, la classe base resta `Wm\WmPackage\Services\BaseService`.
- **`UgcDuplicatesService` in `App\Services`** invece di `Wm\WmPackage\Services\Models`, con
  `moveMedia()` **`public`** invece di `private`: la sanatoria (Task 8) è un Artisan command e deve
  spostare i media senza riscrivere la logica. Divergenza deliberata, l'unica di logica: SQL e corpo dei
  metodi sono identici a `origin/main`.
- **`osm2cai:fix-duplicated-ugc` ha `--max-distance=0` di default**, non `1`: si fonde solo a geometrie
  identiche. Un reinvio porta gli stessi byte e non ricalcola la posizione (verificato su 192 gruppi
  su 192 nei dati di produzione); una geometria diversa significa che l'utente ha spostato il punto da
  un altro endpoint, cioè una volontà espressa che un command non deve ribaltare. Con `--max-distance`
  maggiore di zero si torna al comportamento dell'originale.
- **Report CSV** in `storage/backups/fix-duplicated-ugc-<timestamp>.csv`, che nell'originale non c'è: è
  il modo in cui oggi si revisionano i gruppi prima di applicare. Stesse colonne della tabella a video
  più `utenti` (`id:user_id` di ogni record del gruppo, padre per primo; `-` se `user_id` è NULL).
  Senza `--execute` ha nome fisso (`fix-duplicated-ugc.csv`) e si sovrascrive a ogni esecuzione: è un
  report rigenerabile, e l'esecuzione schedulata delle 06:00 non accumula file. Con `--execute` porta il
  timestamp, perché quell'esecuzione è irripetibile e la sua traccia si conserva. Con zero gruppi non
  si scrive nulla. Il canale di log `duplicated-ugc` è `daily` con 14 giorni di rotazione.
- **Sezione `gruppi multi-utente`**: una seconda tabella a video con i gruppi i cui record
  appartengono a più utenti, così prima di eseguire si vede a chi resterebbe assegnato ciascun
  rilevamento. Sui dati di produzione sono 13 gruppi di tracce (30 record), dove le copie sono finite
  sugli account di alcuni sviluppatori durante vecchie prove di migrazione. Regola decisa: si tiene
  comunque il record più vecchio (in 12 casi su 13 è dell'utente originale); i gruppi devono solo
  essere visibili nel report prima dell'esecuzione. Nel conteggio degli utenti distinti un `user_id` NULL non conta: i gruppi sono 13, quelli con un NULL mescolato a un solo utente reale non compaiono (i bloccati per padre senza utente restano visibili con il loro esito).
- **Guardia sul padre senza utente**: un gruppo il cui record più vecchio ha `user_id` NULL mentre una
  copia ha un utente non si fonde ed esce come `da verificare (padre senza utente)` (se vale anche la
  geometria diversa, `da verificare (geometria diversa, padre senza utente)`). Fonderlo lascerebbe il
  rilevamento senza proprietario, buttando l'attribuzione a un utente vero: si segnala invece di forzare.
  Sui dati di produzione sono 3 gruppi di tracce (`235`/`419`, `302`/`320`, `286`/`336`). Padre con
  utente e copia NULL, o tutti NULL, si fondono normalmente. La guardia sta nel command, non in
  `UgcDuplicatesService`. Il report CSV, inoltre, non si scrive se non ci sono gruppi da riportare.
- **`--app-id` non numerico è un errore**: l'originale lo castava a `(int)`, quindi `--app-id=iNaturalist`
  diventava `0` e restituiva zero gruppi in silenzio (e `iNaturalist` è un valore reale su 103 POI). Il
  servizio filtra per id intero, quindi il command esce con FAILURE e un messaggio esplicito.
- **Riepilogo per esito, cancellazione dei media protetta, CSV verificato prima delle fusioni**: il command
  stampa la ripartizione "N da unire; N da verificare (motivo)"; ogni `delete()` di media doppione è in
  try/catch (logga l'id del media e prosegue) e a fine run riporta cancellati e falliti; la cartella del
  report si controlla prima di toccare i dati, così un errore di scrittura non arriva a fusioni già fatte.
- **`ugc_duplicates_archive.app_id` è `string`, non `integer`**: in osm2cai2 `ugc_pois.app_id` e
  `ugc_tracks.app_id` sono varchar e fra i valori reali c'è `iNaturalist` (103 POI); con un integer
  l'`INSERT ... SELECT` di `UgcDuplicatesService::mergeGroup` fallisce. Corretta la migration di Task 4
  (nata in questo branch, mai eseguita altrove). All'aggiornamento di `wm-package` la sua migration ha la
  guardia `hasTable` e non ricreerà la tabella: la colonna resterà `string`. Il test
  `UgcDuplicatesArchiveSchemaTest` sorveglia che non regredisca.
- **Tie-break diversi fra store e bonifica** (in parità con il package): lo store sceglie il record esistente
  con `orderBy('id')` (`findExistingByUuid()`), la bonifica sceglie il padre con `ORDER BY created_at, id`
  (`UgcDuplicatesService::groups()`). Se `created_at` non segue l'ordine degli id (record importati o
  riscritti) i due criteri possono indicare padri diversi: il reinvio aggiorna una riga, la bonifica ne
  tiene un'altra. Non si corregge qui, per non divergere dal package.
- **Dry-run con la stima delle fotografie** (assente nel package): `osm2cai:fix-duplicated-ugc` senza
  `--execute` stampa quante fotografie dei gruppi da unire si sposterebbero e quante si scarterebbero come
  doppioni, con la stessa regola di `moveMedia()`, leggendo solo `custom_properties.sha256` e senza mai
  chiamare `hashOf()` (che scarica il file e scrive l'hash). Un media senza hash conta come «da spostare» e
  si segnala a parte, quindi senza il popolamento la stima degli scarti è per difetto. Un test lo verifica,
  compreso che non scriva nulla.
- **Conteggio dei senza uuid non trattabili** (strato 2): con `--by-timestamp`, a fine esecuzione, il command
  stampa e scrive nel log quanti rilevamenti senza uuid il raggruppamento esclude (senza istante di
  compilazione, senza utente, senza geometria; per il primo motivo che si applica, in quest'ordine). Sul dump
  collaudato sono 1.973 (1.849 POI e 124 tracce, tutti per mancanza dell'istante, compresi 3 POI e 5 tracce
  con `properties` NULL). Verosimilmente in gran parte i rilevamenti `platform` descritti nei follow-up
  (senza uuid né `createdAt`, che non si duplicano mai; non verificato rilevamento per rilevamento): il
  numero non è una stima dei duplicati ma di ciò che il criterio non sa valutare.
- **Guardia sull'ordine di esecuzione** (strato 2, nel command misto): vedi la sezione «Ordine di esecuzione».
- **Il model_type dei media si legge sempre da `getMorphClass()`**: i command usano i modelli
  `App\Models\UgcPoi/UgcTrack` e ne ricavano il morph, senza stringhe letterali. Oggi il morph di quelle
  classi coincide con quello dei modelli del package grazie alla morphMap in `AppServiceProvider::boot()`.

**Scostamenti rispetto alla versione del package in uso qui** (il codice è invece **identico a
`origin/main`**, quindi non sono divergenze da cercare all'aggiornamento):

- **`fillModelWithRequest` sovrascritto nel trait**: la versione in uso usa
  `addMultipleMediaFromRequest` e non ha lo scarto delle immagini per hash.
- **`getModelIstance()` chiamato senza `$request`** in `store()`: la versione in uso chiama
  `getModelIstance($request)` e cerca l'uuid con `$request->input('properties.uuid')`, che non lo
  vede dentro il campo multipart `feature`; chiamandolo senza `$request` il difetto si aggira senza
  toccare il package.
