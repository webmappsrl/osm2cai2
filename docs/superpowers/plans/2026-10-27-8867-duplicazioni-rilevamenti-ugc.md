# Duplicazioni rilevamenti UGC (oc:8867) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lo store UGC riconosce un rilevamento già salvato invece di duplicarlo, i duplicati già in archivio vengono uniti e archiviati, e lo stato lasciato dalla bonifica precedente viene sanato.

**Architecture:** Due strati separati. Lo **strato 1** è un cherry-pick di oc:8718 da `wm-package/origin/main` dentro `osm2cai2`, con gli stessi nomi di classi, metodi e firme dell'originale: si cancella il giorno in cui il package viene aggiornato. Lo **strato 2** è codice che nel package non esiste e non ci andrà (raggruppamento per istante di compilazione, sanatoria della vecchia bonifica) e resta. I controller locali sostituiscono quelli del package tramite bind nel container, non riscrivendo le rotte.

**Tech Stack:** Laravel 11, PHP 8.4, PostgreSQL/PostGIS, Spatie MediaLibrary, PHPUnit con database PostGIS reale.

**Spec:** `docs/features/8867-acquasorgente-duplicazioni-rilevamenti/overview.md`

## Global Constraints

- Tutto in **osm2cai2**: nessuna modifica dentro `wm-package/`.
- Strato 1 = copia fedele di oc:8718: stessi nomi di classi, metodi e firme; cambia solo il namespace (`Wm\WmPackage\Services\Models\…` → `App\Services\…`). **Ogni scostamento dall'originale va annotato in `docs/features/8867-acquasorgente-duplicazioni-rilevamenti/notes.md`** con il motivo.
- Strato 2 non contiene codice dello strato 1 e lo richiama solo tramite i metodi pubblici che esistono anche nell'originale (`groups`, `mergeGroup`, `mergedProperties`, `maxDistanceMeters`).
- Prevenzione e bonifica valgono per **tutte le app** (`app_id` 1, 2, 3) e per **entrambi** i tipi (`ugc_pois`, `ugc_tracks`).
- Il comportamento di default di ogni command è la **sola segnalazione**; si applica solo con `--execute`.
- Il canale di log per la bonifica è `duplicated-ugc`, da aggiungere a `config/logging.php` (oggi non esiste; esiste `ugc`).
- Le migration locali non sono `.stub`: il file va in `database/migrations/` con nome `2026_10_27_000001_create_ugc_duplicates_archive_table.php`.
- Il morph delle UGC in questo progetto è `App\Models\UgcPoi` / `App\Models\UgcTrack` (verificato sui 12.778 + 309 media in tabella): `getMorphClass()` li restituisce già corretti, il codice di oc:8718 non va adattato.
- Formattazione con `composer format` (Pint) prima di ogni commit.

## Review Focus

Classi di input che la spec implica ma che nessun task esercita da solo; ognuna ha il suo test aggiunto al task che possiede il codice.

1. **Richiesta senza `feature`, con i campi al primo livello** (form classico, non multipart): `findExistingByUuid` legge dai dati validati, che il package normalizza in entrambi i formati — ma nessun test lo prova. → test in Task 2.
2. **Stesso uuid, utente diverso**: non deve mai unire, perché l'uuid compare nei link di condivisione pubblici. → test in Task 2.
3. **Reinvio con `properties` più povere della scheda salvata** (il caso `10830`, che arriva senza foto): il merge non deve svuotare le chiavi già presenti. → test in Task 2.
4. **Media il cui file non è leggibile** (assente su Aruba): `hashOf` torna `null` e il media va **spostato**, non scartato — altrimenti si perde una foto. → test in Task 5.
5. **Copia il cui padre non esiste più** (2 casi su 660 in produzione): la sanatoria non deve cancellarla, deve segnalarla. → test in Task 8.

---

### Task 1: Bind dei controller locali nel container, con rotte in cache

È la prima verifica del piano: se il dispatcher non risolve il controller dal container, tutta la prevenzione non arriva in produzione. In questo task i controller locali non cambiano ancora comportamento, estendono soltanto quelli del package.

**Files:**
- Create: `app/Http/Controllers/Api/UgcPoiController.php` (strato 1)
- Create: `app/Http/Controllers/Api/UgcTrackController.php` (strato 1)
- Modify: `app/Providers/AppServiceProvider.php:14-17` (metodo `register`)
- Test: `tests/Feature/Ugc/UgcControllerBindingTest.php`

**Interfaces:**
- Produces: `App\Http\Controllers\Api\UgcPoiController extends Wm\WmPackage\Http\Controllers\Api\UgcPoiController`, idem per `UgcTrackController`. I task successivi aggiungono il trait a queste due classi.

- [ ] **Step 1: Scrivi il test che fallisce**

