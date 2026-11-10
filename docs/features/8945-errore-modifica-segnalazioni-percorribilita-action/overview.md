> Ticket: oc:8945

# [OSM2CAI] errore nella modifica di Segnalazioni Percorribilità (action) di un percorso

## Cosa cambia

La Nova action `App\Nova\Actions\CreateIssue` (nome visibile «Segnalazioni Percorribilità»), registrata in `App\Nova\HikingRoute::actions()`, diventa eseguibile da tutti gli utenti che hanno i permessi territoriali sul percorso, non più solo dall'Administrator.

I permessi territoriali sono quelli già definiti da `User::canManageHikingRoute()`:

- Administrator e NationalReferent: tutti i percorsi;
- RegionalReferent: i percorsi della propria regione;
- LocalReferent: i percorsi che intersecano i propri settori, aree o province;
- ogni altro ruolo: nessun percorso.

In concreto:

- `CreateIssue` riceve un `canRun` che lascia passare chi ha un ruolo territoriale (`getTerritorialRole() != 'unknown'`): così Nova non usa più la policy `update` e non scarta in silenzio nessun percorso;
- `CreateIssue::handle()` aggiorna solo i percorsi per cui `canManageHikingRoute()` è vero e conta quelli saltati. Se ne salta alcuni risponde con `Action::message`, dicendo quanti percorsi sono stati aggiornati e quanti saltati perché fuori territorio; se li salta tutti risponde con `Action::danger` e non modifica nulla. Con più di un percorso selezionato Nova applica prima `HikingRoute::indexQuery()` (`vendor/laravel/nova/src/Http/Requests/ActionRequest.php:127-131`), che per il RegionalReferent filtra già per regione: il conteggio dei saltati riguarda quindi in pratica il LocalReferent, mentre il RegionalReferent dalla index view vede e seleziona solo percorsi della sua regione;
- il `canSee` di `CreateIssue`, quando la Resource ha un percorso esistente (detail view), mostra la action solo se `canManageHikingRoute()` è vero per quel percorso; altrimenti (index view, esecuzione della action) resta il controllo per ruolo attuale;
- `canManageHikingRoute()` restituisce `false` invece di lanciare un errore per un RegionalReferent senza regione assegnata.

## Perché

Il cliente segnala che gli utenti non admin, lanciando la action, ricevono «Sorry! You are not authorized to perform this action». Allo scrum del 10/11 Giuseppe Bonfanti ha indicato che la action «deve essere lanciata anche dagli utenti che hanno i permessi territoriali attivi su quel percorso».

La causa è nel percorso di autorizzazione di Nova. `CreateIssue` non ha un `canRun`, quindi Nova cerca un metodo `runAction` in `HikingRoutePolicy`; non trovandolo usa la policy `update` (`vendor/laravel/nova/src/Authorizable.php:347-349`). `HikingRoutePolicy::update()` restituisce sempre `false`, e solo `before()` lascia passare l'Administrator: ogni altro utente vede la action (il `canSee` lo permette) ma viene rifiutato.

Il controllo va sulla sola `CreateIssue` e non in un `runAction` della policy, perché quel metodo varrebbe anche per le altre action di `HikingRoute` senza `canRun`, cambiandone i permessi senza che nessuno l'abbia chiesto.

Il filtro per territorio sta in `handle()` e non nel `canRun` perché, con il `canRun`, Nova esegue la action solo sui percorsi autorizzati e risponde con un successo se almeno uno è passato (`vendor/laravel/nova/src/Actions/DispatchAction.php:124-135`). Verificato con un test temporaneo: un LocalReferent che lancia la action su due percorsi, uno nel suo territorio e uno fuori, riceve un successo, ma il secondo percorso resta senza segnalazione e nessuno glielo dice. Il controllo dentro `handle()` è lo stesso schema già usato da altre action della Resource (`app/Nova/Actions/ManageHikingRouteValidationAction.php:47`).

Il `canSee` viene allineato ai permessi nella detail view perché mostrare un comando che poi viene rifiutato riproduce per l'utente lo stesso sintomo del ticket.

