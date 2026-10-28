> Ticket: oc:8867

# AcquaSorgente — Duplicazioni rilevamenti

## Cosa cambia

Oggi ogni reinvio di un rilevamento da parte dell'app crea un nuovo record: il controllo che
dovrebbe riconoscere un rilevamento già salvato cerca il suo `uuid` al primo livello della
richiesta, mentre l'app lo manda dentro il campo `feature`. Dopo questo intervento lo store
riconosce il rilevamento già presente e lo **aggiorna** invece di duplicarlo, e i record
duplicati già in archivio vengono uniti in uno solo, con tutte le fotografie sul record che
resta. Viene inoltre sanato quanto lasciato indietro dall'esecuzione precedente del command di
bonifica: fotografie rimaste su copie nascoste, record sporcati da informazioni di duplicazione
che non li riguardano, copie ancora in tabella sotto un utente di servizio.

## Perché

Il cliente segnala record di monitoraggio sdoppiati, con dati identici ma non sempre completi —
una copia con la foto e una senza. L'esempio che porta riguarda AcquaSorgente, ma il difetto sta
nello store, che è lo stesso per tutte le app e per entrambi i tipi di rilevamento. Sui dati di
produzione del 26/10/2026:

| tabella | app | gruppi | record in eccesso |
|---|---|---|---|
| `ugc_pois` | AcquaSorgente | 77 | 103 |
| `ugc_pois` | osm2cai | 48 | 66 |
| `ugc_pois` | SICAI | 3 | 5 |
| `ugc_tracks` | osm2cai | 58 | 71 |
| `ugc_tracks` | SICAI | 5 | 6 |
| `ugc_tracks` | AcquaSorgente | 1 | 1 |
| | **totale** | **192** | **252** |

Su AcquaSorgente il fenomeno **continua a produrne una quindicina al mese**: 7 nei primi 26 giorni
di ottobre, 14 a settembre, 18 ad agosto.

Per le **tracce** la situazione è peggiore che per i POI: `UgcTrackController::getModelIstance()`
fa `return new UgcTrack` e basta, senza nemmeno il controllo difettoso. Ogni invio di una traccia
crea sempre una riga nuova, e nessun command le ha mai bonificate — quello esistente tratta solo
i POI.

La causa è provata. In `wm-package`, `UgcPoiController::getModelIstance()` legge
`$request->input('properties.uuid')`; l'app invia un multipart con il geojson serializzato nel
campo `feature`, dove quella lettura restituisce `null`, e la riga successiva fa
`return new UgcPoi`. La validazione invece il campo `feature` lo apre
(`Controller.php:45-49`), quindi l'uuid finisce comunque dentro il record salvato: da qui
duplicati che portano tutti lo stesso uuid. Verificato sulla stessa richiesta nei due formati:
dove la validazione legge `f7353f52…`, il controllo anti-duplicato legge `null`.

Il reinvio nasce dall'app: in `_pushUgcPois` il record esce dalla coda locale solo se la risposta
è positiva, e `saveApiPoi` trasforma qualunque errore in `null`, quindi il record viene rimandato
alla sincronizzazione successiva. I due record citati dal cliente, `10828` e `10830`, hanno lo
stesso uuid e lo stesso istante di compilazione, e sono stati salvati a diciotto ore di distanza.

Lo stesso difetto è già stato corretto nel package su **oc:8718** (7 ottobre 2026) e bonificato su
camminiditalia il 12 ottobre. osm2cai2 non lo ha perché è fermo a una versione precedente di
`wm-package`, che non si può aggiornare adesso: per questo la correzione va riscritta qui.

## Requisiti

- [ ] Lo store riconosce un rilevamento già salvato leggendo l'`uuid` dai **dati validati**, non da
      `$request->input()`, e limita la ricerca ai rilevamenti **dell'utente autenticato**
- [ ] La protezione vale sia per i **POI** sia per le **tracce**, che oggi non ne hanno alcuna
- [ ] Il **webhook da geohub viene chiuso**: rotte `api/webhook/ugc/poi` e `api/webhook/ugc/track`
      e `app/Http/Controllers/WebhookController.php` vengono rimossi. Era il ponte usato durante la
      migrazione, quando le vecchie app inviavano ancora a geohub e geohub inoltrava qui; oggi
      quelle app non permettono più l'invio di nuovi rilevamenti e dal webhook non arriva nulla
      dal 01/04/2026 (POI) e dal 08/11/2025 (tracce). Finché resta, è un percorso non autenticato
      che aggira la protezione, perché istanzia il controller del package con la classe cablata
      (`WebhookController.php:25`)