```php
// tests/Feature/Ugc/UgcControllerBindingTest.php
public function test_il_container_risolve_i_controller_ugc_locali(): void
{
    $this->assertInstanceOf(
        \App\Http\Controllers\Api\UgcPoiController::class,
        app(\Wm\WmPackage\Http\Controllers\Api\UgcPoiController::class)
    );
    $this->assertInstanceOf(
        \App\Http\Controllers\Api\UgcTrackController::class,
        app(\Wm\WmPackage\Http\Controllers\Api\UgcTrackController::class)
    );
}
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `docker exec php-osm2cai2 php artisan test --filter=UgcControllerBindingTest`
Expected: FAIL, `Class "App\Http\Controllers\Api\UgcPoiController" does not exist`

- [ ] **Step 3: Crea i due controller locali**

Estendono il controller omonimo del package e non aggiungono nulla. In cima a ciascuno un commento che dice: sostituisce il controller del package via bind in `AppServiceProvider`, strato 1 di oc:8867, si elimina all'aggiornamento di `wm-package`.

- [ ] **Step 4: Registra il bind in `AppServiceProvider::register()`**

```php
// oc:8867 — lo store del package duplica i rilevamenti a ogni retry dell'app (uuid letto da
// $request->input() invece che dai dati validati). Finché wm-package non è aggiornato, le rotte
// del package risolvono questi controller. Le rotte non vengono riscritte: il package carica lo
// stesso file due volte (prefissi api/v2 e api) e riusarne i nomi farebbe fallire route:cache.
$this->app->bind(\Wm\WmPackage\Http\Controllers\Api\UgcPoiController::class, \App\Http\Controllers\Api\UgcPoiController::class);
$this->app->bind(\Wm\WmPackage\Http\Controllers\Api\UgcTrackController::class, \App\Http\Controllers\Api\UgcTrackController::class);
```

- [ ] **Step 5: Esegui il test e verifica che passi**

Run: `docker exec php-osm2cai2 php artisan test --filter=UgcControllerBindingTest`
Expected: PASS

- [ ] **Step 6: Verifica il bind con le rotte in cache, come in produzione**

```bash
docker exec php-osm2cai2 php artisan route:cache
docker exec php-osm2cai2 php artisan route:list --path=ugc/poi/store
docker exec php-osm2cai2 php artisan test --filter=UgcControllerBindingTest
docker exec php-osm2cai2 php artisan route:clear
```
Expected: le rotte restano registrate con i nomi del package (`ugc.poi.store.legacy`) e il test passa anche a rotte compilate. **Se qui il bind non regge, il piano si ferma: l'approccio va ridiscusso prima di costruirci sopra.**

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/UgcPoiController.php app/Http/Controllers/Api/UgcTrackController.php app/Providers/AppServiceProvider.php tests/Feature/Ugc/UgcControllerBindingTest.php
git commit -m "feat(oc:8867): controller UGC locali sostituiti via bind nel container"
```

---

### Task 2: Prevenzione — riconoscimento del rilevamento già salvato (strato 1)

Porting di `Wm\WmPackage\Http\Controllers\Api\Abstracts\UgcController::store()` di oc:8718. Nel package la correzione sta nella classe astratta; qui l'astratta è nel package e non si tocca, quindi vive in un trait usato dai due controller locali — **divergenza da annotare in `notes.md`**.

Il trait sovrascrive anche `fillModelWithRequest`, perché la versione locale del package usa `addMultipleMediaFromRequest` e non ha lo scarto per hash.

**Files:**
- Create: `app/Http/Controllers/Api/Concerns/FindsExistingUgcByUuid.php` (strato 1)
- Modify: `app/Http/Controllers/Api/UgcPoiController.php`, `app/Http/Controllers/Api/UgcTrackController.php` (aggiungono `use FindsExistingUgcByUuid;`)
- Create: `app/Services/UgcMediaHashService.php` (strato 1) — serve già qui, per lo scarto delle immagini in upload
- Test: `tests/Feature/Ugc/UgcStoreDeduplicationTest.php`

**Interfaces:**
- Consumes: i controller di Task 1.
- Produces: `FindsExistingUgcByUuid::store(Request $request): JsonResponse`, `FindsExistingUgcByUuid::findExistingByUuid(array $validated): ?GeometryModel`, `App\Services\UgcMediaHashService` con `make(): static`, `hashOfFile(string $path): string`, `hashOf(Media $media): ?string`, `findByContent(HasMedia $model, string $hash, string $collection = 'default'): ?Media` e la costante `HASH_PROPERTY = 'sha256'`. Task 5 e 9 usano `UgcMediaHashService`.

- [ ] **Step 1: Scrivi i test che falliscono**

Cinque test, con utente autenticato via `actingAs($user, 'api')` (le rotte UGC del package sono sotto `auth:api`), che inviano a `POST /api/v2/ugc/poi/store`:

```php
public function test_due_invii_con_lo_stesso_uuid_aggiornano_lo_stesso_record()
// multipart con campo 'feature' = json_encode($feature), inviato due volte.
// Assert: stesso id nelle due risposte, entrambe 201, UgcPoi::count() === 1

public function test_il_reinvio_senza_foto_non_cancella_quelle_gia_salvate()
// primo invio con un'immagine, secondo senza (caso 10830, app 3.1.10).
// Assert: il record ha ancora 1 media  [Review Focus 3]

public function test_lo_stesso_uuid_di_un_altro_utente_crea_un_record_nuovo()
// Assert: UgcPoi::count() === 2  [Review Focus 2]

public function test_riconosce_il_rilevamento_anche_quando_i_campi_sono_al_primo_livello()
// invio senza campo 'feature', con properties/geometry come array.
// Assert: stesso id  [Review Focus 1]

public function test_due_invii_di_una_traccia_con_lo_stesso_uuid_aggiornano_lo_stesso_record()
// POST /api/v2/ugc/track/store. Assert: UgcTrack::count() === 1
```

- [ ] **Step 2: Esegui i test e verifica che falliscano**

Run: `docker exec php-osm2cai2 php artisan test --filter=UgcStoreDeduplicationTest`
Expected: FAIL — oggi ogni invio crea un record nuovo (count 2 invece di 1)

- [ ] **Step 3: Porta `UgcMediaHashService` in `app/Services/UgcMediaHashService.php`**

Copia da `wm-package` `origin/main:src/Services/Models/UgcMediaHashService.php`, riga per riga; cambia solo il namespace in `App\Services` e la classe base resta `Wm\WmPackage\Services\BaseService` (esiste già nella versione locale). Il canale di log `ugc` esiste già (`config/logging.php:64`).

- [ ] **Step 4: Scrivi il trait `FindsExistingUgcByUuid`**

Contiene `store()`, `findExistingByUuid()` e `fillModelWithRequest()` copiati da `origin/main:src/Http/Controllers/Api/Abstracts/UgcController.php`. Due punti da rispettare alla lettera:
- `store()` chiama `$this->getModelIstance()` **senza `$request`**: è così che il difetto del package (la ricerca per uuid dentro `getModelIstance($request)`) viene aggirato senza toccarlo.
- `findExistingByUuid()` filtra `where('user_id', auth()->id())` e ordina `orderBy('id')->first()`.

- [ ] **Step 5: Aggiungi `use FindsExistingUgcByUuid;` ai due controller locali**

- [ ] **Step 6: Esegui i test e verifica che passino**

Run: `docker exec php-osm2cai2 php artisan test --filter=UgcStoreDeduplicationTest`
Expected: PASS, 5 test

- [ ] **Step 7: Annota in `notes.md` le divergenze dall'originale**

Una riga per ciascuna: il trait al posto dell'astratta; `fillModelWithRequest` sovrascritto perché la versione locale non ha lo scarto per hash; `getModelIstance()` chiamato senza `$request`.

- [ ] **Step 8: Commit**

```bash
composer format
git add app/Http/Controllers/Api app/Services/UgcMediaHashService.php tests/Feature/Ugc docs/features/8867-acquasorgente-duplicazioni-rilevamenti/notes.md
git commit -m "feat(oc:8867): lo store riconosce il rilevamento gia salvato invece di duplicarlo"
```

---

### Task 3: Chiusura del webhook da geohub

Percorso non autenticato che istanzia il controller del package con la classe cablata (`WebhookController.php:25`), quindi aggira il bind di Task 1. Dal webhook non arriva nulla dal 01/04/2026 (POI) e dal 08/11/2025 (tracce).

**Files:**
- Delete: `app/Http/Controllers/WebhookController.php`
- Modify: `routes/api.php:151-155` (gruppo `webhook`) e il suo `use`
- Test: `tests/Feature/Ugc/WebhookRimossoTest.php`

- [ ] **Step 1: Scrivi il test che fallisce**

