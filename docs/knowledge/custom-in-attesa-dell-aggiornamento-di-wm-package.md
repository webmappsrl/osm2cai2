# Custom scritto in attesa dell'aggiornamento di wm-package

Cosa esiste in osm2cai2 solo perché il package è indietro, e che va rimosso quando si aggiorna.

## Come funziona oggi

osm2cai2 usa `wm-package` fermo al commit `451ba071` del **27 maggio 2026**, **188 commit**
dietro a `origin/main`. Nel frattempo nel package sono state corrette cose che qui si continuano
a subire. Quando una di queste correzioni serve subito e l'aggiornamento non è percorribile, la
si **riscrive in casa**: è codice con una scadenza, non una scelta di progetto, e va riconosciuto
come tale da chi lo trova.

Il criterio per distinguere i due casi è uno solo: **la correzione esiste già nel package?**

- **Sì** → è codice temporaneo. Va scritto il più possibile identico all'originale — stessi nomi
  di classi e metodi, stesse firme, stessa logica — e va elencato qui. All'aggiornamento si
  cancella.
- **No** → è codice nostro, e resta anche dopo l'aggiornamento. Non va in questa pagina e non va
  mescolato nei file di quello temporaneo.

### Registro del codice temporaneo (strato 1) — da cancellare all'aggiornamento

Tutto ciò che nasce da oc:8718 (duplicati UGC), riscritto in casa. Ogni file ha in testa il commento
`STRATO 1 di oc:8867 — copia locale destinata a essere cancellata`: `grep -rl "STRATO 1" app` li trova
tutti.

| File o punto | Che cosa è | Equivalente nel package |
|---|---|---|
| `app/Http/Controllers/Api/UgcPoiController.php` | controller locale che sostituisce quello del package | `Wm\WmPackage\Http\Controllers\Api\UgcPoiController` |
| `app/Http/Controllers/Api/UgcTrackController.php` | idem per le tracce | `Wm\WmPackage\Http\Controllers\Api\UgcTrackController` |
| `app/Http/Controllers/Api/Concerns/FindsExistingUgcByUuid.php` | trait con `store()`, `findExistingByUuid()`, `fillModelWithRequest()` | stessi metodi in `Abstracts\UgcController` |
| `app/Services/UgcDuplicatesService.php` | raggruppa i duplicati per uuid, li fonde, archivia e cancella | `Wm\WmPackage\Services\Models\UgcDuplicatesService` |
| `app/Services/UgcMediaHashService.php` | sha256 dei media UGC, per scartare le immagini doppie | `Wm\WmPackage\Services\Models\UgcMediaHashService` |
| `app/Providers/AppServiceProvider.php`, `register()` | i due `bind()` dei controller del package verso quelli locali (blocco con commento «oc:8867») | non serve: i controller del package sono già quelli giusti |
| `config/logging.php`, canale `duplicated-ugc` | ridichiarazione ridondante: il package lo ha già in `config/wm-logging.php` e lo fonde | idem |
| `tests/Feature/Ugc/UgcControllerBindingTest.php`, `UgcDuplicatesServiceTest.php`, `UgcMediaHashServiceTest.php` | testano le classi locali | i test del package (`WmFixDuplicatedUgc*Test`) |

Resta invece, senza modifiche: `tests/Feature/Ugc/UgcStoreDeduplicationTest.php`. Colpisce l'endpoint
`POST /api/v2/ugc/poi/store` e la rotta delle tracce e non le classi locali, quindi dopo l'aggiornamento diventa la
prova che il package fa davvero ciò che qui si è riscritto. Se fallisce, l'aggiornamento non ha portato
la correzione.

**Migration** `database/migrations/2026_10_27_000001_create_ugc_duplicates_archive_table.php`: **non si
cancella**. La tabella `ugc_duplicates_archive` esiste in produzione e contiene le copie archiviate: la
migration del package ha la guardia `hasTable` e non la ricrea. Attenzione: la colonna `app_id` qui è
`string` (in osm2cai2 `ugc_pois.app_id` vale anche `iNaturalist`), nel package è `integer`.
`UgcDuplicatesArchiveSchemaTest` sorveglia che non regredisca.

#### File misto: `app/Console/Commands/FixDuplicatedUgcCommand.php`

È il porting di `wm:fix-duplicated-ugc` (strato 1) ma porta anche cose che il package non ha: CSV dei
gruppi, tabella dei gruppi multi-utente, guardia «padre senza utente», `--max-distance=0` di default,
`--app-id` validato, e l'aggancio dello strato 2 (`use GroupsByTimestamp`, `--by-timestamp`).
**Non si cancella**: resta come `osm2cai:fix-duplicated-ugc` (è anche quello schedulato alle 06:00 in
`app/Console/Kernel.php`) e all'aggiornamento cambia solo il `use` del servizio. Il command del package,
`wm:fix-duplicated-ugc`, comparirà accanto: sono due command con lo stesso scopo e regole diverse (per esempio la distanza massima), quindi
va schedulato uno solo dei due.

