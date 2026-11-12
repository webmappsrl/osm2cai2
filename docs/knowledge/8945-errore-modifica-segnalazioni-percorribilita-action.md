# Permessi delle Nova action sui percorsi

## Come funziona oggi

Chi può eseguire una Nova action della Resource `HikingRoute` lo decidono tre punti, valutati da Nova in momenti diversi:

- **`canRun`** (impostato con `->canRun(...)` in `HikingRoute::actions()`): se manca, Nova controlla il metodo `runAction` della policy e, non trovandolo, ricade su `HikingRoutePolicy::update()` (`vendor/laravel/nova/src/Authorizable.php:347-349`). `update()` restituisce sempre `false`, e solo `before()` lascia passare l'Administrator: **una action senza `canRun` è eseguibile solo dall'Administrator**, anche se il `canSee` la mostra ad altri ruoli. Nova controlla la proprietà `runCallback` (`vendor/laravel/nova/src/Actions/ActionModelCollection.php:29`), quindi ridefinire `authorizedToRun()` nella classe della action non basta. Nella closure il secondo argomento è il modello, non l'utente: l'utente si prende da `$request->user()`.
- **`canSee`**: Nova lo valuta su una Resource che a volte ha il percorso e a volte no.
  - Detail view e righe della index view (per le action `showInline()`): la Resource ha il percorso della riga (`vendor/laravel/nova/src/ResolvesActions.php:101-108`), quindi si può controllare il singolo percorso.
  - Menu delle action sulla selezione multipla ed esecuzione della action: la Resource ha un modello vuoto (`newResource()`, `vendor/laravel/nova/src/Http/Requests/InteractsWithResources.php:65`). Un controllo per percorso qui rifiuterebbe l'esecuzione con un 403: per questo il ramo per percorso va attivato solo se `$this->resource->exists`.
- **`handle()`**: è l'unico punto che vede davvero i percorsi selezionati, ed è qui che si filtra per territorio con `User::canManageHikingRoute()`.

Nova esegue `handle()` a **blocchi di 200 modelli** (`Action::$chunkCount`, `vendor/laravel/nova/src/Actions/Action.php:62`) e mostra solo l'esito dell'ultimo blocco (`Action::handleResult()`, `Action.php:598-600`). Un messaggio che dipende dai percorsi selezionati (conteggi, «non autorizzato») va quindi calcolato in `handleResult()`, sommando quanto restituito da ogni blocco: `CreateIssue` ne è l'esempio.

Nella index view `HikingRoute::indexQuery()` filtra per territorio solo il RegionalReferent: un LocalReferent vede, e può selezionare, tutti i percorsi.

## Perché così

- **Filtro per territorio in `handle()` e non nel `canRun`** (oc:8945): con un `canRun` per percorso Nova esegue la action solo sui percorsi autorizzati e risponde con un successo se almeno uno è passato (`vendor/laravel/nova/src/Actions/DispatchAction.php:124-135`). In una selezione mista gli altri percorsi restano invariati e l'utente non lo sa. Il controllo in `handle()` permette di contarli e dirlo.
- **`canRun` sulla singola action e non un `runAction` nella policy** (oc:8945): il metodo della policy varrebbe per tutte le action di `HikingRoute` senza `canRun`, cambiandone i permessi senza che nessuno l'abbia chiesto.
- **`canManageHikingRoute()` restituisce `false` per un RegionalReferent senza regione** (oc:8945): il `canSee` per percorso la chiama a ogni apertura della detail view, che non deve andare in errore per un utente configurato male.

## Come ci siamo arrivati

- **Messaggio scelto in `handle()`** (oc:8945, superata in review): con «seleziona tutti quelli che corrispondono» e più di 200 percorsi, il messaggio dell'ultimo blocco poteva dire «non autorizzato» anche se i percorsi del territorio erano stati aggiornati. Sostituito dal calcolo in `handleResult()`.