```php
public function test_le_rotte_del_webhook_non_esistono_piu(): void
{
    $this->postJson('/api/webhook/ugc/poi', [])->assertNotFound();
    $this->postJson('/api/webhook/ugc/track', [])->assertNotFound();
}
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `docker exec php-osm2cai2 php artisan test --filter=WebhookRimossoTest`
Expected: FAIL — risponde 422 o 500, non 404

- [ ] **Step 3: Elimina il controller, il gruppo di rotte e l'import**

- [ ] **Step 4: Esegui il test e verifica che passi, e che nessun riferimento resti**

```bash
docker exec php-osm2cai2 php artisan test --filter=WebhookRimossoTest
grep -rn "WebhookController" app routes config tests
```
Expected: test PASS, grep senza risultati

- [ ] **Step 5: Commit**

```bash
git add -A app/Http/Controllers routes/api.php tests/Feature/Ugc/WebhookRimossoTest.php
git commit -m "feat(oc:8867): chiude il webhook da geohub, ponte della migrazione non piu usato"
```

---

### Task 4: Tabella `ugc_duplicates_archive`

**Files:**
- Create: `database/migrations/2026_10_27_000001_create_ugc_duplicates_archive_table.php` (strato 1)
- Test: `tests/Feature/Ugc/UgcDuplicatesArchiveSchemaTest.php`

**Interfaces:**
- Produces: tabella `ugc_duplicates_archive` con `model_type`, `original_id`, `reference_id` (senza FK), `uuid`, `user_id`, `app_id`, `properties` jsonb, `media_ids` jsonb, `geometry` geography, `original_created_at`, `original_updated_at`, `archived_at`, `timestamps`, indici su `['model_type','reference_id']` e `uuid`. Task 5 ci scrive dentro.

- [ ] **Step 1: Scrivi il test che fallisce**

```php
public function test_la_tabella_archivio_esiste_con_le_sue_colonne(): void
{
    $this->assertTrue(Schema::hasTable('ugc_duplicates_archive'));
    $this->assertTrue(Schema::hasColumns('ugc_duplicates_archive', [
        'model_type', 'original_id', 'reference_id', 'uuid', 'user_id', 'app_id',
        'properties', 'media_ids', 'geometry', 'original_created_at', 'original_updated_at', 'archived_at',
    ]));
}
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `docker exec php-osm2cai2 php artisan test --filter=UgcDuplicatesArchiveSchemaTest`
Expected: FAIL

- [ ] **Step 3: Crea la migration**

Copia fedele di `origin/main:database/migrations/zz_2026_10_07_000001_create_ugc_duplicates_archive_table.php.stub`, incluse la guardia `Schema::hasTable` e la colonna `geometry geography` aggiunta con `DB::statement` (senza vincolo di tipo: deve contenere sia punti sia linee).

- [ ] **Step 4: Esegui migrazione e test**

```bash
docker exec php-osm2cai2 php artisan migrate
docker exec php-osm2cai2 php artisan test --filter=UgcDuplicatesArchiveSchemaTest
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations tests/Feature/Ugc/UgcDuplicatesArchiveSchemaTest.php
git commit -m "feat(oc:8867): tabella ugc_duplicates_archive (struttura di oc:8718)"
```

---

### Task 5: `UgcDuplicatesService` — fusione, spostamento media, archiviazione (strato 1)

**Files:**
- Create: `app/Services/UgcDuplicatesService.php` (strato 1)
- Test: `tests/Feature/Ugc/UgcDuplicatesServiceTest.php`

**Interfaces:**
- Consumes: `App\Services\UgcMediaHashService` (Task 2), tabella di Task 4.
- Produces: `groups(string $modelClass, ?int $appId = null): Collection` (ogni elemento: `uuid`, `parent_id`, `copy_ids`, `max_distance_m`), `maxDistanceMeters(string $table, int $parentId, array $copyIds): float`, `mergeGroup(string $modelClass, array $group): array` (restituisce i `Media` scartati, da cancellare **dopo** il commit), `mergedProperties(array $rowsInOrder, array $parentProperties): array`, costanti `DEFAULT_MAX_DISTANCE_METERS = 1.0` e `SERVER_PROPERTY_KEYS`. Task 6, 7 e 8 lo usano.
- **Divergenza necessaria:** `moveMedia(int $parentId, array $copyIds, string $morph): array` è `private` nell'originale; qui diventa **`public`** perché la sanatoria (Task 8) è un command e deve spostare media senza riscrivere la logica. Da annotare in `notes.md`.

- [ ] **Step 1: Scrivi i test che falliscono**

```php
public function test_raggruppa_per_uuid_e_tiene_il_piu_vecchio_come_padre()
// 3 UgcPoi stesso uuid, created_at crescenti. Assert: parent_id === id del più vecchio, copy_ids === gli altri due

public function test_la_fusione_sposta_i_media_sul_padre_e_cancella_le_copie()
// padre senza foto, copia con 1 foto. Assert: il padre ha 1 media, UgcPoi::count() === 1

public function test_una_foto_identica_per_hash_viene_scartata_e_restituita_al_chiamante()
// stesso contenuto su padre e copia. Assert: mergeGroup restituisce 1 Media, il padre ne ha ancora 1

public function test_un_media_illeggibile_viene_spostato_e_non_scartato()
// media il cui file non esiste sul disco. Assert: finisce sul padre, non è fra gli scartati  [Review Focus 4]

public function test_la_copia_finisce_in_archivio_con_utente_app_geometria_e_media_spostati()
// Assert: una riga in ugc_duplicates_archive con model_type 'App\Models\UgcPoi', reference_id = padre, media_ids non vuoto

public function test_le_properties_si_uniscono_in_ordine_cronologico_tenendo_le_chiavi_del_server()
// mergedProperties: chiavi di SERVER_PROPERTY_KEYS prese dal padre

public function test_funziona_anche_per_le_tracce()
// stesso scenario con UgcTrack e geometria LINESTRING
```

