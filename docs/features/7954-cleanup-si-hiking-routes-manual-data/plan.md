> Ticket: oc:7954

# Plan — Cleanup manual_data su SiHikingRoute

## File da creare
| File | Repo | Operazione |
|---|---|---|
| `app/Console/Commands/CleanupSiHikingRoutesManualDataCommand.php` | osm2cai2 | Nuovo |

Nessun file esistente da modificare. Laravel auto-carica i command da `app/Console/Commands/` via `Kernel::load()`.

**Commit convention:** `feat(oc:7954): ...`

---

## Step 1 — Crea il command

**File:** `app/Console/Commands/CleanupSiHikingRoutesManualDataCommand.php`

### Signature e description

```php
protected $signature = 'osm2cai:cleanup-si-hiking-routes-manual-data
                        {--dry-run : Esegui senza scrivere sul database}
                        {--verbose : Stampa l\'ID di ogni record processato o skippato}';

protected $description = 'Rimuove manual_data dalle SiHikingRoute (app_id=2, layer_id=6) per ripristinare i valori DEM come current value';
```

### Struttura handle()

```
handle()
├── leggi opzioni: $isDryRun, $isVerbose
├── stampa header (dry-run se attivo)
├── build query SiHikingRoute (app_id=2, layer_id=6, ha manual_data non vuoto)
├── conta totale da processare
├── se totale = 0 → info "Nessun record da pulire" → return 0
├── crea progress bar sul totale
├── chunk(100) → per ogni record:
│   ├── skip se properties null        → contatore $skipped++, verbose log
│   ├── skip se manual_data assente    → contatore $skipped++, verbose log
│   ├── skip se manual_data vuoto []   → contatore $skipped++, verbose log
│   ├── Log::info() con id + manual_data values
│   ├── se !$isDryRun → unset + saveQuietly()
│   ├── traccia se dem_data assente (e osm_data assente) → $noDemIds[]
│   └── contatore $cleaned++
├── progress bar finish
├── stampa recap finale
│   ├── cleaned / skipped / errors
│   └── se $noDemIds non vuoto → warning con lista ID
└── return 0
```

### Query di selezione

```php
use App\Models\HikingRoute;
use Illuminate\Support\Facades\DB;

$query = HikingRoute::query()
    ->where('app_id', 2)
    ->whereIn('id', function ($sub) {
        $sub->select('layerable_id')
            ->from('layerables')
            ->where('layer_id', 6)
            ->where('layerable_type', HikingRoute::class);
    })
    ->whereNotNull('properties')
    ->whereRaw("properties::jsonb ? 'manual_data'");
```

> Il filtro `whereRaw("properties::jsonb ? 'manual_data'")` seleziona solo i record
> che hanno la chiave nel JSONB, evitando di caricare tutti i record in memoria.
> Il check "manual_data non vuoto" viene fatto a livello PHP nel loop per gestire
> correttamente array vuoti `[]` senza una query JSONB aggiuntiva.

### Logica per singolo record (dentro chunk)

```php
$properties = $route->properties;

// skip: properties null
if (! is_array($properties)) {
    $skipped++;
    if ($isVerbose) $this->line("  SKIP [{$route->id}] properties non è array");
    $bar->advance();
    continue;
}

// skip: manual_data assente o vuoto
$manualData = $properties['manual_data'] ?? null;
if (empty($manualData)) {
    $skipped++;
    if ($isVerbose) $this->line("  SKIP [{$route->id}] manual_data assente o vuoto");
    $bar->advance();
    continue;
}

// log pre-rimozione (anche in dry-run)
Log::info('cleanup-si-hiking-routes-manual-data: rimozione manual_data', [
    'id'          => $route->id,
    'manual_data' => $manualData,
    'dry_run'     => $isDryRun,
]);

// traccia record senza DEM né OSM
$demData = $properties['dem_data'] ?? null;
$osmData = $properties['osm_data'] ?? null;
if (empty($demData) && empty($osmData)) {
    $noDemIds[] = $route->id;
}

if (! $isDryRun) {
    unset($properties['manual_data']);
    $route->properties = $properties;
    $route->saveQuietly();
}

$cleaned++;
if ($isVerbose) {
    $mode = $isDryRun ? 'DRY-RUN' : 'CLEANED';
    $this->line("  {$mode} [{$route->id}]");
}
$bar->advance();
```

### Recap finale

```php
$this->newLine(2);
$this->info("✅ Puliti:  {$cleaned}");
$this->info("⏭️  Saltati: {$skipped}");
if ($errors > 0) {
    $this->warn("❌ Errori:  {$errors}");
}
if (! empty($noDemIds)) {
    $this->newLine();
    $this->warn('⚠️  I seguenti record sono stati puliti ma non hanno DEM né OSM data disponibile:');
    $this->warn('   ID: ' . implode(', ', $noDemIds));
}
```

---

## Step 2 — Verifica manuale post-implementazione

Prima del commit, lanciare nell'ambiente locale:

```bash
# Dry-run per verifica
docker exec -it php81-osm2cai2 php artisan osm2cai:cleanup-si-hiking-routes-manual-data --dry-run --verbose

# Lancio reale
docker exec -it php81-osm2cai2 php artisan osm2cai:cleanup-si-hiking-routes-manual-data --verbose
```

Verificare in Nova che su una SiHikingRoute precedentemente con manual_data
il tab DEM mostri CURRENT VALUE = DEM (o EMPTY se dem_data era assente).

---

## Note implementative

- `saveQuietly()` bypassa `HikingRouteObserver` — nessun job di intersezione/prossimità viene dispatchato
- `chunk(100)` evita out-of-memory su dataset grandi
- Il command è idempotente: può essere rilanciato senza effetti su record già puliti
- `whereRaw("properties::jsonb ? 'manual_data'")` richiede PostgreSQL/PostGIS (non SQLite) — coerente con la configurazione di test del progetto
