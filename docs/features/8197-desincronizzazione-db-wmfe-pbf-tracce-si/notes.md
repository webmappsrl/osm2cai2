> Ticket: oc:8197

# Notes — Desincronizzazione DB ↔ WMFE ↔ PBF su tracce aggiornate senza observer

## Deviazioni dal piano

- Il piano indicava `Queue::fake()` + `Queue::assertPushed()` per il test. L'implementer ha usato `Bus::fake()` + `Bus::assertDispatched()`/`Bus::batched()`: `Dispatchable::dispatch()` passa dal Bus, non dalla Queue facade — verificato che è lo stesso meccanismo già usato per il dispatch esistente nel metodo `saved()` del parent. Cambio tecnicamente necessario, non un'invenzione arbitraria.
- Il piano accedeva a `$job->ecTrack->id` nell'assert; la proprietà è `protected` in `BaseEcTrackJob`, quindi l'implementer ha usato il getter pubblico `getEcTrack()->id`.

## Bug trovati

- Durante la Fase: challenge è stato individuato un bug distinto (non introdotto da questo fix, preesistente): `UpdateEcTrackAwsJob` ricarica sempre il modello tramite `config('wm-package.ec_track_model')`, fissato a `App\Models\HikingRoute`. Per una `SiHikingRoute` questo significa perdere l'override `getFeatureCollectionMap()` specifico. **Verificato con dati reali di produzione** (file `29360.json` su WMFE) che questo NON impatta il JSON effettivamente pubblicato: `EcTrackResource` costruisce `related_pois` leggendo direttamente la relazione `ecPois`, non passando da `getFeatureCollectionMap()`. Nessuna azione necessaria, nessun ticket aperto per questo.

## Decisioni

- Scope limitato alla causa certa (propagazione ai figli via `saveQuietly()` in `HikingRouteObserver::syncOsm2caiStatusToChildSiHikingRoutes()`), escludendo deliberatamente altri path `saveQuietly()`/`updateQuietly()` esistenti (sync osmfeatures bulk, `ForceUpdateOsmFeaturesFromTo`, SignageMap, cleanup SI) e il servizio centralizzato `EcTrackSyncService` proposto nel ticket come soluzione a medio termine.
- Verificato via query DB che nessuna SiHikingRoute figlia ha a sua volta figli (0 "nipoti") — nessun rischio di ricorsione con l'approccio scelto (dispatch esplicito dopo `saveQuietly()`, non sostituzione con `save()`).
- Creato ticket parallelo **oc:8200** per la rigenerazione forzata WMFE/PBF su tutte le tracce SI esistenti (app_id=2, ~1188 record) — decisione presa con l'utente di non costruire una logica di detection (rischio falsi negativi su `geometry_sync`) ma rigenerare direttamente tutto il target.

## Review formale (wm-review-ticket)

- 5 finder paralleli: nessun finding bloccante, solo cleanup. Corretto subito: rimosse 2 chiamate ridondanti a `Queue::fake()` nel test (dead code, l'assert reale passa da `Bus::fake()`/`Bus::assertDispatched()`/`Bus::batched()`). Test rieseguiti dopo la pulizia: 2/2 PASS.
- Lasciati come follow-up (non correttivi per questo ciclo): duplicazione della logica di dispatch tra `saved()` e `syncOsm2caiStatusToChildSiHikingRoutes()` (debito tecnico, refactor da valutare separatamente), accoppiamento del test all'esatto formato stringa del nome batch PBF (minor).

## Follow-up

- oc:8200 — rigenerazione forzata WMFE/PBF per tutte le HikingRoute con `app_id=2`, incluse le due tracce segnalate nel ticket originale (`29360`, `29369`). **Rafforzato durante la review finale**: verificato sul DB che coppie parent/figlio con lo stesso `osmfeatures_id` hanno spesso geometrie diverse (`same_geom = false`) — segno che la desincronizzazione è un pattern più ampio del previsto, non isolato alle due tracce segnalate. Conferma che la scelta di rigenerare tutto il target (senza logica di detection puntuale) in oc:8200 è quella giusta.
- Non tracciato in un ticket dedicato (volutamente, vedi "Bug trovati" sopra): l'incoerenza `UpdateEcTrackAwsJob` vs classe reale del modello (`HikingRoute` vs `SiHikingRoute`) — nessun impatto verificato sul JSON pubblicato, monitorare se in futuro `EcTrackResource` dovesse iniziare a usare `getFeatureCollectionMap()`.