- [ ] **Step 2: Esegui i test e verifica che falliscano**

Run: `docker exec php-osm2cai2 php artisan test --filter=UgcDuplicatesServiceTest`
Expected: FAIL, classe inesistente

- [ ] **Step 3: Porta il servizio in `app/Services/UgcDuplicatesService.php`**

Copia da `origin/main:src/Services/Models/UgcDuplicatesService.php`. Cambia il namespace in `App\Services`, l'import di `UgcMediaHashService` verso `App\Services`, e `moveMedia` da `private` a `public`. Il resto — SQL compreso — resta identico: `Wm\WmPackage\Models\Media` e `GeometryModel` esistono già nella versione locale del package.

- [ ] **Step 4: Esegui i test e verifica che passino**

Run: `docker exec php-osm2cai2 php artisan test --filter=UgcDuplicatesServiceTest`
Expected: PASS, 7 test

- [ ] **Step 5: Annota in `notes.md` la divergenza su `moveMedia`**

- [ ] **Step 6: Commit**

```bash
composer format
git add app/Services/UgcDuplicatesService.php tests/Feature/Ugc docs/features/8867-acquasorgente-duplicazioni-rilevamenti/notes.md
git commit -m "feat(oc:8867): servizio di fusione dei duplicati UGC (porting di oc:8718)"
```

---

### Task 6: Command `osm2cai:fix-duplicated-ugc` e rimozione del precedente

Il vecchio command viene **eliminato**, non deprecato, e il vecchio nome non resta come alias: in `docs/superpowers/plans/2026-05-05-migrazione-server-cai.md:43` c'è una riga pronta da copiare con `--execute=1`, e deve dare errore invece di eseguire.

**Files:**
- Create: `app/Console/Commands/FixDuplicatedUgcCommand.php` (strato 1)
- Delete: `app/Console/Commands/FixDuplicatedUgcPois.php`
- Modify: `app/Console/Kernel.php:54-56`, `config/logging.php` (canale `duplicated-ugc`), `docs/superpowers/plans/2026-05-05-migrazione-server-cai.md:43`
- Test: `tests/Feature/Ugc/FixDuplicatedUgcCommandTest.php`

**Interfaces:**
- Consumes: `App\Services\UgcDuplicatesService` (Task 5).
- Produces: comando `osm2cai:fix-duplicated-ugc` con `--type=`, `--execute`, `--app-id=`, `--max-distance=0`. Task 7 ne estende il raggruppamento.
- **Divergenze dall'originale, da annotare:** `--max-distance` ha default **0** (si fonde solo a geometrie identiche: verificato su 192 gruppi su 192); si aggiungono il report CSV sotto `storage/backups` e la sezione `gruppi multi-utente`, che nel package non esistono.

- [ ] **Step 1: Scrivi i test che falliscono**

```php
public function test_senza_execute_non_scrive_nulla()
// Assert: UgcPoi::count() invariato, archivio vuoto, output contiene 'report'

public function test_con_execute_unisce_i_gruppi_e_archivia_le_copie()
public function test_tratta_anche_le_tracce_e_type_le_limita()
public function test_un_gruppo_con_geometrie_diverse_non_viene_toccato_e_risulta_da_verificare()
public function test_un_gruppo_multi_utente_compare_nella_sezione_dedicata_del_report()
public function test_il_vecchio_nome_non_esiste_piu()
// Assert: Artisan::call('osm2cai:fix-duplicated-ugc-pois') solleva CommandNotFoundException
public function test_scrive_il_report_csv_sotto_storage_backups()
```

- [ ] **Step 2: Esegui i test e verifica che falliscano**

Run: `docker exec php-osm2cai2 php artisan test --filter=FixDuplicatedUgcCommandTest`
Expected: FAIL

- [ ] **Step 3: Aggiungi il canale di log `duplicated-ugc` in `config/logging.php`**

Stessa forma del canale `ugc` esistente, con `storage_path('logs/duplicated-ugc.log')`.

- [ ] **Step 4: Crea il command**

