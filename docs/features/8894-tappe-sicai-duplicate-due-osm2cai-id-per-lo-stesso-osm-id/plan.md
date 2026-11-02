> Ticket: oc:8894

# Tappe SICAI con lo stesso osm_id — Piano di implementazione

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** tre endpoint v2 restituiscono solo record `app_id = 1` (sentieri), mai le tappe SI (`app_id = 2`) che condividono lo stesso osm_id.

**Architecture:** un `where('app_id', 1)` scritto a mano in tre query, come già fanno gli altri metodi di `HikingRouteController` (righe 52, 130, 204, 282, 352). Nessuno scope, nessun `orderBy`. TDD: i test nuovi devono fallire sul codice attuale prima del fix.

**Tech Stack:** Laravel 11, PHP 8.4, PostgreSQL/PostGIS, PHPUnit (`php artisan test`).

**Spec:** [overview.md](overview.md)

## Global Constraints

- Repo: solo osm2cai2, nessun submodule toccato.
- Solo v2: `app/Http/Controllers/V1/` **non** va modificato.
- Nessun `orderBy`, nessuno scope sul modello, nessun 404 nuovo su `/api/v2/ecpois/osm/{osm_id}/{type}`.
- Comandi sempre nel container: `docker exec php-osm2cai2 php artisan test <file>`, `docker exec php-osm2cai2 ./vendor/bin/pint <file>`.
- I test girano sul DB locale reale con `DatabaseTransactions`: id e osm_id fittizi (`999000001`, `999000002`…), geometrie nella zona dell'helper (`LINESTRING(10 10, 20 20)`, fuori dall'Italia, dove non ci sono dati), record creati con `createQuietly` per non far partire observer e job WMFE.
- La tappa SI si crea **prima** del sentiero e **senza** `parent_hiking_route_id`: la FK `hiking_routes_parent_hiking_route_id_foreign` non è differibile, e assegnare il parent dopo con un `UPDATE` sposterebbe fisicamente la riga della tappa in fondo alla tabella, facendo uscire il sentiero anche col codice vecchio. Il fix filtra per `app_id`, non per parent, quindi il parent non serve al test.
- Commit: `fix(oc:8894): ...`. I commit sono istruzioni per il dev: nessun `git add`/`git commit` durante l'esecuzione.

## Review Focus

- Il test passa già prima del fix → non dimostra niente: lo step «verifica che fallisca» è obbligatorio in ogni task; se un test nuovo passa sul codice attuale, fermarsi e segnalarlo invece di proseguire.
- Il test sulla collection non può usare `relation_id` (oggi è sempre `null`, vedi notes.md): le asserzioni usano `properties.id`.
- Un osm_id presente solo su una tappa SI: dopo il fix `/hiking-route-osm` dà 404 — coperto dal test `..._ignores_si_stage_without_trail` del Task 1.
- La v1 non deve cambiare comportamento: verificato con `git diff --stat` che non tocchi `app/Http/Controllers/V1/`.
- I test esistenti devono restare verdi (22 su 22 in `HikingRouteControllerV2Test` al 02/11).

---

### Task 1: `/api/v2/hiking-route-osm/{osm_id}` restituisce solo il sentiero

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-helper-e-id-fittizi)

**Files:**
- Modify: `tests/Api/HikingRouteControllerV2Test.php` — helper `createTestHikingRoute` (riga 30) e test nuovi
- Modify: `app/Http/Controllers/HikingRouteController.php:795` (`showByOsmId`)

**Interfaces:**
- Produces: helper `createTestHikingRoute($id, $osm_id, $status, $geometry = null, int $appId = 1)` — il nuovo parametro finale imposta `'app_id' => $appId` nel `createQuietly`. Le chiamate esistenti restano invariate. Usato dai Task 2.

- [ ] **Step 1: Estendi l'helper** con il parametro `int $appId = 1` e la chiave `'app_id' => $appId`.

- [ ] **Step 2: Scrivi i test che falliscono**

