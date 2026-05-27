> Ticket: oc:7954

# Cleanup manual_data su SiHikingRoute

## Cosa cambia
Viene aggiunto un command Artisan rilanciabile che rimuove la chiave `manual_data`
dall'oggetto JSONB `properties` delle SiHikingRoute (app_id=2, layer_id=6).
Dopo la pulizia, il sistema di priorità `HasDemClassification` mostrerà come
"current value" i dati DEM (o OSM) invece di quelli manuali.

## Perché
I dati `manual_data` presenti sulle SiHikingRoute sovrascrivono i valori DEM nel
pannello Nova (tab DEM, colonna CURRENT VALUE). Rimuoverli permette di visualizzare
i dati calcolati automaticamente, che sono più affidabili per il contesto SICAI.

## Requisiti
- [ ] Command `osm2cai:cleanup-si-hiking-routes-manual-data` in `app/Console/Commands/`
- [ ] Filtra solo record con `app_id = 2` associati a `layer_id = 6` via tabella `layerables`
- [ ] Idempotente: skippa record con `properties = null` o `manual_data` assente/vuoto
- [ ] Rimuove la chiave `manual_data` da `properties` e salva con `saveQuietly()`
- [ ] Flag `--dry-run`: esegue tutto il flusso senza scrivere sul DB
- [ ] Flag `--verbose`: stampa l'ID di ogni record processato o skippato
- [ ] `Log::info()` con ID e valori di `manual_data` prima di ogni rimozione (anche in dry-run)
- [ ] Recap finale: contatori `cleaned` / `skipped` / `errors`
- [ ] Warning finale con lista ID dei record puliti ma privi di `dem_data` (e `osm_data`)

## Rischi
- **Irreversibilità**: la rimozione non è annullabile senza ripristino DB.
  Mitigato con `--dry-run` obbligatorio prima del lancio reale, e `Log::info()` che
  conserva i valori rimossi nei log Laravel.

## Out of scope
- HikingRoute con `app_id = 1` (osm2cai standard): non vengono toccate
- Record con `app_id = 2` ma non nel `layer_id = 6`: non vengono toccati
- Aggiunta di una guardia su `dem_data` prima della rimozione
- Backup automatico dei valori rimossi su tabella separata

## Moduli toccati
- `app/Console/Commands/CleanupSiHikingRoutesManualDataCommand.php` — nuovo file