Copia di `origin/main:src/Commands/WmFixDuplicatedUgcCommand.php` con: classe `FixDuplicatedUgcCommand`, signature `osm2cai:fix-duplicated-ugc`, `--max-distance=0` come default, i modelli presi da `config('wm-package.ugc_poi_model')`/`ugc_track_model` se configurati, altrimenti quelli del package. Si aggiungono, in coda al `handle`, la scrittura del CSV in `storage/backups/fix-duplicated-ugc-<timestamp>.csv` con le stesse colonne della tabella a video più `utenti`, e una seconda tabella a video per i gruppi i cui record appartengono a più utenti.

- [ ] **Step 5: Elimina `FixDuplicatedUgcPois.php` e aggiorna i due riferimenti**

`Kernel.php` mantiene `dailyAt('06:00')` e la sola segnalazione, cambiando solo nome e descrizione; nel documento di migrazione la riga diventa il nuovo nome **senza** `--execute`.

- [ ] **Step 6: Esegui i test e verifica che passino**

```bash
docker exec php-osm2cai2 php artisan test --filter=FixDuplicatedUgcCommandTest
grep -rn "fix-duplicated-ugc-pois" app docs routes
```
Expected: PASS; grep senza risultati

- [ ] **Step 7: Commit**

```bash
composer format
git add -A app/Console config/logging.php docs tests/Feature/Ugc
git commit -m "feat(oc:8867): command osm2cai:fix-duplicated-ugc al posto del precedente"
```

---

### Task 7: Raggruppamento per istante di compilazione (strato 2)

Serve per i 162 duplicati senza uuid (tutti POI; l'ultimo creato dall'app il 28/06/2026). Resta **spento per default**: è un'inferenza, non una chiave dichiarata. Il rischio che governa è la fusione di monitoraggi legittimi — 1.121 coppie stesso utente/stessa posizione in produzione.

**Files:**
- Create: `app/Services/UgcDuplicatesByTimestampService.php` (strato 2)
- Create: `app/Console/Commands/Concerns/GroupsByTimestamp.php` (strato 2)
- Modify: `app/Console/Commands/FixDuplicatedUgcCommand.php` (usa il concern, aggiunge `--by-timestamp`)
- Test: `tests/Feature/Ugc/UgcDuplicatesByTimestampTest.php`

**Interfaces:**
- Consumes: `UgcDuplicatesService::mergeGroup()` e `maxDistanceMeters()` (Task 5) — richiamati, mai riscritti.
- Produces: `groups(string $modelClass, ?int $appId = null): Collection`, con elementi della **stessa forma** di `UgcDuplicatesService::groups()` (`uuid` a `null`, `parent_id`, `copy_ids`, `max_distance_m`), così il command li tratta allo stesso modo.

- [ ] **Step 1: Scrivi i test che falliscono**

```php
public function test_raggruppa_i_record_senza_uuid_per_utente_istante_di_compilazione_e_posizione()
public function test_non_unisce_due_monitoraggi_della_stessa_sorgente_con_istanti_diversi()
// stesso utente, stessa posizione, properties.createdAt diversi: due gruppi distinti, nessuna fusione
public function test_i_record_senza_uuid_e_senza_istante_non_vengono_mai_uniti()
public function test_i_record_con_uuid_non_entrano_in_questo_raggruppamento()
public function test_il_command_senza_by_timestamp_ignora_i_record_senza_uuid()
```

- [ ] **Step 2: Esegui i test e verifica che falliscano**

Run: `docker exec php-osm2cai2 php artisan test --filter=UgcDuplicatesByTimestampTest`
Expected: FAIL

- [ ] **Step 3: Scrivi il servizio**

Raggruppa i record con `properties->>'uuid'` nullo o vuoto per `user_id`, `properties->>'createdAt'` e geometria, scartando i gruppi in cui `createdAt` manca. Padre = il più vecchio per `created_at, id`, come nello strato 1.

- [ ] **Step 4: Scrivi il concern e agganciarlo al command**

`GroupsByTimestamp` espone l'opzione `--by-timestamp` e restituisce i gruppi aggiuntivi da trattare; il command li unisce a quelli dello strato 1 e li passa allo stesso ciclo. Nessuna logica di fusione nel concern.

- [ ] **Step 5: Esegui i test e verifica che passino**

Run: `docker exec php-osm2cai2 php artisan test --filter=UgcDuplicatesByTimestampTest`
Expected: PASS, 5 test

- [ ] **Step 6: Commit**

```bash
composer format
git add app/Services/UgcDuplicatesByTimestampService.php app/Console/Commands tests/Feature/Ugc
git commit -m "feat(oc:8867): raggruppamento dei duplicati senza uuid per istante di compilazione"
```

---

### Task 8: Sanatoria dell'esecuzione precedente (strato 2)

660 copie marcate `properties.duplicated`, 462 schede con chiavi ereditate, 102 fotografie rimaste sulle copie, l'utente di servizio `7736` (`duplicated-ugc-pois@webmapp.it`).