- [ ] Prevenzione e bonifica valgono per **tutte le app** (`app_id` 1, 2 e 3), non solo per
      AcquaSorgente
- [ ] I controller locali sostituiscono quelli del package **ribindandoli nel container**, non
      riscrivendo le rotte. Il package carica lo stesso file di rotte due volte
      (`WmPackageServiceProvider.php:96-105`, prefissi `api/v2` e `api`), quindi di ogni store
      esistono due varianti: un override per rotta dovrebbe coprirle tutte e quattro, e riusare i
      loro nomi farebbe fallire `php artisan route:cache` — che il deploy esegue dentro
      `php artisan optimize`. Il bind lascia intatti nomi e prefissi, e al momento
      dell'aggiornamento del package si rimuove una riga sola
- [ ] Il bind è accompagnato da un commento che ne spiega il motivo: chi legge `routes/api.php`
      non vede che il controller è stato sostituito
- [ ] Lo store continua a riscrivere la geometria come fa il package, **senza divergenze**: la
      modifica voluta dall'utente passa da un endpoint diverso (`/api/v3/ugc/poi/edit`), mentre
      sullo store arrivano solo reinvii, che portano sempre la stessa posizione
- [ ] Quando lo trova, aggiorna il record esistente e risponde `201` con il suo id, invece di
      crearne uno nuovo
- [ ] Le `properties` ricevute si **uniscono** a quelle salvate, non le sostituiscono, come nel
      package e senza divergenze. Effetto collaterale noto e accettato: un campo svuotato dal
      client non si svuota sul server. Riguarda solo lo store, dove arrivano reinvii che mandano
      sempre lo stesso contenuto; la correzione voluta dall'utente passa da `/api/v3/ugc/poi/edit`
- [ ] Una fotografia si salva solo se il suo `sha256` è diverso da quelle già presenti sul record
- [ ] L'unico posto dove finiscono le copie rimosse è la tabella `ugc_duplicates_archive`
      introdotta da oc:8718: niente più marcature `properties.duplicated` né riassegnazioni
- [ ] Esiste una tabella `ugc_duplicates_archive` con la stessa struttura di quella di oc:8718
      (`model_type`, `original_id`, `reference_id` senza vincolo di chiave esterna, uuid, utente,
      app, properties, media_ids, geometria, date originali)
- [ ] Un command di bonifica raggruppa i duplicati **per uuid**, tiene il record più vecchio, vi
      sposta le fotografie scartando i doppioni per hash, archivia le copie e le cancella
- [ ] Il command tratta sia `ugc_pois` sia `ugc_tracks`, con un'opzione per limitarlo a uno dei due
- [ ] Il command ha una modalità di sola segnalazione come comportamento di default, e applica le
      modifiche solo con `--execute`
- [ ] Un gruppo si fonde **solo se le geometrie dei suoi record sono identiche**; se differiscono
      non viene toccato e finisce nell'elenco «da verificare». Niente soglia metrica: un reinvio
      porta gli stessi byte e non ricalcola la posizione — verificato su **192 gruppi su 192**,
      POI e tracce, geometria identica in tutti. Una geometria diversa significa invece che
      l'utente ha spostato il punto da `POST /api/v3/ugc/poi/edit` (`ugc.effects.ts:283-285`,
      `poiDrawnGeometry`), cioè una volontà espressa che un command non deve ribaltare.
      Il criterio vale uguale per POI e tracce, senza bisogno di un confronto specifico per le linee
- [ ] Un gruppo che attraversa **più utenti** viene fuso tenendo comunque il record più vecchio,
      ma compare in una sezione a sé del report (`gruppi multi-utente`), così prima di eseguire si
      vede a chi resta assegnato ciascun rilevamento. Riguarda **13 gruppi di tracce, 30 record,
      17 in eccesso**; sui POI non se ne presenta nessuno (0 su 128)