```php
public function test_hiking_route_show_by_osm_id_returns_trail_not_si_stage()
{
    $this->createTestHikingRoute(999000002, 999000001, 4, null, 2); // tappa SI, prima
    $this->createTestHikingRoute(999000003, 999000001, 4);          // sentiero

    $response = $this->get('/api/v2/hiking-route-osm/999000001');

    $response->assertStatus(200)
        ->assertJsonPath('properties.id', 999000003);
}

public function test_hiking_route_show_by_osm_id_ignores_si_stage_without_trail()
{
    $this->createTestHikingRoute(999000004, 999000005, 4, null, 2);

    $this->get('/api/v2/hiking-route-osm/999000005')->assertStatus(404);
}
```

Prima di scriverli controlla in `buildHikingRouteResponse` (`HikingRouteController.php` ~riga 836) il percorso esatto dell'id nella risposta (`properties.id`) e adegua l'asserzione se diverso.

- [ ] **Step 3: Verifica che falliscano**

Run: `docker exec php-osm2cai2 php artisan test tests/Api/HikingRouteControllerV2Test.php --filter=si_stage`
Expected: entrambi FAIL (il primo restituisce `999000002`, il secondo 200). Se il primo passa, fermarsi: l'ordine fisico non riproduce il bug e il test va ripensato.

- [ ] **Step 4: Fix** — in `showByOsmId` aggiungi `->where('app_id', 1)` alla query prima di `->first()`.

- [ ] **Step 5: Verifica** — stesso comando dello Step 3: PASS. Poi l'intero file: `docker exec php-osm2cai2 php artisan test tests/Api/HikingRouteControllerV2Test.php` → tutti verdi (22 esistenti + nuovi).

- [ ] **Step 6: Commit (istruzione per il dev)**

```bash
git add tests/Api/HikingRouteControllerV2Test.php app/Http/Controllers/HikingRouteController.php
git commit -m "fix(oc:8894): hiking-route-osm v2 restituisce solo i sentieri app_id 1"
```

### Task 2: `/api/v2/hiking-routes-collection/bb/{bbox}/{sda}` esclude le tappe SI

**Files:**
- Modify: `tests/Api/HikingRouteControllerV2Test.php`
- Modify: `app/Http/Controllers/HikingRouteController.php:960` (`geojsonByBoundingBox`, query `DB::table('hiking_routes')`)

**Interfaces:**
- Consumes: helper `createTestHikingRoute(..., int $appId = 1)` dal Task 1.

- [ ] **Step 1: Scrivi il test che fallisce**

```php
public function test_hiking_route_collection_by_bounding_box_excludes_si_stages()
{
    $this->createTestHikingRoute(999000012, 999000011, 4, null, 2); // tappa SI
    $this->createTestHikingRoute(999000013, 999000011, 4);          // sentiero

    $response = $this->get('/api/v2/hiking-routes-collection/bb/14.9,14.9,15.1,15.1/4');

    $ids = collect($response->assertStatus(200)->json('features'))->pluck('properties.id');
    $this->assertTrue($ids->contains(999000013));
    $this->assertFalse($ids->contains(999000012));
}
```

Il riquadro `14.9,14.9,15.1,15.1` (area 0,04 < 0,1) interseca la linea dell'helper e non contiene dati reali.

- [ ] **Step 2: Verifica che fallisca** — `docker exec php-osm2cai2 php artisan test tests/Api/HikingRouteControllerV2Test.php --filter=excludes_si_stages` → FAIL su `assertFalse` (la tappa c'è).

- [ ] **Step 3: Fix** — in `geojsonByBoundingBox` aggiungi `->where('app_id', 1)` alla query `DB::table('hiking_routes')`.

- [ ] **Step 4: Verifica** — filtro dello Step 2: PASS; intero file: tutti verdi.

- [ ] **Step 5: Commit (istruzione per il dev)**

```bash
git add tests/Api/HikingRouteControllerV2Test.php app/Http/Controllers/HikingRouteController.php
git commit -m "fix(oc:8894): collection per bbox v2 esclude le tappe SI"
```

### Task 3: `/api/v2/ecpois/osm/{osm_id}/{type}` usa la geometria del sentiero

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3-helper-dei-poi)