**Files:**
- Create: `app/Console/Commands/RecoverPreviousDuplicateFixCommand.php` (strato 2)
- Test: `tests/Feature/Ugc/RecoverPreviousDuplicateFixTest.php`

**Interfaces:**
- Consumes: `UgcDuplicatesService::moveMedia()` e `UgcMediaHashService` (Task 2 e 5).
- Produces: comando `osm2cai:recover-previous-duplicate-fix` con `--execute`.

- [ ] **Step 1: Scrivi i test che falliscono**

```php
public function test_sposta_sul_padre_le_foto_rimaste_sulle_copie_marcate()
public function test_archivia_e_cancella_le_copie_marcate()
public function test_ripristina_l_utente_originale_prima_di_archiviare()
// l'archivio registra duplication_info.parent_ugc_poi_user_id, non l'utente di servizio
public function test_ripulisce_le_chiavi_ereditate_dalle_schede_che_non_sono_copie()
// duplication_info e has_been_duplicated rimossi, scheda non cancellata
public function test_una_copia_il_cui_padre_non_esiste_piu_non_viene_cancellata()
// finisce nell'elenco da revisionare  [Review Focus 5]
public function test_senza_execute_non_scrive_nulla()
public function test_a_fine_lavoro_l_utente_di_servizio_viene_eliminato()
// con --execute e nessun record residuo assegnato
```

- [ ] **Step 2: Esegui i test e verifica che falliscano**

Run: `docker exec php-osm2cai2 php artisan test --filter=RecoverPreviousDuplicateFixTest`
Expected: FAIL

- [ ] **Step 3: Scrivi il command**

Per ogni record con `properties->duplicated` vero: ritrova il padre da `duplication_info.parent_ugc_poi_id` e **verifica che esista**; se manca, lo mette nell'elenco da revisionare e passa oltre. Altrimenti sposta i media con lo stesso scarto per hash, ripristina `user_id` da `duplication_info.parent_ugc_poi_user_id`, archivia e cancella. In un secondo passaggio rimuove `duplication_info` e `has_been_duplicated` dalle schede che non sono copie. In chiusura, se l'utente `duplicated-ugc-pois@webmapp.it` non ha più record, lo elimina.

- [ ] **Step 4: Esegui i test e verifica che passino**

Run: `docker exec php-osm2cai2 php artisan test --filter=RecoverPreviousDuplicateFixTest`
Expected: PASS, 7 test

- [ ] **Step 5: Commit**

```bash
composer format
git add app/Console/Commands/RecoverPreviousDuplicateFixCommand.php tests/Feature/Ugc
git commit -m "feat(oc:8867): sanatoria dello stato lasciato dalla bonifica precedente"
```

---

### Task 9: Popolamento degli hash dei media

Oggi **nessuno dei 21.625 media** ha `custom_properties.sha256`. Senza, il dry-run dovrebbe scaricare i file per calcolarlo e scriverebbe sul database: il popolamento va fatto prima, da un command a sé, così il dry-run resta a sola lettura.

**Files:**
- Create: `app/Console/Commands/BackfillUgcMediaHashCommand.php` (strato 2 — nel package non esiste)
- Test: `tests/Feature/Ugc/BackfillUgcMediaHashTest.php`

**Interfaces:**
- Consumes: `App\Services\UgcMediaHashService::hashOf()` (Task 2).
- Produces: comando `osm2cai:backfill-ugc-media-hash` con `--execute` e `--chunk=500`.

- [ ] **Step 1: Scrivi i test che falliscono**

```php
public function test_calcola_e_salva_lo_sha256_dei_media_ugc_che_non_ce_l_hanno()
public function test_non_ricalcola_quelli_che_ce_l_hanno_gia()
public function test_un_media_illeggibile_viene_contato_fra_i_falliti_e_non_blocca_gli_altri()
public function test_senza_execute_conta_soltanto()
```

- [ ] **Step 2: Esegui i test e verifica che falliscano**

Run: `docker exec php-osm2cai2 php artisan test --filter=BackfillUgcMediaHashTest`
Expected: FAIL

- [ ] **Step 3: Scrivi il command**

Itera con `chunkById` sui media con `model_type` fra `App\Models\UgcPoi` e `App\Models\UgcTrack` privi della chiave, chiama `hashOf()` e conta riusciti e falliti. `chunkById` e non `chunk`: modifica i record mentre itera (vedi CLAUDE.md, oc:7954).

- [ ] **Step 4: Esegui i test e verifica che passino**

Run: `docker exec php-osm2cai2 php artisan test --filter=BackfillUgcMediaHashTest`
Expected: PASS, 4 test

- [ ] **Step 5: Commit**

```bash
composer format
git add app/Console/Commands/BackfillUgcMediaHashCommand.php tests/Feature/Ugc
git commit -m "feat(oc:8867): command di popolamento dello sha256 dei media UGC"
```

