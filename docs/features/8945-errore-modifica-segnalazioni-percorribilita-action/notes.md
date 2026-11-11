> Ticket: oc:8945

# Notes — [OSM2CAI] errore nella modifica di Segnalazioni Percorribilità (action) di un percorso

## Divergenze dal piano, task per task

### Task 2 — Test Feature sull'esecuzione di CreateIssue

Il parametro `resources` della POST `/nova-api/hiking-routes/action` va passato come array di id, non come stringa separata da virgole: `ActionRequest::toSelectedResourceQuery()` lo legge con `Arr::wrap()` (`vendor/laravel/nova/src/Http/Requests/ActionRequest.php:111`). Con la stringa un percorso solo funzionava per caso, mentre la selezione mista non trovava nessun percorso e Nova rispondeva con un corpo vuoto.

Inoltre l'helper `assertDanger` verifica il testo esatto del messaggio di `CreateIssue::handle()`. Con il solo controllo sulla chiave `danger`, i casi negativi passavano già prima della correzione, perché anche il rifiuto generico di Nova («Sorry! You are not authorized…») è una risposta `danger`.

### Task 4 — canSee per percorso

Il test della detail view usa `GET /nova-api/hiking-routes/actions?display=detail&resources[]=<id>`: la route `/nova-api/hiking-routes/<id>/actions` indicata nel piano non esiste in Nova 5 (`php artisan route:list --path=nova-api`).

Il test della index view non usa `GET /nova-api/hiking-routes`. La Resource `HikingRoute` non dichiara campi di ricerca, quindi non si possono isolare i percorsi del test, e l'index sul DB di sviluppo impiegava circa 15 secondi. Il test chiama direttamente `availableActionsOnTableRow()`, cioè il metodo che `Resource::serializeForIndex()` usa per le action di ogni riga (`vendor/laravel/nova/src/Resource.php:521`).

Verifica fatta: disattivando per prova il ramo per percorso del `canSee`, falliscono esattamente i test della detail view e delle righe della index view.

### Task 6 — Verifica finale

Pint non è stato applicato a `app/Nova/HikingRoute.php`: il file non era conforme e Pint lo riformattava per intero, aggiungendo centinaia di righe estranee al ticket. Sono state tenute solo le righe di oc:8945. Gli altri file toccati erano già conformi.

La suite Unit completa non è stata eseguita: sul DB di sviluppo era ancora in corso dopo oltre 15 minuti ed è stata fermata. Al suo posto sono stati eseguiti i soli test che passano dal codice toccato (cercati in `tests/` per `canManageHikingRoute`, `CreateIssue` e la Resource `HikingRoute`): `CreateIssueActionTest`, `UserTest` e `ManageHikingRouteValidationActionTest`, tutti passati.

Prova manuale in Nova (11/11/2026), fatta sia dalla dev sia da Claude con un LocalReferent della provincia Verbano-Cusio-Ossola, cercando `R13959` nell'index (un percorso dentro il territorio e uno fuori):
- il pulsante inline compare solo sulla riga del percorso nel territorio;
- dalla detail view l'esecuzione aggiorna la segnalazione, e sul percorso fuori territorio la action non compare;
- la selezione mista dal menu delle action mostra «Segnalazioni aggiornate su 1 percorsi, 1 saltati perché fuori dal tuo territorio».

## Bug trovati

## Decisioni

- Tag Orchestrator associato al ticket: `osm2cai2` (id 613), caratteristica repository (10/11/2026).
- Messaggio di `CreateIssue` calcolato sul totale della selezione (deciso in review, 11/11/2026, dopo l'approvazione del piano). Nova chiama `handle()` a blocchi di 200 percorsi (`vendor/laravel/nova/src/Actions/Action.php:62`) e mostra solo l'esito dell'ultimo blocco (`Action.php:598-600`). Con «seleziona tutti quelli che corrispondono» un LocalReferent seleziona tutti i percorsi (`indexQuery()` non lo filtra per territorio), e il messaggio scelto in `handle()` poteva dire «non autorizzato» anche se i percorsi del suo territorio erano stati aggiornati, o riportare i conteggi di un solo blocco. Ora `handle()` restituisce solo i conteggi del blocco e `CreateIssue::handleResult()` li somma e sceglie il messaggio. Il caso è coperto dal test `mixed_selection_larger_than_one_chunk_reports_totals_of_all_chunks`, che abbassa `$chunkCount` a 2. Senza l'override il test fallisce.

## Follow-up

- `HikingRoute::indexQuery()` (`app/Nova/HikingRoute.php:144-148`) legge `auth()->user()->region->id` senza controllare che la regione esista: per un RegionalReferent senza regione (1 su 65 nel DB locale) la index view dei percorsi va in errore, e con lei l'esecuzione di una action su più percorsi. Difetto preesistente, lasciato fuori da oc:8945 perché non segnalato dal cliente; da correggere in un ticket separato.
- `AddRegionFavoritePublicationDateToHikingRouteAction` (`app/Nova/HikingRoute.php`, `actions()`): il `canSee` la mostra ad Administrator e NationalReferent, ma la action non ha `canRun` e quindi passa da `HikingRoutePolicy::update()`, che restituisce `false` a chi non è Administrator. Dedotto dal codice, non provato con una richiesta reale: un NationalReferent la vede e viene rifiutato. Da verificare se il NationalReferent deve poterla eseguire (allora manca il `canRun`) o no (allora va tolto dal `canSee`), in un ticket separato.
