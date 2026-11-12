> Ticket: oc:8945

# Piano — [OSM2CAI] errore nella modifica di Segnalazioni Percorribilità (action) di un percorso

Tutti i file sono nel repo principale `osm2cai2`. Nessun submodule coinvolto.

Tutti i test girano nel container (`docker exec -it php81-osm2cai2 php artisan test --filter=...`), sul DB PostGIS di sviluppo, con `DatabaseTransactions`.

I commit sono istruzioni per il dev: Claude non esegue `git commit` né `git push` senza conferma esplicita.

---

## Task 1 — `User::canManageHikingRoute()` sicuro per il RegionalReferent senza regione

**File:** `app/Models/User.php`, `tests/Unit/Models/UserTest.php`

1. Riscrivi il test `regional_referent_without_region_cannot_manage_routes` (`UserTest.php:623-642`): togli `expectException` e il commento sull'errore, e verifica `$this->assertFalse($user->canManageHikingRoute($hikingRoute))`.
2. Esegui il test: deve fallire con `ErrorException` («Attempt to read property "id" on null").
3. In `canManageHikingRoute()`, ramo RegionalReferent (`User.php:183-185`): se `$this->region` è `null` restituisci `false`, altrimenti il controllo attuale.

   ```php
   if ($this->hasRole(UserRole::RegionalReferent)) {
       if ($this->region === null) {
           return false;
       }

       return $hr->regions->pluck('id')->contains($this->region->id);
   }
   ```

4. Esegui `php artisan test --filter=UserTest`: tutti i test devono passare.

Commit: `fix(oc:8945): canManageHikingRoute returns false for regional referent without region`

---

## Task 2 — Test Feature sull'esecuzione di `CreateIssue` (prima della correzione)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2--test-feature-sullesecuzione-di-createissue)

**File nuovo:** `tests/Feature/CreateIssueActionTest.php`

1. Classe con `DatabaseTransactions`. In `setUp()`: `Queue::fake()` e `Bus::fake()`, così il `save()` di `CreateIssue::handle()` non fa partire `UpdateEcTrackAwsJob` verso WMFE (`QUEUE_CONNECTION=sync` in `phpunit.xml`).
2. Helper per creare i dati, sullo stesso schema di `UserTest`:
   - percorsi con `HikingRoute::createQuietly([...geometry...])`;
   - collegamenti con insert diretti in `hiking_route_region` e nelle pivot di settori, aree, province del percorso;
   - utenti con `UserFactory::new()->createQuietly()` + `assignRole(...)`, e per il LocalReferent le pivot `sector_user` / `area_user` / `province_user`.
3. Helper `runAction(User $user, array $routeIds)`: `actingAs($user)` e `POST /nova-api/hiking-routes/action?action=<uriKey>` con `resources` = id separati da virgola e i campi `issues_status` (un valore di `IssuesStatusEnum`) e `issues_description`. L'`uriKey` si ricava con `(new CreateIssue)->uriKey()`, mai scritto a mano. Prima di scrivere l'helper verifica nel container il nome reale della route con `php artisan route:list --path=nova-api` e il formato del parametro `resources` in `vendor/laravel/nova/src/Http/Requests/ActionRequest.php`.
4. Casi, ciascuno con verifica della risposta e dello stato del percorso nel DB (`issues_description` aggiornata o invariata):
   - Administrator, un percorso qualunque → aggiornato;
   - NationalReferent, un percorso qualunque → aggiornato;
   - RegionalReferent, percorso della sua regione → aggiornato;
   - RegionalReferent, percorso di un'altra regione → risposta `danger`, non modificato;
   - LocalReferent, percorso che interseca il suo settore → aggiornato;
   - LocalReferent, percorso senza intersezione → risposta `danger`, non modificato;
   - RegionalReferent senza regione, **un solo percorso** selezionato (così Nova usa `detailQuery()` e non `indexQuery()`, lasciato fuori) → risposta `danger`, non modificato;
   - LocalReferent, selezione mista (uno dentro e uno fuori) → risposta `message` con 1 aggiornato e 1 saltato; solo il percorso nel territorio modificato;
   - utente senza ruolo territoriale (es. Guest) → la action non è disponibile, nessuna modifica.
5. Esegui il test: i casi dei non Administrator devono fallire (oggi rispondono 403). Serve a confermare che il test riproduce il bug del ticket.

Commit: insieme al Task 3.

---

## Task 3 — `canRun` e filtro per territorio in `handle()`

**File:** `app/Nova/HikingRoute.php`, `app/Nova/Actions/CreateIssue.php`

1. In `HikingRoute::actions()` (`app/Nova/HikingRoute.php:398-405`), sulla registrazione di `CreateIssue` aggiungi:

   ```php
   ->canRun(function ($request, $hikingRoute) {
       return $request->user()->getTerritorialRole() != 'unknown';
   })
   ```

   L'utente si prende da `$request->user()`: il secondo argomento della closure è il percorso (`vendor/laravel/nova/src/Actions/Action.php:482`). Non ridefinire `authorizedToRun()` nella classe: Nova controlla la proprietà `runCallback` (`vendor/laravel/nova/src/Actions/ActionModelCollection.php:29`).

2. In `CreateIssue::handle()`:
   - prima del ciclo, dividi `$models` con `partition(fn ($hr) => $user->canManageHikingRoute($hr))` in percorsi autorizzati e saltati;
   - se gli autorizzati sono zero → `return Action::danger(__('You are not authorized to report issues on the selected hiking routes'))`, senza modificare nulla;
   - esegui il ciclo attuale solo sugli autorizzati, senza cambiarne la logica;
   - se i saltati sono più di zero → `return Action::message(__('Issues updated on :updated hiking routes, :skipped skipped because outside your territory', ['updated' => ..., 'skipped' => ...]))`;
   - altrimenti nessun valore di ritorno, come oggi.

3. Esegui `php artisan test --filter=CreateIssueActionTest`: tutti i casi devono passare.

Commit: `fix(oc:8945): allow territorial users to run CreateIssue on their hiking routes`

---

## Task 4 — `canSee` per percorso (detail view e righe della index view)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4--cansee-per-percorso)

**File:** `app/Nova/HikingRoute.php`, `tests/Feature/CreateIssueActionTest.php`

1. Sostituisci il `canSee` di `CreateIssue` (`app/Nova/HikingRoute.php:402-404`):

   ```php
   ->canSee(function ($request) {
       $user = $request->user();

       if ($this->resource instanceof \App\Models\HikingRoute && $this->resource->exists) {
           return $user->canManageHikingRoute($this->resource);
       }

       return $user->getTerritorialRole() != 'unknown';
   })
   ```

   Il ramo per percorso vale nella detail view e sulle righe della index view, dove Nova valuta il `canSee` con il percorso della riga (`vendor/laravel/nova/src/ResolvesActions.php:101-108`). Il ramo per ruolo vale per il menu della selezione multipla e per l'esecuzione della action, dove la Resource ha un modello vuoto: senza la condizione `exists` l'esecuzione sarebbe rifiutata con un 403.

2. Aggiungi al test:
   - detail view: `GET /nova-api/hiking-routes/<id>/actions` per un LocalReferent → `CreateIssue` presente sul percorso nel territorio, assente su quello fuori;
   - index view: `GET /nova-api/hiking-routes` per lo stesso LocalReferent → nel campo `actions` della riga del percorso nel territorio c'è `CreateIssue`, in quella del percorso fuori no;
   - menu della selezione multipla: `GET /nova-api/hiking-routes/actions` → `CreateIssue` presente per un LocalReferent, assente per un Guest.

   Prima di scrivere le asserzioni verifica nel container le route con `php artisan route:list --path=nova-api` e la struttura della risposta (dove si trova l'`uriKey` delle action).

3. Riesegui tutto `CreateIssueActionTest` e `UserTest`.

Commit: `fix(oc:8945): show CreateIssue only on hiking routes the user can manage`

---

## Task 5 — Traduzioni

**File:** `lang/vendor/nova/it.json`

1. Aggiungi le chiavi dei due messaggi nuovi del Task 3, vicino a `"Accessibility Issue"` (riga 48):
   - `"You are not authorized to report issues on the selected hiking routes"` → `"Non sei autorizzato a inserire segnalazioni sui percorsi selezionati"`
   - `"Issues updated on :updated hiking routes, :skipped skipped because outside your territory"` → `"Segnalazioni aggiornate su :updated percorsi, :skipped saltati perché fuori dal tuo territorio"`
2. Verifica che il JSON sia valido (`php -r 'json_decode(file_get_contents("lang/vendor/nova/it.json"), flags: JSON_THROW_ON_ERROR);'`).
3. `lang/vendor/nova/en.json`: le chiavi sono già in inglese, nessuna aggiunta necessaria; verifica che il file non tenga anche le altre chiavi della action, nel qual caso aggiungi anche queste per coerenza.

Commit: `fix(oc:8945): translate CreateIssue messages`

---

## Task 6 — Verifica finale

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6--verifica-finale)

1. `php artisan test --filter=CreateIssueActionTest` e `php artisan test --filter=UserTest`.
2. `php artisan test --testsuite=Unit` per escludere regressioni sulle altre action che usano `canManageHikingRoute()`.
3. `./vendor/bin/pint --ansi` sui file toccati.
4. Prova manuale in Nova (locale), con un LocalReferent impersonato:
   - nell'index dei percorsi il pulsante compare solo sulle righe del suo territorio;
   - l'esecuzione dalla detail view aggiorna la segnalazione;
   - la selezione mista dal menu della index view mostra il messaggio con aggiornati e saltati.
