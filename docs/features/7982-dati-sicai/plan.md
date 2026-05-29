> Ticket: oc:7982

# Plan — Dati SICAI: Fix valori campi DEM nell'export Excel

## Repo coinvolto
`osm2cai2` (repo principale) — modifica custom, nessun submodule toccato.

## Commit convention
`fix(oc:7982): ...`

---

## Step 1 — Aggiorna `CleanupSiHikingRoutesManualDataCommand`

**File:** `app/Console/Commands/CleanupSiHikingRoutesManualDataCommand.php`

### Modifiche alla query

Rimuovere il filtro `whereIn` su `layerables` (layer_id=6) e il filtro `whereRaw` su `manual_data`. Il nuovo target è semplicemente tutti i record con `app_id=2`:

```php
$query = HikingRoute::query()
    ->where('app_id', 2)
    ->whereNotNull('properties');
```

### Aggiorna la descrizione del command

```php
protected $description = 'Removes manual_data from HikingRoute records (app_id=2) and restores top-level DEM fields from osm_data/dem_data cascade';
```

### Costante campi DEM

Definire i campi da ripristinare come costante privata:

```php
private const DEM_FIELDS = [
    'ascent', 'descent', 'ele_min', 'ele_max',
    'ele_from', 'ele_to', 'distance',
    'duration_forward', 'duration_backward',
];
```

### Logica di ripristino dentro il loop

Dopo aver rimosso `manual_data`, ripristinare i valori top-level replicando esattamente la priorità di `classifyField`:

```php
$properties = $route->properties;

// 1. rimuovi manual_data se presente
$hadManualData = array_key_exists('manual_data', $properties);
unset($properties['manual_data']);

// 2. leggi sorgenti
$demData = $this->safeArray($properties['dem_data'] ?? null);
$osmData = $this->safeArray($properties['osm_data'] ?? null);
$osmid   = $route->osmid ?? null;

// 3. ripristina ogni campo top-level
$noSourceFields = [];
foreach (self::DEM_FIELDS as $field) {
    $osmValue = ($osmid !== null) ? ($osmData[$field] ?? null) : null;
    $demValue = $demData[$field] ?? null;

    if ($osmValue !== null) {
        $properties[$field] = $osmValue;
    } elseif ($demValue !== null) {
        $properties[$field] = $demValue;
    } else {
        $properties[$field] = null;
        $noSourceFields[] = $field;
    }
}

if (!empty($noSourceFields)) {
    $noDemIds[] = $route->id;
}
```

### Helper `safeArray`

Aggiungere il metodo privato (stessa logica del trait `HasDemClassification`):

```php
private function safeArray(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}
```

### Output `-v`

In modalità verbose distinguere tra record con `manual_data` rimosso e record già puliti:

```php
$mode = $isDryRun ? 'DRY-RUN' : 'FIXED';
$note = $hadManualData ? '(manual_data removed)' : '(already clean)';
$this->line("  {$mode} [{$route->id}] {$note}");
```

### Contatori

Rinominare `$cleaned` in `$fixed` per riflettere il nuovo comportamento (ripristino top-level, non solo rimozione `manual_data`). Aggiornare il summary finale:

```php
$this->info("Fixed: {$fixed}");
$this->info("Skipped: {$skipped}");
```

Il blocco warning `$noDemIds` rimane invariato.

**Commit:**
```
fix(oc:7982): extend cleanup command to app_id=2 and restore top-level DEM fields
```

---

## Step 2 — Aggiorna i test esistenti

**File:** `tests/` — cercare test su `CleanupSiHikingRoutesManualDataCommand`

- Aggiornare i test esistenti per riflettere il nuovo perimetro (app_id=2, no filtro layer_id=6)
- Aggiungere casi:
  - Record senza `manual_data`: verifica che i valori top-level vengano aggiornati da `dem_data`
  - Record con `osmid` e `osm_data`: verifica che OSM abbia priorità su DEM
  - Record senza nessuna sorgente: verifica che i valori top-level diventino `null`
  - `--dry-run`: verifica che nessun valore venga scritto nel DB

**Commit:**
```
test(oc:7982): update and extend tests for cleanup command
```

---

## Note implementative

- Usare `??` (non `?`) per l'operatore JSONB in `whereRaw` per evitare che PDO lo interpreti come placeholder (da CLAUDE.md)
- `chunkById()` per evitare skipping di record durante l'iterazione (da CLAUDE.md)
- La condizione `osmid !== null` è obbligatoria prima di leggere `osm_data` — replicare esattamente `classifyField` in `HasDemClassification`
- `saveQuietly()` per non triggerare observer e job in coda