- [ ] Un gruppo in cui il record più vecchio **non ha utente** (`user_id` NULL) mentre una copia ce
      l'ha **non si fonde**: finisce in «da verificare (padre senza utente)», distinto da quello per
      geometria diversa, perché fonderlo lascerebbe il rilevamento senza proprietario. Tre casi
      reali, tutte tracce (`235`/`419`, `302`/`320`, `286`/`336`). Se il padre ha un utente e la
      copia è NULL, o sono NULL tutti, la fusione procede: non si perde nessuna attribuzione
- [ ] Il command riconosce anche i duplicati **senza uuid**, raggruppando per utente, istante di
      compilazione (`properties.createdAt`) e posizione — attivabile con un'opzione dedicata,
      spento per default
- [ ] I rilevamenti privi sia di uuid sia di istante di compilazione non vengono mai uniti: con
      `--by-timestamp` il command stampa (e scrive nel log) quanti sono, insieme a quelli esclusi perché
      privi di utente o di geometria, perché restino da revisionare a mano (un conteggio per tipo e
      motivo, non un elenco)
- [ ] Le fotografie scartate come doppioni si cancellano **dopo** il commit della transazione

### Sostituzione del command esistente

Il command `osm2cai:fix-duplicated-ugc-pois` **viene sostituito, non affiancato**: due logiche di
fusione che agiscono sugli stessi record sono una fonte di divergenza, e all'aggiornamento del
package diventerebbero tre.

- [ ] Il command conserva la **schedulazione notturna in sola segnalazione** e il flag `--execute`
      per applicare: il comportamento di default non cambia, chi lo lancia oggi non si trova un
      command che cancella
- [ ] Il file del command attuale viene **eliminato**, non deprecato: finché resta eseguibile,
      qualcuno può lanciarlo con `--execute` e riprodurre lo stato che stiamo sanando
- [ ] Il nome diventa `osm2cai:fix-duplicated-ugc`, perché non tratta più i soli POI, e il vecchio
      nome **non resta come alias**: chi lo lancia deve ricevere un errore, non eseguire
      silenziosamente il command nuovo. In `docs/superpowers/plans/2026-05-05-migrazione-server-cai.md:43`
      esiste una riga pronta da copiare con `--execute=1`: con un alias trasparente cancellerebbe
      record senza che nessuno l'abbia deciso
- [ ] I due riferimenti al vecchio nome — `app/Console/Kernel.php` e il documento di migrazione —
      vengono aggiornati in questo stesso lavoro
- [ ] Spariscono dal codice e dai dati: la creazione dell'utente di servizio, la marcatura
      `properties.duplicated`, `duplication_info`, `has_been_duplicated` e la riassegnazione delle
      copie
- [ ] Il confronto delle fotografie **non scarica più le immagini da AWS**: l'hash si legge da
      `custom_properties.sha256` e si calcola al volo solo per i media che non ce l'hanno. È il
      punto debole del command attuale: oggi, se il download fallisce, il media esce in silenzio
      dalla lista di quelli da spostare. La causa delle fotografie rimaste sulle copie marcate non è
      provata
- [ ] Viene aggiunta la guardia sulle geometrie, nella forma definita sopra: si fonde solo a
      geometrie identiche, senza soglie metriche
- [ ] Il report in CSV sotto `storage/backups` **resta**, perché è il modo in cui oggi si
      revisionano i gruppi prima di applicare; si aggiunge il log sul canale dedicato

### Sanatoria dell'esecuzione precedente

- [ ] Per ogni copia marcata `properties.duplicated`, il record tenuto si ritrova da
      `duplication_info.parent_ugc_poi_id`; le fotografie ancora attaccate alla copia le vengono
      spostate addosso, con lo stesso scarto per hash
- [ ] Le copie così trattate vengono archiviate in `ugc_duplicates_archive` e cancellate, come
      quelle della bonifica ordinaria
- [ ] I record che hanno ereditato `duplication_info` o `has_been_duplicated` senza essere copie
      vengono ripulite di quelle chiavi
- [ ] L'utente originale di ogni copia viene **ripristinato** da
      `duplication_info.parent_ugc_poi_user_id` prima dell'archiviazione, così l'archivio registra
      la persona che ha fatto il rilevamento e non l'utente di servizio
- [ ] A fine lavoro l'utente di servizio `7736` (`duplicated-ugc-pois@webmapp.it`) non ha più
      nessun record assegnato e **viene eliminato**: non deve restare nel sistema
- [ ] Nessun nuovo utente di servizio viene creato: il command non ne ha più bisogno, perché le
      copie si archiviano e si cancellano