`canManageHikingRoute()` va resa sicura perché il `canSee` la chiamerà a ogni apertura della detail view: oggi per un RegionalReferent senza regione (nel DB locale ce n'è 1 su 65) lancia «Attempt to read property "id" on null», e quella pagina andrebbe in errore.

## Requisiti

- [ ] Un Administrator o un NationalReferent esegue `CreateIssue` su qualunque percorso.
- [ ] Un RegionalReferent esegue `CreateIssue` sui percorsi della propria regione; su un percorso di un'altra regione riceve la risposta `danger` di `handle()` e il percorso non viene modificato.
- [ ] Un LocalReferent esegue `CreateIssue` sui percorsi che intersecano i suoi settori, aree o province; su un percorso senza intersezione riceve la risposta `danger` di `handle()` e il percorso non viene modificato.
- [ ] Un utente senza ruolo territoriale non vede `CreateIssue` (comportamento attuale invariato).
- [ ] Nella detail view di un percorso, `CreateIssue` compare solo se `canManageHikingRoute()` è vero per quel percorso.
- [ ] Nella index view, `CreateIssue` compare a chi ha un ruolo territoriale.
- [ ] Su una selezione mista di un LocalReferent, `CreateIssue` aggiorna solo i percorsi nel suo territorio e risponde con `Action::message` indicando quanti ne ha aggiornati e quanti ne ha saltati.
- [ ] Se nessun percorso selezionato è nel territorio dell'utente, `CreateIssue` non aggiorna nulla e risponde con `Action::danger`.
- [ ] I testi nuovi dei messaggi sono scritti in inglese e tradotti in `lang/vendor/nova/it.json`, il file che contiene già le traduzioni della action (`"Accessibility Issue"`, riga 48).
- [ ] `canManageHikingRoute()` restituisce `false`, senza errore, per un RegionalReferent senza regione.
- [ ] Le altre action di `HikingRoute` non cambiano permessi.
- [ ] Test Feature sull'endpoint delle action di Nova (`uriKey` `segnalazioni-percorribilità`, ricavato dal nome tradotto) con i casi: Administrator, NationalReferent, RegionalReferent della regione del percorso, RegionalReferent di un'altra regione, LocalReferent con intersezione, LocalReferent senza intersezione, RegionalReferent senza regione (con un solo percorso selezionato, così Nova usa `detailQuery()` e non il difetto di `indexQuery()` lasciato fuori; esito atteso: risposta `danger`, nessuna modifica), selezione mista di un LocalReferent.
- [ ] Il test esistente `tests/Unit/Models/UserTest.php::regional_referent_without_region_cannot_manage_routes`, che oggi si aspetta un'eccezione, viene riscritto perché si aspetti `false`.

## Rischi

- **Selezione mista aggiornata solo in parte senza avviso** (verificato con un test temporaneo): mitigato spostando il filtro per territorio in `handle()`, con un messaggio sui percorsi saltati.
- **Test esistente che fissa l'errore come comportamento atteso** (`UserTest.php:623-642`, `expectException` alla riga 640): riscritto con `assertFalse`.
- **Il `canSee` viene valutato anche quando la action viene eseguita**, e in quel momento Nova costruisce una Resource con un modello vuoto: il ramo per percorso del `canSee` si attiva solo se la Resource ha un percorso esistente, altrimenti vale il controllo per ruolo. Senza questa condizione l'esecuzione sarebbe rifiutata con un 403.
- **Effetto sulle altre action che usano `canManageHikingRoute()`** (validazione, favoriti, `SectorRefactoring`…): per il RegionalReferent senza regione passano da un errore a un rifiuto. I permessi non cambiano.
- **I test passano dal `save()` di `CreateIssue::handle()`**, che fa partire `HikingRouteObserver` e `UpdateEcTrackAwsJob` verso WMFE, con `QUEUE_CONNECTION=sync` in `phpunit.xml`: i test usano `Queue::fake()` e `Bus::fake()`, e `DatabaseTransactions` perché girano sul DB di sviluppo.
- **Comportamenti accettati, già impliciti nella regola di `canManageHikingRoute()`**: un LocalReferent può agire su un percorso che interseca il suo settore anche solo in parte; su un percorso appena modificato le intersezioni calcolate da `CalculateIntersectionsJob` possono non essere ancora aggiornate; gli utenti con il solo ruolo ClubManager, ItineraryManager o SicaiManager restano esclusi, come oggi.
- **Rollback**: nessuna migrazione. La correzione di `canManageHikingRoute()` e il `canSee` per percorso vanno annullati insieme, altrimenti la detail view del RegionalReferent senza regione va in errore. Le segnalazioni scritte dai nuovi utenti restano nel DB e su WMFE; la loro storia è in `issues_chronology`.

## Out of scope

- `AddRegionFavoritePublicationDateToHikingRouteAction`: il `canSee` la mostra anche al NationalReferent, ma senza `canRun` passa dalla policy `update`, che al NationalReferent la rifiuta. Probabilmente è lo stesso difetto di questo ticket (dedotto dal codice, non provato con una richiesta reale). Va verificato con chi conosce la funzionalità se il NationalReferent deve poterla eseguire; tracciato in `notes.md`, «Follow-up».
- `HikingRoute::indexQuery()` legge `auth()->user()->region->id` senza controllo (`app/Nova/HikingRoute.php:144-148`): per un RegionalReferent senza regione la index view dei percorsi va già in errore oggi, e con lei l'esecuzione di una action su più percorsi. Difetto preesistente, non segnalato dal cliente; tracciato in `notes.md`, «Follow-up».

## Moduli toccati

Tutti nel repo principale `osm2cai2` (feature custom, nessun submodule coinvolto):

- `app/Nova/HikingRoute.php` — `canSee` e `canRun` di `CreateIssue` in `actions()`
- `app/Nova/Actions/CreateIssue.php` — filtro per territorio e messaggi in `handle()`
- `app/Models/User.php` — `canManageHikingRoute()`, caso del RegionalReferent senza regione
- `lang/vendor/nova/it.json` — traduzione dei messaggi nuovi
- `tests/Feature/` — nuovo test sull'endpoint delle action per `CreateIssue`
- `tests/Unit/Models/UserTest.php` — riscrittura di `regional_referent_without_region_cannot_manage_routes`