---

### Task 10: Collaudo sul dump di produzione e documentazione

**Files:**
- Modify: `docs/features/8867-acquasorgente-duplicazioni-rilevamenti/notes.md` (esiti del collaudo)
- Modify: `docs/knowledge/custom-in-attesa-dell-aggiornamento-di-wm-package.md` (elenco definitivo dei file dello strato 1)
- Modify: `CLAUDE.md` (riga nella tabella «Feature disponibili»)

- [ ] **Step 1: Esegui l'intera suite**

Run: `docker exec php-osm2cai2 php artisan test`
Expected: PASS, nessuna regressione sui test preesistenti

- [ ] **Step 2: Collauda la bonifica sul dump di produzione, in sola segnalazione**

```bash
docker exec php-osm2cai2 php artisan osm2cai:fix-duplicated-ugc
docker exec php-osm2cai2 php artisan osm2cai:fix-duplicated-ugc --by-timestamp
docker exec php-osm2cai2 php artisan osm2cai:recover-previous-duplicate-fix
```
Expected: 192 gruppi e 252 record in eccesso per uuid, i gruppi senza uuid in più con `--by-timestamp`, 660 copie marcate per la sanatoria. I numeri si confrontano con quelli di `notes.md`; **ogni scostamento va capito prima di andare avanti**, non riallineato nel documento.

- [ ] **Step 3: Esegui con `--execute` e verifica che i conteggi corrispondano al report**

```bash
docker exec php-osm2cai2 php artisan osm2cai:fix-duplicated-ugc --execute
docker exec php-osm2cai2 php artisan tinker --execute="echo \Wm\WmPackage\Models\UgcPoi::count().' / '.\DB::table('ugc_duplicates_archive')->count();"
```
Expected: i record cancellati sono quelli annunciati dal report, e altrettante righe in archivio. **Nessuna fotografia persa**: il conteggio dei media UGC prima e dopo deve essere uguale, meno i doppioni per hash dichiarati dal report.

- [ ] **Step 4: Verifica la prevenzione con le rotte in cache**

```bash
docker exec php-osm2cai2 php artisan route:cache && docker exec php-osm2cai2 php artisan config:cache
docker exec php-osm2cai2 php artisan test --filter=UgcStoreDeduplicationTest
docker exec php-osm2cai2 php artisan route:clear && docker exec php-osm2cai2 php artisan config:clear
```
Expected: PASS a rotte compilate. Il `config:clear` finale è necessario: il `.env` locale è quello modificato per scaricare il dump (originale in `.env.bak-oc8867`).

- [ ] **Step 5: Scrivi gli esiti e aggiorna i documenti**

In `notes.md`: numeri osservati nel collaudo e elenco completo delle divergenze dallo strato 1. Nella pagina di conoscenza: i file dello strato 1 da cancellare all'aggiornamento e i namespace che lo strato 2 dovrà cambiare. In `CLAUDE.md`: una riga nella tabella delle feature.

- [ ] **Step 6: Commit**

```bash
git add docs CLAUDE.md
git commit -m "docs(oc:8867): esiti del collaudo, divergenze dallo strato 1 e registro del custom"
```

---

## Dopo il merge — ordine di esecuzione in produzione

Non fa parte del piano di sviluppo, ma è la ragione per cui Task 9 esiste.

**L'ordine è vincolante: la sanatoria (`recover-previous-duplicate-fix`) va prima della bonifica
(`fix-duplicated-ugc`).** Le copie lasciate dalla vecchia bonifica portano le chiavi `duplicated`,
`duplication_info` e `has_been_duplicated`; se la bonifica le fonde per prime, quelle chiavi passano
sulla scheda tenuta (`UgcDuplicatesService::SERVER_PROPERTY_KEYS` protegge solo `id`, `created_at`,
`updated_at`, `taxonomy_where`, `taxonomyWheres`), e la sanatoria poi non la ripulisce più, perché
esclude per costruzione ogni riga con la chiave `duplicated`: un record legittimo resta marcato per
sempre. Per questo `fix-duplicated-ugc` si ferma con un errore (anche in dry-run) finché in `ugc_pois`
esistono righe con `properties->duplicated`.

1. `php artisan migrate`
2. `php artisan osm2cai:backfill-ugc-media-hash --execute` (popola gli sha256 leggendo i file da Aruba)
3. `php artisan osm2cai:recover-previous-duplicate-fix` → si leggono i numeri (dry-run a sola lettura), poi `--execute`
4. `php artisan osm2cai:fix-duplicated-ugc` → si leggono i numeri, comprese le fotografie che sposterebbe e scarterebbe (dry-run a sola lettura), poi `--execute`
5. Solo se serve: `php artisan osm2cai:fix-duplicated-ugc --by-timestamp` → dry-run, poi `--execute`
