# Piano: Select interattiva per la seconda meta nella freccia segnaletica

## Obiettivo

Nella visualizzazione delle frecce segnaletica (`SignageArrowsDisplay.vue`), la **seconda riga** (`rows[1]`) di una freccia con 3 destinazioni diventa una `<select>` dropdown. L'utente può scegliere tra i checkpoint intermedi disponibili (tra `rows[0]` e `rows[last]`, esclusi). La selezione è **persistente**. Le opzioni disponibili sono **calcolate a runtime** perché i checkpoint cambiano nel tempo.

---

## Architettura della Soluzione

### Struttura dati corrente
```
poles.properties.signage[hikingRouteId].arrows[idx] = {
  direction: "forward",
  rows: [
    { id: 456, name: "Rosignano M.", time_hiking: 45, distance: 2500 },      // rows[0] FISSO
    { id: 789, name: "Via Piè di Grotti", time_hiking: 90, distance: 5000 }, // rows[1] → SELECT
    { id: 1001, name: "Le Badie", time_hiking: 150, distance: 8000 }         // rows[last] FISSO
  ]
}
```

### Struttura dati dopo la feature

**In `poles.properties.signage[hikingRouteId].arrows[idx]`** — aggiunge due campi:
```json
{
  "direction": "forward",
  "rows": ["...invariato..."],
  "selected_midpoint_id": 789,
  "midpoints_data": {
    "789": { "time_hiking": 90, "distance": 5000, "elevation_gain": 300 },
    "999": { "time_hiking": 70, "distance": 3800, "elevation_gain": 200 }
  }
}
```
- `selected_midpoint_id` — ID scelto dall'utente, uno per freccia, persistito nel DB
- `midpoints_data` — dizionario `{ poleId → {time_hiking, distance, ...} }` per tutti i checkpoint del route da questo palo. Viene da `$hikingRouteMatrix` già disponibile in `processPointDirections()`. È stabile (dipende dalla geometria, non dai checkpoint attivi).

**In `hiking_route.properties.signage.checkpoint_order`** — array ordinato degli ID checkpoint lungo la traccia:
```json
{ "checkpoint_order": [456, 789, 999, 1001] }
```
Aggiornato ad ogni `processPointDirections()`.

### Come si calcolano available_midpoints a runtime

In `SignageArrows.php::resolveAttribute()`, per ogni arrow:
1. Legge `checkpoint_order` dalla HikingRoute corrispondente
2. Filtra vs checkpoints attivi (`hiking_route.properties.signage.checkpoint`)
3. Trova la posizione di `rows[0].id` e `rows[last].id` nell'ordine
4. Estrae gli ID intermedi (tra i due, ESCLUSI entrambi gli estremi — già fissi nel cartello)
5. Query su `Poles` per ottenere i nomi aggiornati
6. Usa `arrow.midpoints_data[id]` per time_hiking e distance