**Files:**
- Create: `tests/Api/EcPoiControllerV2Test.php` (namespace `Tests\Api`, `use DatabaseTransactions`, throttling disattivato come in `HikingRouteControllerV2Test::setUp`)
- Modify: `app/Http/Controllers/EcPoiController.php:163` (`indexByBufferFromHikingRouteOsmId`)

**Interfaces:**
- Nessuna dipendenza dagli altri task: il file ha un proprio helper privato `createRoute(int $id, int $osmId, int $appId, string $wkt)` che fa `HikingRoute::createQuietly` con `id`, `app_id`, `osm2cai_status = 4`, `geometry` da `ST_GeomFromText($wkt, 4326)` e `osmfeatures_data = ['properties' => ['osm_id' => $osmId]]`; e `createPoi(string $wkt)` che fa `EcPoi::createQuietly` con `name`, `geometry` e `osmfeatures_data = ['properties' => ['osm_type' => 'N']]`. Verificare in migration/model di `ec_pois` le colonne obbligatorie (`app_id`, `user_id`, …) e valorizzarle.

- [ ] **Step 1: Scrivi il test che fallisce**

Geometrie lontane fra loro: la tappa SI passa per (10 10)–(10.1 10), il sentiero per (12 12)–(12.1 12); un POI a (12.05 12.001), entro 1 km solo dal sentiero.

```php
public function test_ecpois_by_osm_id_uses_trail_geometry_not_si_stage()
{
    $this->createRoute(999000022, 999000021, 2, 'LINESTRING(10 10, 10.1 10)'); // tappa SI, prima
    $this->createRoute(999000023, 999000021, 1, 'LINESTRING(12 12, 12.1 12)'); // sentiero
    $poi = $this->createPoi('POINT(12.05 12.001)');

    $response = $this->get('/api/v2/ecpois/osm/999000021/N');

    $ids = collect($response->assertStatus(200)->json())->pluck('id');
    $this->assertTrue($ids->contains($poi->id));
}
```

- [ ] **Step 2: Verifica che fallisca** — `docker exec php-osm2cai2 php artisan test tests/Api/EcPoiControllerV2Test.php` → FAIL (buffer calcolato sulla tappa, lista vuota).

- [ ] **Step 3: Fix** — in `indexByBufferFromHikingRouteOsmId` aggiungi `->where('app_id', 1)` prima di `->first()`. Il caso «osm_id non trovato» resta com'è (fuori scope).

- [ ] **Step 4: Verifica** — comando dello Step 2: PASS.

- [ ] **Step 5: Commit (istruzione per il dev)**

```bash
git add tests/Api/EcPoiControllerV2Test.php app/Http/Controllers/EcPoiController.php
git commit -m "fix(oc:8894): ecpois per osm_id v2 usa solo i sentieri app_id 1"
```

### Task 4: Verifica finale

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4-pint-e-cleanup-dopo-la-review)

- [ ] **Step 1:** `docker exec php-osm2cai2 ./vendor/bin/pint app/Http/Controllers/HikingRouteController.php app/Http/Controllers/EcPoiController.php tests/Api/HikingRouteControllerV2Test.php tests/Api/EcPoiControllerV2Test.php` → nessun errore.
- [ ] **Step 2:** `docker exec php-osm2cai2 php artisan test tests/Api/HikingRouteControllerV2Test.php tests/Api/EcPoiControllerV2Test.php` → tutti verdi.
- [ ] **Step 3:** `git diff --stat` → nessun file sotto `app/Http/Controllers/V1/`.
- [ ] **Step 4:** prova manuale in locale: `curl -s http://localhost:8008/api/v2/hiking-route-osm/2282464 | jq .properties.id` → `2268` (prima del fix `29651`).
