> Ticket: oc:8893

# [OSM2CAI] API restituiscono dati sensibili

## Cosa cambia

Le API pubbliche dei percorsi smettono di restituire i dati personali dei referenti SICAI contenuti nel blocco `properties.sicai`: nome ed email del referente, nome ed email del referente regionale, sezione del referente regionale, sezioni di manutenzione.

Oggi `/api/v2/hiking-route/{id}` (e la variante per osm_id, che usa lo stesso `buildHikingRouteResponse`) copia nella risposta l'intero `hiking_routes.properties`, blocco `sicai` compreso. `/api/v2/export/hiking-routes/{id}` serializza tutte le colonne del modello, quindi anche `properties.sicai`. Entrambi gli endpoint sono pubblici, senza autenticazione.

Esiste già un filtro, `HikingRoute::filterSicaiPropertiesForApi()`, ma lo usa solo `getGeojson()` (canale WMFE/Elasticsearch) e solo per i percorsi con `app_id === 2`. Dopo questo lavoro:

- il filtro è l'unica lista dei campi SICAI da non pubblicare, usata da tutti i canali pubblici: i due endpoint API e WMFE;
- si applica a qualunque percorso abbia il blocco `sicai`, indipendentemente da `app_id`;
- i dati restano intatti nel DB: Nova continua a leggerli e a mostrarli agli utenti autenticati.

## Perché

Il cliente ha segnalato che dati personali (nome ed email dei referenti delle tappe SICAI) sono consultabili da chiunque via API, per esempio su `https://osm2cai.cai.it/api/v2/hiking-route/29194`.

Sul DB locale: 1.180 percorsi hanno il blocco `sicai` (1.175 con `app_id` 2, 5 con `app_id` 1); 1.157 hanno l'email del referente valorizzata, 434 l'email del referente regionale. I 5 percorsi con `app_id` 1 (id 1121, 4348, 11060, 11077, 11672) sfuggono oggi anche al filtro WMFE, perché la condizione è sull'app e non sulla presenza del dato.

## Requisiti

- [ ] `/api/v2/hiking-route/{id}` e la variante per osm_id non restituiscono `sicai.referente` (name, email), `sicai.referente_regionale`, `sicai.email_ref_regionale`, `sicai.sezione_ref_regionale`, `sicai.sezioni_manutenzione`.
- [ ] `/api/v2/export/hiking-routes/{id}` non restituisce gli stessi campi.
- [ ] I campi SICAI non sensibili (es. `sezione`, `percorribilità`, `segnaletica`) restano nelle risposte.
- [ ] La lista dei campi da nascondere è una sola, nel modello `HikingRoute`, ed è la stessa per API e WMFE (la lista attuale di `filterSicaiPropertiesForApi`).
- [ ] Il filtro si applica a ogni percorso con blocco `sicai`, a prescindere da `app_id`, anche in `getGeojson()`.
- [ ] I dati nel DB non vengono modificati; Nova continua a mostrarli.
- [ ] Test Feature su entrambi gli endpoint: assenza dei campi sensibili e presenza di quelli legittimi.
- [ ] Test Unit del filtro in `getGeojson()` su un percorso con `app_id` 1.

## Rischi

- **Consumer esterni che leggono i campi rimossi.** Nessun consumer interno usa questi campi via API (Nova li legge dal modello), ma i consumer esterni non sono determinabili dal repo. Mitigazione: si rimuovono solo campi personali, non dati del percorso; un consumer legittimo non dovrebbe dipendere da nome ed email del referente.
- **Copie già pubblicate su WMFE.** I due endpoint API costruiscono la risposta a ogni chiamata, senza cache: per loro il fix è efficace al deploy. I file WMFE invece sono generati in anticipo: quelli dei percorsi con `app_id` diverso da 2 (in produzione verificati 1121, 4348, 11060, 11077, 11672) contengono ancora nome ed email dei referenti e saranno filtrati solo alla prossima rigenerazione, cioè al prossimo salvataggio del percorso. Decisione del dev: nessuna rigenerazione forzata in questo ciclo.

## Out of scope

- Seconda parte del ticket: tappe SICAI duplicate, con due osm2cai_id per lo stesso osm_id (`hiking_routes` e `si-hiking-routes`).
- Altri canali pubblici che espongono ancora il blocco `sicai` intero, verificati in locale e lasciati fuori per decisione del dev (oggi probabilmente usati solo dall'app o non usati):
  - route di wm-package `ec/track/multiple` e `ec/track/download/{id}.geojson`, sotto `/api` e `/api/v2`: usano il modello base `EcTrack`, che non passa dal filtro di `HikingRoute`;
  - `/api/geojson-complete/region/{id}` e `/api/geojson-complete/sector/{id}` (`RegionController`, `SectorController`): uniscono `properties` per intero; sulla regione 17 restituiscono 12 email di referenti regionali in una sola chiamata.
- `/api/v2/export/users/{id}` e `/api/v2/export/users/list`: endpoint pubblici che, da `ExportResource.php:58`, restituiscono anche l'hash bcrypt della password degli utenti (verificato in locale). Problema grave, da trattare in un ticket separato, non ancora aperto.
- Rigenerazione forzata dei file WMFE già pubblicati per i percorsi con `app_id` diverso da 2: si aggiornano al prossimo salvataggio, eventualmente si ricalcolano in un secondo momento.
- Altri campi testuali del blocco `sicai` (`note`, `segnalazioni`) che potrebbero contenere dati personali: si nasconde solo quanto indicato dal cliente più quanto già nascosto su WMFE.

## Moduli toccati

Tutto nel repo principale `osm2cai2`:

- `app/Models/HikingRoute.php` — filtro reso riusabile e applicato senza condizione su `app_id`.
- `app/Http/Controllers/HikingRouteController.php` — `buildHikingRouteResponse` applica il filtro.
- `app/Http/Resources/ExportResource.php` — il caso `HikingRoute` applica il filtro.
- `tests/` — test Feature sui due endpoint, test Unit del filtro.