- [ ] Una copia il cui padre non esiste più (**2 casi su 660**, copie `506` e `513`) è l'unico
      superstite del suo gruppo, non è copia di niente: **non si cancella**. Se l'utente originale
      in `duplication_info` esiste, le si ripristina `user_id` e le si tolgono `duplicated`,
      `duplication_info` e `has_been_duplicated`, e diventa un rilevamento normale del suo autore;
      resta comunque nell'elenco da guardare, perché il caso è anomalo. Se l'utente originale manca
      o non esiste, o se il padre è a sua volta una copia marcata, la copia resta com'è e si segnala
- [ ] Bonifica e sanatoria si collaudano **in locale sul dump di produzione**, verificando che il
      conteggio del dry-run coincida con quello che il command poi cancella. I numeri di
      riferimento sono 192 gruppi, 252 record in eccesso, 660 copie marcate, 536 fotografie
      attaccate alle copie marcate (su 295 copie); in produzione saranno cresciuti (~15 record in eccesso al mese su
      AcquaSorgente), quindi il dry-run si rifà lì prima di eseguire
- [ ] La prevenzione si collauda in locale **con le rotte in cache** (`route:cache` +
      `config:cache`, come in produzione dopo `optimize`), perché è lì che il bind del controller
      nel container può non valere; a fine prova `config:clear`
- [ ] **Lo scarto delle fotografie per hash non è provabile sul solo dump**: i file stanno su
      Aruba e nessuno dei 21.625 media ha `sha256` salvato, quindi in locale `hashOf()` torna
      `null` e ogni media viene spostato invece che scartato. Si prova **in produzione**, dove il
      dry-run è a sola lettura e non cancella nulla: stampa quante fotografie sposterebbe e quante
      scarterebbe, leggendo lo sha256 già nei `custom_properties` (dopo il popolamento) senza calcolarlo mai:
      un media senza hash conta come «da spostare» e viene segnalato a parte
- [ ] Il popolamento di `custom_properties.sha256` è un **passaggio a sé**, da far girare prima
      del dry-run: così il dry-run resta a sola lettura e l'hash non va ricalcolato ogni volta.
      Serve comunque a regime, visto che oggi nessun media ce l'ha
- [ ] Ordine di esecuzione in produzione, **vincolante**: backup del database
      (`php artisan wm:backup-run --only-db`, lo stesso command dello schedulato delle 20:00: carica lo
      zip su Aruba in `backups/osm2cai2/`; poi `wm:download-db-backup --latest` per tenerne una copia
      sul server, da rinominare subito perché lo schedulato delle 20:10 riscrive lo stesso nome)
      → popolamento degli hash → sanatoria
      (`recover-previous-duplicate-fix`, dry-run, lettura dei numeri, poi `--execute`) → bonifica per uuid
      (`fix-duplicated-ugc`, dry-run, lettura dei numeri, poi `--execute`) → **solo dopo**, e solo se
      serve, il raggruppamento per istante (`--by-timestamp`, dry-run e poi `--execute`). Il perché: fondendo
      una copia marcata dalla vecchia bonifica, le chiavi `duplicated`, `duplication_info` e
      `has_been_duplicated` passerebbero sul record tenuto, e la sanatoria non la ripulirebbe più (esclude
      ogni riga con la chiave `duplicated`): un record legittimo resterebbe marcato per sempre. Per questo
      `fix-duplicated-ugc` **si ferma con un errore**, in dry-run come con `--execute`, se in `ugc_pois`
      esistono ancora righe con `properties->duplicated`
