> Ticket: oc:7982

# Dati SICAI — Fix valori campi DEM nell'export Excel

## Cosa cambia

Il command `osm2cai:cleanup-si-hiking-routes-manual-data` viene esteso per agire su **tutti** i record con app_id=2 (non solo quelli con layer_id=6): rimuove `manual_data` se presente e ripristina i valori top-level dei campi DEM da `osm_data`/`dem_data` in cascata. Un unico command idempotente risolve sia i record con `manual_data` ancora presente sia quelli già ripuliti da esecuzioni precedenti.

## Perché

Il `CleanupSiHikingRoutesManualDataCommand` rimuoveva `properties['manual_data']` ma lasciava intatti i valori top-level (`properties['ascent']`, `properties['descent']`, ecc.) che erano stati scritti a partire dal dato manuale. Nova mostra correttamente i valori DEM grazie a `classifyField` (che legge da `dem_data`/`osm_data` in cascata), ma l'exporter Excel (`EcTrackExcelExporter`) legge direttamente i campi top-level — quindi continuava a mostrare i valori stantii di tutti i campi che erano stati sovrascritti da `manual_data`.

## Requisiti

- [ ] `CleanupSiHikingRoutesManualDataCommand` esteso per agire su **tutti** i record con app_id=2, non solo quelli con layer_id=6 e non solo quelli con `manual_data`
- [ ] Per ogni record: rimuovere `manual_data` se presente, poi ripristinare i valori top-level (`ascent`, `descent`, `ele_min`, `ele_max`, `ele_from`, `ele_to`, `distance`, `duration_forward`, `duration_backward`) con priorità: OSM (se `osmid` non null e `osm_data[field]` non null) → DEM → lascia invariato
- [ ] `saveQuietly()` per non triggerare gli observer
- [ ] Supporto `--dry-run` e `-v` invariato
- [ ] Se `dem_data[field]`, `osm_data[field]` e `manual_data[field]` sono tutti assenti/null, il valore top-level viene impostato a `null`
- [ ] Idempotente: rieseguirlo su record già corretti non produce modifiche

## Rischi

- **Nessuna sorgente disponibile:** se per un campo nessuna delle tre sorgenti (`dem_data`, `osm_data`, `manual_data`) contiene un valore, il top-level viene azzerato a `null`. Warning finale con elenco degli ID coinvolti per eventuale intervento manuale.

## Out of scope

- Fix dell'`EcTrackExcelExporter` in `wm-package` per usare `classifyField` (tracciato in oc:7984)
- Record con app_id != 2
- Ricalcolo del DEM tramite la catena di job

## Moduli toccati

- `app/Console/Commands/CleanupSiHikingRoutesManualDataCommand.php` — aggiornamento
