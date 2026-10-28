> Ticket: oc:8893

# Notes — API restituiscono dati sensibili

## Deviazioni dal piano

- **Task 2, test di export:** il piano indicava `json('properties.sicai')`, ma `ExportResource` è un `JsonResource` e avvolge la risposta in `data`: il test legge `json('data.properties.sicai')`. Errore del piano, nessun effetto sul codice.
- **Task 2, nuovi test in `HikingRouteControllerV2Test`:** scritti senza `: void` e con un helper `assertSicaiWithoutPersonalData()` condiviso dai due test, per seguire lo stile già presente nel file.
- **Task 3, Pint:** `pint --dirty` ha riformattato per intero `HikingRoute.php` e `HikingRouteController.php` (circa 80 righe estranee: spazi nelle concatenazioni, `HikingRoute::class` → `self::class`). I due file sono stati riportati allo stato di `develop` e sono state riapplicate solo le modifiche del fix, per tenere il diff limitato al ticket. In `HikingRouteGeojsonDescriptionTest.php` è rimasta la piccola riformattazione di Pint (`new HikingRoute()` → `new HikingRoute`).

## Bug trovati

- Nessuno nel codice del ticket. Test che falliscono già su `develop`, indipendenti da questo lavoro (verificati con le stesse esecuzioni su branch e su `develop`): `SignageMapControllerTest` (arrow midpoint), `SignageMapControllerRoundTravelTimeTest` (3), `HikingRouteSignageExporterTest`, `HikingRouteMorphClassTest` (Elasticsearch), `MiturAbruzzoApiTest`, `RegionGeojsonCompleteTest`, e i test UGC di oc:8867 che in locale danno `QueryException`/`UniqueConstraintViolationException`.

## Decisioni

- **Split del ticket non eseguito:** la seconda parte (tappe duplicate con due osm2cai_id per lo stesso osm_id) resta nel testo del ticket, fuori da questo lavoro.
- **Lista dei campi nascosti:** si riusa quella che già nascondeva i dati su WMFE (`referente.name`/`email`, `referente_regionale`, `email_ref_regionale`, `sezione_ref_regionale`, `sezioni_manutenzione`), così nessun canale torna a pubblicare un campo che prima non pubblicava.
- **Filtro senza condizione su `app_id`:** in produzione i percorsi 1121, 4348, 11060, 11077, 11672 (`app_id` 1, con blocco `sicai`) pubblicano su WMFE nome ed email dei referenti; il filtro ora vale anche per loro.
- **Nessuna rigenerazione forzata dei file WMFE:** quei file si aggiornano al prossimo salvataggio del percorso. Le due API costruiscono la risposta a ogni chiamata e sono corrette dal deploy.
- **Canali lasciati fuori per decisione del dev**, verificati in locale che espongono ancora il blocco `sicai` intero: route wm-package `ec/track/multiple` e `ec/track/download/{id}.geojson` (sotto `/api` e `/api/v2`), `/api/geojson-complete/region/{id}` e `/sector/{id}`.
- **Tag Orchestrator:** saltati su richiesta del dev.
- **Trascrizioni delle call:** non consultate, NotebookLM con credenziali scadute anche dopo `nlm login`; il dev ha scelto di proseguire senza.

## Verifiche eseguite

- Test rossi prima della modifica e verdi dopo: `HikingRoutePublicPropertiesTest` (5), i 3 test `*_sicai_personal_data`, `test_filters_sicai_personal_data_regardless_of_app_id`.
- Classi toccate: `HikingRoutePublicPropertiesTest`, `HikingRouteGeojsonDescriptionTest`, `HikingRouteControllerV2Test`, `ExportControllerTest` → 61 passati.
- `curl` in locale su `/api/v2/hiking-route/29194`, `/api/v2/hiking-route-osm/7569936`, `/api/v2/export/hiking-routes/29194`: nessuna email, `sezione` presente.
- Suite completa: 420 passati, 110 falliti, tutti preesistenti (vedi sopra).

## Follow-up

- **Grave:** `/api/v2/export/users/{id}` e `/list` sono pubblici e restituiscono l'hash bcrypt della password (`ExportResource.php:58`). Ticket da aprire.
- Canali fuori scope elencati sopra (wm-package `ec/track/*`, `geojson-complete`): restano esposti.
- Debito tecnico dalla review: il filtro è una lista di campi da nascondere (un campo personale nuovo in `sicai` sarebbe pubblico) e viene applicato canale per canale; il docblock di `getPublicProperties()` dice «unico punto», ma `enhanceHikingRouteProperties()` chiama direttamente il filtro.
- Preesistente: in `ExportResource` `MountainGroup` è usato in `instanceof` senza `use`, quindi quel ramo non scatta mai.