- [ ] La sanatoria va eseguita **prima** della bonifica e quindi anche del raggruppamento per istante, e `--by-timestamp`
      **esclude** i record marcati `properties.duplicated`. Sul dump di produzione le 660 copie
      marcate hanno tutte lo stesso utente di servizio, e quelle senza uuid (162 record, 58 gruppi)
      avrebbero formato gruppi per istante: fonderle fra loro avrebbe lasciato il padre vero
      (`duplication_info.parent_ugc_poi_id`, che ha sempre l'uuid) senza le sue fotografie, cioè il
      danno da riparare rifatto da capo. Con l'esclusione i due trattamenti non si sovrappongono per
      costruzione, e sul dump `--by-timestamp` non trova alcun gruppo: la sanatoria fa già tutto il
      lavoro, l'opzione resta per i casi futuri

## Rischi

**Fotografie sulle copie marcate dalla vecchia bonifica: il danno non c'è.** Le fotografie
attaccate alle copie marcate sono **536**, su **295 copie** (app 1: 388, app 3: 148), e **nessuna
copia ha fotografie mentre il record tenuto ne è priva**: ogni record tenuto che ha copie
fotografate ha già fotografie proprie (535 su 536 hanno anche un media della stessa dimensione sul
padre). Quindi nessun cliente sta perdendo immagini: la sanatoria le sposta perché è giusto che
stiano sul record che resta, non per recuperare qualcosa di perso. Il numero «102 fotografie
rimaste sulle copie», usato nelle versioni precedenti di questo documento, non è riproducibile da
alcun conteggio ed è sbagliato.

**Perdita di fotografie durante la bonifica ordinaria.** In 57 gruppi su 77 il record più vecchio —
quella che il command tiene — non ha fotografie, e sui duplicati ce ne sono **139**. Se la bonifica
non spostasse i media, il cliente vedrebbe record singoli ma senza foto, cioè un danno peggiore di
quello segnalato. Il command attuale i media li sposta (`FixDuplicatedUgcPois.php:386`), ma solo
con `--execute` e solo dopo lo scarto per hash. Mitigazione: `moveMedia` sposta i file **prima** del `DELETE`, dentro la stessa
transazione; al momento della cancellazione le copie non hanno più media agganciati. Le foto
scartate perché identiche per hash si cancellano dopo il commit, perché Spatie rimuove il file
subito e un rollback non lo riporta indietro.

**Fusione di monitoraggi legittimi.** Una sorgente viene monitorata più volte nel tempo, dallo
stesso utente e nello stesso punto: sui dati di produzione ci sono **1.121 coppie** di questo tipo,
inclusa una sorgente rilevata ogni mese per un anno. Un criterio basato su utente e posizione le
unirebbe, cancellando una serie storica di misure. Mitigazione: il raggruppamento senza uuid usa
**l'istante di compilazione sul telefono**, che resta identico nei reinvii e cambia sempre fra due
monitoraggi distinti; verificato su 174 coppie di duplicati e 1.121 di monitoraggi successivi,
senza falsi positivi.

**Il bind nel container potrebbe non valere per le rotte in cache.** Tutta la prevenzione passa da
lì: se il dispatcher non risolvesse il controller dal container, in produzione — dove le rotte
sono in cache dopo `php artisan optimize` — resterebbe attivo quello del package e il difetto
sarebbe ancora lì, senza che nulla lo segnali. Mitigazione: è la **prima verifica del piano**, con
una prova su rotte messe in cache, prima di costruirci sopra il resto.

**La bonifica può spostare la proprietà di un rilevamento fra utenti diversi.** Lo store cerca il
rilevamento già salvato solo fra quelli dell'utente autenticato — misura di sicurezza di oc:8718,
perché l'uuid compare nei link di condivisione pubblici — mentre il command raggruppa per uuid.
Sui 13 gruppi di tracce che attraversano più utenti, fondere significa decidere a chi resta la
traccia. Sui dati attuali l'esito è corretto: in 12 gruppi su 13 il record più vecchio è
dell'utente originale (Tamburini, Micheli, Penna, Barbieri, Piccioli) e le copie sono finite sugli
account dei dev in un secondo momento, verosimilmente durante prove di migrazione del 07/01/2025;
nel tredicesimo (`sentiero 119 test`) il gruppo è composto solo da account di dev. Mitigazione: si
tiene il più vecchio, e la sezione dedicata nel report permette di controllare le assegnazioni
prima di eseguire. La regola regge finché le copie arrivano dopo l'originale: se un giorno fosse
il contrario, assegnerebbe il rilevamento al dev.

**Cambiamento di comportamento visibile dall'app.** Un client che oggi riceve sempre un `201` con
un id nuovo comincerà a ricevere l'id del record esistente. È il comportamento voluto, ma va
verificato che l'app lo gestisca: è già in produzione su camminiditalia dal 7 ottobre senza
problemi segnalati.

**Il comportamento delle foto della 3.1.10 differisce da quello su cui oc:8718 è stato tarato.**
Là il reinvio porta con sé tutte le fotografie; qui `10830` — reinvio del 25 ottobre — arriva
**senza**. I test vanno rifatti su questo caso, non dati per buoni da là.

**La sanatoria tocca record già manipolati una volta.** Sono 660 copie marcate e 462 record con
chiavi ereditate, su tutti gli app_id, non solo AcquaSorgente. Le informazioni su cui si basa il
recupero — `duplication_info.parent_ugc_poi_id` — sono state scritte dalla versione precedente del
command e non sono state verificate: una copia il cui padre è stato nel frattempo cancellato, o il
cui riferimento è errato, non deve essere cancellata a sua volta. Mitigazione: il recupero
verifica l'esistenza del padre prima di agire, e ogni caso anomalo finisce nell'elenco da
revisionare invece di essere forzato.

**Divergenza con il package al momento dell'aggiornamento.** Mitigazione: il porting mantiene gli
stessi nomi di classi, metodi e firme di oc:8718, e sta in file separati da quelli del criterio
custom, così all'aggiornamento si elimina il primo strato senza toccare il secondo.

## Out of scope

- **I 1.583 rilevamenti `created_by = 'platform'`**: entrano da un percorso che non è lo store
  dell'app, non si duplicano mai (zero coppie su dati di produzione) e restano fuori dalla
  bonifica. Da dove entrino è una domanda aperta, da trattare in un ticket separato.
- **Gli EcPoi nati dai rilevamenti validati**: la bonifica non li tocca, non li riassegna e non li
  segnala. Un EcPoi è una **promozione decisa da un operatore**, che fotografa quel rilevamento in
  quel momento e da lì ha vita propria: `properties->ugc->ugc_poi_id` è un riferimento storico a
  dove è nato, non una relazione viva, e riscriverlo falsificherebbe quella storia. Cancellare il
  rilevamento non lo danneggia. Dove un gruppo di duplicati ha prodotto **due punti pubblicati**
  — succede, per esempio `19525` e `19527` dalla stessa sorgente — la scelta su quale tenere
  spetta a chi li ha promossi.
  La bonifica non li riscrive ma il sistema ora **sa risalire al padre**: se l'EcPoi era stato
  promosso da una copia cancellata, `checkExistingEcPoi()` lo trova attraverso
  `ugc_duplicates_archive` e impedisce di promuovere di nuovo il padre sopravvissuto (vedi
  `notes.md`, «EcPoi promossi da una copia poi cancellata»). Cambia la lettura, non il dato.
- **Il difetto in `wm-package`**: già corretto in oc:8718, non si tocca. Qui si fa solo il porting.
- **Il raggruppamento per istante di compilazione nel package**: su camminiditalia tutti i
  rilevamenti hanno l'uuid (0 su 369), quindi là non servirebbe a nulla.
- **Il retry lato app**: `saveApiPoi` tratta ogni errore come `null` e rimanda il record. Si
  interviene sul server, che è l'unico lato che possiamo correggere senza una nuova versione
  dell'app.
- **La correzione di `db:download`**, che cerca il dump in un percorso che non esiste più
  (`backups/maphub/osm2cai2/last_dump.sql.gz` invece di `backups/osm2cai2/last_dump.zip`,
  su AWS invece che su Aruba): emersa durante l'analisi, è un difetto a sé.
- **Un indice unico su `properties->uuid`**: due invii nello stesso istante creano ancora due
  righe. Nei dati di produzione non c'è un solo caso — i reinvii distano ore o giorni — quindi è
  un rischio teorico, e l'indice non si può comunque creare finché i duplicati esistono.

## Struttura in due strati

Il lavoro si scrive fin dall'inizio come **due strati separati**, con durata diversa.

**Strato 1 — il cherry-pick.** Prevenzione e bonifica per uuid sono una copia del codice di
oc:8718, tenuta il più possibile **identica all'originale**: stessi nomi di classi e metodi,
stesse firme, stessa struttura dei file, stessa logica riga per riga. Non è il posto dove
migliorare qualcosa. Si scosta dall'originale solo dove una dipendenza non esiste in questa
versione del package, e **ogni scostamento va annotato in `notes.md`** con il motivo: quella lista
è ciò che servirà il giorno dell'aggiornamento per sapere cosa verificare.

**Strato 2 — il codice davvero custom.** Il raggruppamento per istante di compilazione e la
sanatoria dell'esecuzione precedente non esistono nel package e non ci andranno: su camminiditalia
tutti i rilevamenti hanno l'uuid, e la sanatoria riguarda uno stato prodotto da un command che
esiste solo qui. Vivono in file propri, e **non vengono mai mescolati** con quelli dello strato 1.

**Al momento dell'aggiornamento di `wm-package`** si cancellano i file dello strato 1 e si cambia
il namespace delle classi che lo strato 2 richiama, da `App\Services\…` a
`Wm\WmPackage\Services\Models\…`. Resta solo lo strato 2. Perché questo sia un'operazione di
minuti e non una riscrittura:

- [ ] Lo strato 2 richiama lo strato 1 **solo attraverso i metodi pubblici** che esistono anche
      nell'originale del package (`mergeGroup`, `mergedProperties`, `moveMedia`), mai duplicandone
      la logica
- [ ] Nessun file dello strato 1 contiene codice dello strato 2, e viceversa. Il comando di
      bonifica è strato 1 e richiama il raggruppamento custom come un'estensione tenuta in un file
      a sé: all'aggiornamento del package resta solo quella, da agganciare al comando del package
- [ ] I nomi delle classi dello strato 1 coincidono con quelli del package, cambiando solo il
      namespace
- [ ] `notes.md` elenca ogni divergenza dall'originale, con il motivo

## Moduli toccati

Tutto in **osm2cai2**, niente in `wm-package`. La colonna «strato» dice cosa si butta
all'aggiornamento del package (1) e cosa resta (2).

| File | Strato | Cosa |
|---|---|---|
| `app/Http/Controllers/Api/UgcPoiController.php` (nuovo) | 1 | override dello store: uuid dai dati validati, ricerca limitata all'utente, aggiornamento invece di creazione |
| `app/Http/Controllers/Api/UgcTrackController.php` (nuovo) | 1 | stessa protezione per le tracce, che oggi non ne hanno nessuna |
| `app/Http/Controllers/Api/Concerns/FindsExistingUgcByUuid.php` (nuovo) | 1 | store, ricerca per uuid e salvataggio immagini con scarto per hash: nel package stanno nella classe astratta, che qui non si può toccare |
| `app/Providers/AppServiceProvider.php` | 1 | bind dei controller UGC del package verso quelli locali |
| `routes/api.php` | 1 | rimosse le due rotte del webhook (le rotte di store restano quelle del package) |
| `app/Http/Controllers/WebhookController.php` | — | **eliminato**: ponte della migrazione da geohub, non più usato |
| `database/migrations/…_create_ugc_duplicates_archive_table.php` (nuovo) | 1 | tabella archivio, struttura di oc:8718 |
| `app/Services/UgcDuplicatesService.php` (nuovo) | 1 | fusione, spostamento media per hash, archiviazione e cancellazione — porting di oc:8718 |
| `app/Services/UgcMediaHashService.php` (nuovo) | 1 | hash sha256 dei media — porting di oc:8718 |
| `app/Services/UgcDuplicatesByTimestampService.php` (nuovo) | 2 | **strato custom**: raggruppamento per utente, istante di compilazione e posizione |
| `app/Console/Commands/FixDuplicatedUgcPois.php` | — | **eliminato** insieme al suo nome |
| `app/Console/Commands/FixDuplicatedUgcCommand.php` (nuovo) | 1 | comando `osm2cai:fix-duplicated-ugc`: raggruppa per uuid e utente, tratta POI e tracce, `--execute` per applicare |
| `app/Console/Commands/Concerns/GroupsByTimestamp.php` (nuovo) | 2 | opzione per il raggruppamento dei record senza uuid, tenuta fuori dal comando |
| `app/Console/Commands/RecoverPreviousDuplicateFixCommand.php` (nuovo) | 2 | **sanatoria**: recupera le fotografie dalle copie marcate, archivia e cancella, ripulisce le chiavi ereditate |
| `app/Console/Commands/BackfillUgcMediaHashCommand.php` (nuovo) | 2 | popolamento di `custom_properties.sha256`, da far girare prima del dry-run |
| `config/logging.php` | 1 | canale `duplicated-ugc` per il log della bonifica (oggi non esiste) |
| `app/Console/Kernel.php` | 1 | schedulazione invariata, in sola segnalazione, sul nuovo nome |
| `tests/Feature/…` (nuovi) | 1 e 2, in file distinti | store con multipart `feature`, reinvio senza foto (caso 3.1.10), fusione con spostamento media, non fusione di monitoraggi successivi |