### Codice nostro che resta (strato 2) — con i `use` da cambiare

Ogni file ha in testa `STRATO 2 di oc:8867 — resta anche dopo l'aggiornamento di wm-package`.

| File | Perché resta |
|---|---|
| `app/Services/UgcDuplicatesByTimestampService.php` | riconosce i duplicati senza uuid per utente, istante di compilazione e posizione. Serve solo qui: su camminiditalia tutti i rilevamenti hanno l'uuid (0 su 369), qui no |
| `app/Console/Commands/Concerns/GroupsByTimestamp.php` | aggancia `--by-timestamp` a un command di fusione |
| `app/Console/Commands/RecoverPreviousDuplicateFixCommand.php` | sanatoria dello stato lasciato dal vecchio command di duplicazione, che esiste solo in osm2cai2. È un'operazione una tantum: **dopo che è stata eseguita in produzione si può cancellare** invece di adeguarla |
| `app/Console/Commands/BackfillUgcMediaHashCommand.php` | popola lo sha256 dei media UGC già salvati, che il package calcola solo per quelli nuovi. Anche questo è una tantum |

**Namespace da cambiare all'aggiornamento**, da `App\Services\…` a `Wm\WmPackage\Services\Models\…`:

| File | `use` da cambiare |
|---|---|
| `app/Console/Commands/FixDuplicatedUgcCommand.php` | `App\Services\UgcDuplicatesService` |
| `app/Console/Commands/RecoverPreviousDuplicateFixCommand.php` | `App\Services\UgcDuplicatesService` |
| `app/Console/Commands/BackfillUgcMediaHashCommand.php` | `App\Services\UgcMediaHashService` |
| `tests/Feature/Ugc/RecoverPreviousDuplicateFixTest.php`, `BackfillUgcMediaHashTest.php`, `UgcStoreDeduplicationTest.php` | `App\Services\UgcMediaHashService` |

**Un punto che non è un cambio di namespace e rompe il codice**: `RecoverPreviousDuplicateFixCommand`
chiama `UgcDuplicatesService::moveMedia()`, che qui è `public` ma nel package è `private`
(l'unica divergenza di logica dello strato 1). Dopo l'aggiornamento quella chiamata va in errore. Se la
sanatoria è già stata eseguita in produzione il command si cancella e il problema non esiste; altrimenti
va resa pubblica nel package prima di aggiornare.

I conteggi dei duplicati e l'esito del collaudo sul dump sono in
`docs/features/8867-acquasorgente-duplicazioni-rilevamenti/notes.md`.

### Verifica dopo l'aggiornamento

1. `grep -rl "STRATO 1" app tests` → i file trovati sono quelli della tabella, da cancellare.
2. Cambiati i `use` dello strato 2, `php artisan test --filter=Ugc` deve passare per intero.
3. `php artisan route:list | grep ugc` mostra le rotte del package, e `UgcStoreDeduplicationTest` passa
   anche con `route:cache`.

## Perché così

- **Riscrivere invece di aggiornare** (oc:8867): l'aggiornamento di 188 commit di package è un
  lavoro a sé, con il suo rischio; il difetto dei duplicati produceva una quindicina di record in
  eccesso al mese e non poteva aspettarlo.
- **Copia il più fedele possibile invece di una riscrittura migliore** (oc:8867): il valore di
  questo codice è di poter essere **cancellato senza pensarci** il giorno dell'aggiornamento. Ogni
  miglioria introdotta qui è una differenza da riconciliare a mano in quel momento, cioè il
  contrario di quello che serve. Le divergenze inevitabili — dipendenze che in questa versione
  non esistono — vanno annotate nel `notes.md` del lavoro che le introduce.
- **Due strati separati e mai mescolati** (oc:8867): il codice temporaneo e quello definitivo
  stanno in file diversi, e il secondo richiama il primo solo attraverso metodi che esistono
  anche nell'originale del package. Se fossero nello stesso file, all'aggiornamento si
  dovrebbe separarli a mano, rileggendo riga per riga un codice scritto mesi prima.
- **Una pagina sola, per area** (oc:8867): il registro sta qui e non nei singoli `notes.md` dei
  cantieri, perché la domanda a cui risponde — «cosa devo togliere quando aggiorno?» — se la fa
  chi aggiorna, che non sa quali ticket siano passati di qui.

## Come ci siamo arrivati

- **Portare la correzione nel package** (oc:8867, scartata): l'avrebbe risolta per tutti, ma
  osm2cai2 non può prendersi l'aggiornamento adesso, quindi non avrebbe risolto il problema del
  cliente. Lo stesso criterio aggiuntivo per i duplicati senza uuid è stato valutato per il
  package e scartato: su camminiditalia non troverebbe nulla.
