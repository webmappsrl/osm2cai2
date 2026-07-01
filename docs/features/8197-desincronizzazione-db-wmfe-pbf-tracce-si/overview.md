> Ticket: oc:8197

# Desincronizzazione DB ↔ WMFE ↔ PBF su tracce aggiornate senza observer

## Cosa cambia

`HikingRouteObserver::syncOsm2caiStatusToChildSiHikingRoutes()` propaga `osm2cai_status`, `validator_id` e `validation_date` da una HikingRoute parent alle SiHikingRoute figlie usando `saveQuietly()`. Questo bypassa gli Observer Laravel e quindi salta il dispatch di `UpdateEcTrackAwsJob` (sync WMFE) e della rigenerazione PBF, lasciando le tracce figlie con dati validi nel DB ma versioni obsolete su mappa/app.

Il fix dispatcha esplicitamente, subito dopo il `saveQuietly()` di ogni figlio effettivamente cambiato:
- `UpdateEcTrackAwsJob` sempre (sync WMFE)
- la rigenerazione PBF (`updatePbfsForHikingRoute()`) solo se il figlio ha anche una modifica di geometria (`wasChanged('geometry')`) — questo metodo modifica solo `osm2cai_status`/`validator_id`/`validation_date` sul figlio, mai la geometria, quindi in pratica oggi questo branch non scatta mai. La maggioranza dei figli (517 su 548, verificato sul DB) ha un `osmfeatures_id` proprio e riceve la propria geometria da un sync OSM indipendente, non dal parent — la condizione resta comunque corretta come guardia se in futuro il metodo venisse estesa a toccare anche la geometria.

## Perché

Segnalato dall'utente su tracce SI validate (`osm2cai_status = 4`), figlie di un parent: backoffice mostra dati corretti, mappa/app mostrano la versione precedente (`geometry_sync = false`). Causa identificata: `saveQuietly()` sui figli bypassa `saved()`/`updated()` degli Observer, che sono l'unico punto che dispatcha la sync verso WMFE e PBF.

## Requisiti

- [ ] `syncOsm2caiStatusToChildSiHikingRoutes()` dispatcha `UpdateEcTrackAwsJob` per ogni figlio effettivamente modificato
- [ ] `syncOsm2caiStatusToChildSiHikingRoutes()` dispatcha la rigenerazione PBF per il figlio solo se `wasChanged('geometry')` è true sul figlio
- [ ] Nessuna modifica al comportamento per i campi non toccati da questo metodo (nessuna regressione su altri Observer/side-effect)
- [ ] Test automatico (`Queue::fake()`) che verifica che l'aggiornamento di `osm2cai_status` su un parent dispatchi `UpdateEcTrackAwsJob` anche per i figli propagati via `saveQuietly()`

## Rischi

- Nessun rischio di ricorsione: verificato via query sul DB locale che nessuna SiHikingRoute figlia ha a sua volta figli (0 nipoti).
- Il fix non copre altri path che usano `saveQuietly()`/`updateQuietly()` (sync osmfeatures bulk, `ForceUpdateOsmFeaturesFromTo`, SignageMap, cleanup SI) — questi possono generare lo stesso sintomo e sono esplicitamente out of scope.
- Le tracce già desincronizzate in produzione (es. `29360`, `29369`, e altre non ancora identificate) non vengono corrette da questo fix, che previene solo nuove desincronizzazioni dal path corretto — gestite dal ticket parallelo oc:8200.

## Out of scope

- Fix di altri path `saveQuietly()`/`updateQuietly()` che bypassano gli observer (bulk osmfeatures sync, `ForceUpdateOsmFeaturesFromTo`, SignageMap, cleanup SI)
- Rigenerazione/audit dei dati storici già desincronizzati → tracciato in **oc:8200**
- Servizio centralizzato `EcTrackSyncService::syncToWmfeAndPbf()` proposto nel ticket come soluzione a medio termine (non necessario per chiudere la causa specifica di questo bug)
- Compensazione WMFE nella sync osmfeatures bulk (esiste già solo per PBF)

## Moduli toccati

- `app/Observers/HikingRouteObserver.php` — metodo `syncOsm2caiStatusToChildSiHikingRoutes()`
- Nuovo test: `tests/Unit/Observers/HikingRouteObserverTest.php`