### Condizione di disabilitazione
La select è **disabled** quando `available_midpoints.length === 0` (nessuna alternativa tra il primo e l'ultimo checkpoint).

---

## File coinvolti

| File | Ruolo |
|------|-------|
| `nova-components/SignageMap/src/Http/Controllers/SignageMapController.php` | Salva `checkpoint_order` in HikingRoute + `midpoints_data` + `selected_midpoint_id` per arrow + nuovo metodo `updateArrowMidpoint()` |
| `nova-components/SignageMap/src/Routes/api.php` | Nuova route PATCH |
| `nova-components/SignageArrows/src/SignageArrows.php` | Override `resolveAttribute()` per calcolare `available_midpoints` a runtime |
| `nova-components/SignageArrows/resources/js/components/SignageArrowsDisplay.vue` | Rendering `<select>` + evento |
| `nova-components/SignageArrows/resources/js/components/DetailField.vue` | Handler evento → chiamata API |

---

## Phase 0: Pattern esistenti (già letti)

- Route: `nova-components/SignageMap/src/Routes/api.php`
- Controller pattern: `updateArrowDirection()` righe 683-746
- Vue event pattern: `arrow-direction-changed` → `DetailField.vue` → API PATCH
- `SignageArrows.php`: `resolveAttribute()` usa `data_get($resource, $attribute)`
- `processPointDirections()`: `$hikingRouteMatrix` contiene dati per tutti i punti del route

---

## Phase 1: Backend — `processPointDirections()` + `SignageArrows.php`

### 1.1 Salva `checkpoint_order` nella HikingRoute

**File:** `SignageMapController.php`

Alla fine di `processPointDirections()`, DOPO il loop sui punti (dopo riga 624), aggiungi:

```php
// Salva checkpoint_order nella HikingRoute per uso runtime in resolveAttribute
$orderedCheckpoints = array_values(array_filter(
    array_map('intval', $pointsOrder),
    fn($id) => isset($checkpointSet[(string) $id])
));

$hikingRoute = HikingRoute::find($hikingRouteId);
if ($hikingRoute) {
    $hrProperties = $hikingRoute->properties ?? [];
    if (!isset($hrProperties['signage'])) {
        $hrProperties['signage'] = [];
    }
    $hrProperties['signage']['checkpoint_order'] = $orderedCheckpoints;
    $hikingRoute->properties = $hrProperties;
    $hikingRoute->saveQuietly();
}
```

### 1.2 Salva `midpoints_data` e preserva `selected_midpoint_id` per arrow

**File:** `SignageMapController.php`

Dentro il loop `foreach ($pointsOrder as $i => $pointId)`, modifica la costruzione di `$arrows` (righe 587-606):

```php
// midpoints_data: time/distance per tutti i checkpoint del route da questo palo.
// Stabile (dipende dalla geometria), usato a runtime per popolare available_midpoints.
$allCheckpointMidpointsData = [];
foreach ($checkpoints as $checkpointId) {
    $matrixEntry = $hikingRouteMatrix[(string) $checkpointId] ?? null;
    if ($matrixEntry) {
        $allCheckpointMidpointsData[(string) $checkpointId] = $matrixEntry;
    }
}

$arrows = [];

if (!empty($forwardRows)) {
    $direction = $existingArrows[0]['direction'] ?? 'forward';

    $selectedFwMidId = isset($existingArrows[0]['selected_midpoint_id'])
        ? (int) $existingArrows[0]['selected_midpoint_id']
        : null;

    // Applica override a rows[1] se il midpoint selezionato ha dati validi
    if ($selectedFwMidId && isset($forwardRows[1]) && isset($allCheckpointMidpointsData[(string) $selectedFwMidId])) {
        $midFeature = $pointFeaturesMap[(string) $selectedFwMidId] ?? null;
        $forwardRows[1] = $this->applyMinimumDisplayValues(array_merge([
            'id'          => $selectedFwMidId,
            'name'        => $midFeature['properties']['name'] ?? '',
            'ref'         => $midFeature['properties']['ref'] ?? '',
            'description' => $midFeature['properties']['description'] ?? '',
        ], $allCheckpointMidpointsData[(string) $selectedFwMidId]));
    }

    $arrowData = [
        'direction'      => $direction,
        'rows'           => $forwardRows,
        'midpoints_data' => $allCheckpointMidpointsData,
    ];
    if ($selectedFwMidId) {
        $arrowData['selected_midpoint_id'] = $selectedFwMidId;
    }
    $arrows[] = $arrowData;
}

if (!empty($backwardRows)) {
    $backwardIndex = !empty($forwardRows) ? 1 : 0;
    $direction = $existingArrows[$backwardIndex]['direction'] ?? 'backward';

    $selectedBwMidId = isset($existingArrows[$backwardIndex]['selected_midpoint_id'])
        ? (int) $existingArrows[$backwardIndex]['selected_midpoint_id']
        : null;

    if ($selectedBwMidId && isset($backwardRows[1]) && isset($allCheckpointMidpointsData[(string) $selectedBwMidId])) {
        $midFeature = $pointFeaturesMap[(string) $selectedBwMidId] ?? null;
        $backwardRows[1] = $this->applyMinimumDisplayValues(array_merge([
            'id'          => $selectedBwMidId,
            'name'        => $midFeature['properties']['name'] ?? '',
            'ref'         => $midFeature['properties']['ref'] ?? '',
            'description' => $midFeature['properties']['description'] ?? '',
        ], $allCheckpointMidpointsData[(string) $selectedBwMidId]));
    }

    $arrowData = [
        'direction'      => $direction,
        'rows'           => $backwardRows,
        'midpoints_data' => $allCheckpointMidpointsData,
    ];
    if ($selectedBwMidId) {
        $arrowData['selected_midpoint_id'] = $selectedBwMidId;
    }
    $arrows[] = $arrowData;
}
```

### 1.3 Calcolo runtime in `SignageArrows.php::resolveAttribute()`

**File:** `nova-components/SignageArrows/src/SignageArrows.php`

Leggi il file prima di modificarlo. Override di `resolveAttribute()`:

```php
protected function resolveAttribute($resource, string $attribute): mixed
{
    $signageData = data_get($resource, $attribute) ?? [];

    if (empty($signageData)) {
        return $signageData;
    }

    $signage = $signageData['signage'] ?? $signageData;

    foreach ($signage as $routeId => &$routeData) {
        if ($routeId === 'arrow_order' || !is_array($routeData) || !isset($routeData['arrows'])) {
            continue;
        }

        $hikingRoute = \App\Models\HikingRoute::find((int) $routeId);
        if (!$hikingRoute) {
            continue;
        }

        $hrSignage         = $hikingRoute->properties['signage'] ?? [];
        $checkpointOrder   = array_map('intval', $hrSignage['checkpoint_order'] ?? []);
        $activeCheckpoints = array_map('intval', $hrSignage['checkpoint'] ?? []);
        $activeSet         = array_flip($activeCheckpoints);

        // Unica query per i nomi di tutti i poli potenzialmente intermedi
        $poleNames = \App\Models\Poles::whereIn('id', $checkpointOrder)
            ->get(['id', 'name', 'ref'])
            ->keyBy('id');

        foreach ($routeData['arrows'] as &$arrow) {
            if (!isset($arrow['rows']) || count($arrow['rows']) < 3) {
                $arrow['available_midpoints'] = [];
                continue;
            }

            $nearestId = (int) ($arrow['rows'][0]['id'] ?? 0);
            $finalId   = (int) ($arrow['rows'][count($arrow['rows']) - 1]['id'] ?? 0);

            $nearestPos = array_search($nearestId, $checkpointOrder);
            $finalPos   = array_search($finalId, $checkpointOrder);

            if ($nearestPos === false || $finalPos === false) {
                $arrow['available_midpoints'] = [];
                continue;
            }

            $start = min($nearestPos, $finalPos);
            $end   = max($nearestPos, $finalPos);

            $midpoints = [];
            for ($k = $start + 1; $k < $end; $k++) {
                $midId = $checkpointOrder[$k];

                // Esclude nearest e final: già fissi nel cartello
                if ($midId === $nearestId || $midId === $finalId) {
                    continue;
                }
                // Solo checkpoint attivi
                if (!isset($activeSet[$midId])) {
                    continue;
                }

                $midData = $arrow['midpoints_data'][(string) $midId] ?? null;
                if (!$midData) {
                    continue;
                }

                $pole        = $poleNames->get($midId);
                $midpoints[] = array_merge([
                    'id'          => $midId,
                    'name'        => $pole?->name ?? '',
                    'ref'         => $pole?->ref ?? '',
                    'description' => '',
                ], $midData);
            }

            $arrow['available_midpoints'] = $midpoints;
        }
        unset($arrow);
    }
    unset($routeData);

    return $signageData;
}
```

**Verifica Phase 1:**
- Tinker: `Poles::find($id)->properties['signage'][$routeId]['arrows'][0]` → deve avere `midpoints_data`
- Tinker: `HikingRoute::find($id)->properties['signage']['checkpoint_order']` → array ordinato
- Testare `resolveAttribute()` → deve restituire `available_midpoints` calcolati freschi, senza nearest e final

---

## Phase 2: Backend — Nuovo endpoint `updateArrowMidpoint()`

### 2.1 Route

**File:** `nova-components/SignageMap/src/Routes/api.php`

Aggiungi dopo riga 23:
```php
Route::patch('/pole/{poleId}/arrow-midpoint', [SignageMapController::class, 'updateArrowMidpoint'])
    ->name('signage-map.update-arrow-midpoint');
```

### 2.2 Metodo controller

**File:** `SignageMapController.php`

```php
/**
 * Aggiorna la meta intermedia (rows[1]) di una freccia.
 * Valida selected_pole_id a runtime contro checkpoint_order della HikingRoute.
 * Persiste selected_midpoint_id nell'arrow.
 *
 * Body: { hiking_route_id: int, arrow_index: int, selected_pole_id: int }
 */
public function updateArrowMidpoint(Request $request, int $poleId): JsonResponse
{
    $validated = $request->validate([
        'hiking_route_id'  => 'required|integer',
        'arrow_index'      => 'required|integer|min:0',
        'selected_pole_id' => 'required|integer',
    ]);

    $pole             = Poles::findOrFail($poleId);
    $poleProperties   = $pole->properties ?? [];
    $hikingRouteIdStr = (string) $validated['hiking_route_id'];
    $arrowIndex       = (int) $validated['arrow_index'];
    $selectedPoleId   = (int) $validated['selected_pole_id'];

    if (!isset($poleProperties['signage'][$hikingRouteIdStr]['arrows'][$arrowIndex])) {
        return response()->json(['error' => 'Arrow not found'], 404);
    }

    $arrow = &$poleProperties['signage'][$hikingRouteIdStr]['arrows'][$arrowIndex];
    $rows  = $arrow['rows'] ?? [];

    if (count($rows) < 3) {
        return response()->json(['error' => 'Arrow has no midpoint slot'], 422);
    }

    // Validazione runtime: selected_pole_id deve essere checkpoint attivo tra nearest e final
    $hikingRoute = HikingRoute::find((int) $hikingRouteIdStr);
    if (!$hikingRoute) {
        return response()->json(['error' => 'HikingRoute not found'], 404);
    }

    $hrSignage         = $hikingRoute->properties['signage'] ?? [];
    $checkpointOrder   = array_map('intval', $hrSignage['checkpoint_order'] ?? []);
    $activeCheckpoints = array_map('intval', $hrSignage['checkpoint'] ?? []);
    $activeSet         = array_flip($activeCheckpoints);

    $nearestId   = (int) ($rows[0]['id'] ?? 0);
    $finalId     = (int) ($rows[count($rows) - 1]['id'] ?? 0);
    $nearestPos  = array_search($nearestId, $checkpointOrder);
    $finalPos    = array_search($finalId, $checkpointOrder);
    $selectedPos = array_search($selectedPoleId, $checkpointOrder);

    $start = min($nearestPos, $finalPos);
    $end   = max($nearestPos, $finalPos);

    $isValid = $selectedPos !== false
        && $selectedPos > $start
        && $selectedPos < $end
        && isset($activeSet[$selectedPoleId])
        && $selectedPoleId !== $nearestId
        && $selectedPoleId !== $finalId;

    if (!$isValid) {
        return response()->json(['error' => 'Selected pole is not a valid intermediate checkpoint'], 422);
    }

    // Recupera time/distance da midpoints_data (già calcolati in processPointDirections)
    $midData      = $arrow['midpoints_data'][(string) $selectedPoleId] ?? [];
    $selectedPole = Poles::find($selectedPoleId);

    $arrow['rows'][1] = array_merge([
        'id'          => $selectedPoleId,
        'name'        => $selectedPole?->name ?? '',
        'ref'         => $selectedPole?->ref ?? '',
        'description' => '',
    ], $midData);

    $arrow['selected_midpoint_id'] = $selectedPoleId;

    $pole->properties = $poleProperties;
    $pole->saveQuietly();

    return response()->json(['success' => true, 'arrow' => $arrow]);
}
```

**Verifica Phase 2:**
- 200: `selected_pole_id` è checkpoint attivo tra nearest e final
- 422: `selected_pole_id` è nearest, final, non checkpoint, o fuori range
- 404: arrow o hikingRoute non trovati

---

## Phase 3: Frontend — Select in `SignageArrowsDisplay.vue`

### 3.1 Template — rows[1] diventa select

Sostituisci il `v-for` destinazioni (righe 47-62) con:

```vue
<div v-for="(destination, idx) in arrow.rows"
    :key="idx"
    class="destination-row"
    :class="{ 'destination-row--with-separator': idx < arrow.rows.length - 1 }">

    <!-- rows[1] con 3 destinazioni: select per i midpoint intermedi -->
    <template v-if="idx === 1 && arrow.rows.length === 3">
        <span class="destination-info destination-info--select">
            <select
                class="midpoint-select"
                :disabled="!arrow.available_midpoints || arrow.available_midpoints.length === 0"
                :value="destination.id"
                @change="onMidpointChange($event, routeId, arrow, arrowIdx)"
            >
                <!--
                  Le opzioni vengono da available_midpoints in ordine naturale
                  (primo = checkpoint originale calcolato da processPointDirections).
                  available_midpoints include sempre il valore corrente (destination.id),
                  quindi :value lo seleziona correttamente anche dopo un override utente.
                -->
                <option
                    v-for="midpoint in (arrow.available_midpoints || [])"
                    :key="midpoint.id"
                    :value="midpoint.id"
                >
                    {{ formatDestinationName(midpoint) }}
                </option>
            </select>
        </span>
        <span class="destination-meta">
            <span class="destination-time">h {{ formatTime(destination?.time_hiking) }}</span>
            <span v-if="destination?.distance" class="destination-distance">
                km {{ formatDistance(destination?.distance) }}
            </span>
        </span>
    </template>

    <!-- Tutte le altre righe: testo normale -->
    <template v-else>
        <span class="destination-info">
            <span class="destination-name">{{ formatDestinationName(destination) }}</span>
            <span v-if="destination?.description" class="destination-description">{{
                destination?.description }}</span>
        </span>
        <span class="destination-meta">
            <span class="destination-time">h {{ formatTime(destination?.time_hiking) }}</span>
            <span v-if="destination?.distance" class="destination-distance">
                km {{ formatDistance(destination?.distance) }}
            </span>
        </span>
    </template>
</div>
```

### 3.2 Metodo `onMidpointChange`

Aggiungi nei `methods` dopo `closeConfirmModal()`:

```javascript
onMidpointChange(event, routeId, arrow, arrowIdx) {
    const selectedPoleId = parseInt(event.target.value, 10);
    const originalArrowKey = arrow.__arrowKey || `${routeId}-${arrowIdx}`;
    const originalIndex = parseInt((originalArrowKey.split('-')[1] ?? arrowIdx), 10);

    this.$emit('arrow-midpoint-changed', {
        routeId: routeId,
        arrowIndex: originalIndex,
        selectedPoleId: selectedPoleId,
    });
},
```

### 3.3 CSS

Aggiungi dopo `.destination-description` (riga ~785):

```css
.destination-info--select {
    flex: 1;
    min-width: 0;
    max-width: 180px;
}

.midpoint-select {
    width: 100%;
    font-family: 'Arial', sans-serif;
    font-weight: 600;
    font-size: 13px;
    color: #000000;
    background: #FFFFFF;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    padding: 2px 4px;
    cursor: pointer;
}

.midpoint-select:disabled {
    background: transparent;
    border: none;
    cursor: default;
    appearance: none;
    font-size: 15px;
    padding: 0;
    font-weight: 600;
}
```

---

## Phase 4: Frontend — Handler in `DetailField.vue`

**Leggi `DetailField.vue` prima di modificarlo.** Replica il pattern usato per `arrow-direction-changed`.

1. Sul componente `<signage-arrows-display>`: aggiungi `@arrow-midpoint-changed="handleMidpointChange"`
2. Metodo (adatta `poleId` al pattern già usato in `handleArrowDirectionChanged`):

```javascript
handleMidpointChange({ routeId, arrowIndex, selectedPoleId }) {
    const poleId = /* stesso pattern di handleArrowDirectionChanged */;

    Nova.request()
        .patch(`/nova-vendor/signage-map/pole/${poleId}/arrow-midpoint`, {
            hiking_route_id: parseInt(routeId, 10),
            arrow_index: arrowIndex,
            selected_pole_id: selectedPoleId,
        })
        .then(() => {
            Nova.success('Meta intermedia aggiornata');
        })
        .catch(error => {
            Nova.error('Errore nel salvataggio');
            console.error(error);
        });
},
```

---

## Phase 5: Build e test end-to-end

```bash
cd nova-components/SignageArrows && npm run build
cd nova-components/SignageMap && npm run build
```

Test manuale:
1. Aprire un Pole con signage → seconda meta deve essere `<select>`
2. Se nessun checkpoint intermedio → select disabled con valore attuale
3. Selezionare opzione → PATCH 200 su `/arrow-midpoint`
4. Ricaricare pagina → select mostra il valore salvato
5. Aggiungere checkpoint tra nearest e final → la select aggiorna le opzioni
6. Rimuovere checkpoint selezionato → la select torna al default

---

## Anti-pattern da evitare

- NON salvare `available_midpoints` nel DB — calcolati a runtime in `resolveAttribute()`
- NON includere `rows[0]` (nearest) e `rows[last]` (final) in `available_midpoints`
- NON validare `selected_pole_id` dai dati salvati — sempre rivalidare da `checkpoint_order` della HikingRoute
- NON usare `save()` invece di `saveQuietly()` sui Pole e sulla HikingRoute
- NON modificare `rows[0]` e `rows[last]` — sono immutabili

---

## Ordine di esecuzione

1. **Phase 1a** — `processPointDirections()`: salva `checkpoint_order` + `midpoints_data`
2. **Phase 1b** — `SignageArrows.php`: calcolo runtime `available_midpoints`
3. **Phase 2** — Endpoint `updateArrowMidpoint()`
4. **Phase 3** — Vue select
5. **Phase 4** — DetailField handler
6. **Phase 5** — Build + test
