> Ticket: oc:8893

# API restituiscono dati sensibili — Piano di implementazione

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/api/v2/hiking-route/{id}`, `/api/v2/hiking-route-osm/{osm_id}`, `/api/v2/export/hiking-routes/{id}` e il GeoJSON verso WMFE non pubblicano più i dati personali dei referenti contenuti in `properties.sicai`.

**Architecture:** il modello `HikingRoute` espone un solo metodo pubblico che restituisce `properties` con il blocco `sicai` filtrato, costruito sul filtro già esistente `filterSicaiPropertiesForApi()`. Controller, `ExportResource` e `enhanceHikingRouteProperties()` (usato da `getGeojson()`) passano tutti da lì; il filtro non dipende più da `app_id`. Il DB non viene toccato.

**Tech Stack:** Laravel 11, PHP 8.4, PHPUnit su PostgreSQL/PostGIS reale (`DatabaseTransactions`), container `php-osm2cai2`.

**Spec:** [overview.md](overview.md)

## Global Constraints

- Repo unico: `osm2cai2`. Nessuna modifica a `wm-package/`, `wm-osmfeatures/`, `wm-internal/`.
- Campi da nascondere (lista unica, invariata rispetto a oggi): `sicai.referente.name`, `sicai.referente.email` (se `referente` resta vuoto la chiave sparisce), `sicai.referente_regionale`, `sicai.email_ref_regionale`, `sicai.sezione_ref_regionale`, `sicai.sezioni_manutenzione`.
- Campi `sicai` da lasciare visibili: tutti gli altri (es. `sezione`, `percorribilità`, `segnaletica`).
- Nessuna condizione su `app_id` per il filtro.
- `hiking_routes.properties` nel DB resta intatto; Nova non cambia.
- Fuori scope: route `ec/track/*` di wm-package, `/api/geojson-complete/*`, export utenti, rigenerazione file WMFE, tappe duplicate.
- Commit: messaggi `fix(oc:8893): ...`. **I commit sono istruzioni per il dev: non eseguire `git add`/`git commit`/`git push`.**
- Test: `docker exec php-osm2cai2 php artisan test --filter=<Nome>`.

## Review Focus

- `properties` nullo o vuoto: il metodo restituisce `[]` senza errori e gli endpoint rispondono 200 come oggi — coperto in Task 1.
- `properties` senza blocco `sicai`: restituito identico — coperto in Task 1.
- `sicai.referente` con chiavi extra oltre a `name`/`email`: le chiavi extra restano — coperto in Task 1.
- Percorso con `app_id` 1 e blocco `sicai`: filtrato anche nel GeoJSON — coperto in Task 3.
- Altre chiavi di `properties` fuori da `sicai` (es. `manual_data`, `dem_data`): restano invariate negli endpoint — coperto in Task 1 (chiave di controllo `altro`).

---

### Task 1: Metodo unico `getPublicProperties()` nel modello

**Files:**
- Modify: `app/Models/HikingRoute.php` (metodo `filterSicaiPropertiesForApi`, ~riga 1346)
- Test: `tests/Unit/Models/HikingRoutePublicPropertiesTest.php` (nuovo)

**Interfaces:**
- Produces: `HikingRoute::getPublicProperties(): array` — `properties` del modello con `sicai` passato da `filterSicaiPropertiesForApi()`; `[]` se `properties` non è un array.
- Produces: `filterSicaiPropertiesForApi(mixed $sicai): mixed` resta `private` (unico uso: `getPublicProperties` e `enhanceHikingRouteProperties`).

- [ ] **Step 1: Scrivi i test** (classe `Tests\Unit\Models\HikingRoutePublicPropertiesTest extends Tests\TestCase`, modello istanziato con `new HikingRoute()` senza DB, come in `HikingRouteGeojsonDescriptionTest`)

```php
private function sicaiCompleto(): array
{
    return [
        'referente' => ['name' => 'Mario Rossi', 'email' => 'mario@example.com'],
        'referente_regionale' => 'Luigi Verdi',
        'email_ref_regionale' => 'luigi@example.com',
        'sezione_ref_regionale' => 'Sez. Test',
        'sezioni_manutenzione' => 'Sez. A, Sez. B',
        'sezione' => 'Sez. Tappa',
        'percorribilità' => 'Percorribile',
        'segnaletica' => 'Buona',
    ];
}

public function test_rimuove_i_campi_sensibili_e_conserva_gli_altri(): void
{
    $route = new HikingRoute();
    $route->properties = ['sicai' => $this->sicaiCompleto(), 'altro' => 'x'];

    $out = $route->getPublicProperties();

    $this->assertArrayNotHasKey('referente', $out['sicai']);
    foreach (['referente_regionale', 'email_ref_regionale', 'sezione_ref_regionale', 'sezioni_manutenzione'] as $k) {
        $this->assertArrayNotHasKey($k, $out['sicai']);
    }
    $this->assertSame('Sez. Tappa', $out['sicai']['sezione']);
    $this->assertSame('Percorribile', $out['sicai']['percorribilità']);
    $this->assertSame('Buona', $out['sicai']['segnaletica']);
    $this->assertSame('x', $out['altro']);
}

public function test_conserva_le_chiavi_extra_di_referente(): void
{
    $route = new HikingRoute();
    $route->properties = ['sicai' => ['referente' => ['name' => 'M', 'email' => 'e', 'ruolo' => 'R']]];

    $this->assertSame(['ruolo' => 'R'], $route->getPublicProperties()['sicai']['referente']);
}

public function test_properties_senza_sicai_restano_identiche(): void
{
    $route = new HikingRoute();
    $route->properties = ['altro' => 'x'];

    $this->assertSame(['altro' => 'x'], $route->getPublicProperties());
}

public function test_properties_nulle_restituiscono_array_vuoto(): void
{
    $route = new HikingRoute();
    $route->properties = null;

    $this->assertSame([], $route->getPublicProperties());
}

public function test_non_modifica_le_properties_del_modello(): void
{
    $route = new HikingRoute();
    $route->properties = ['sicai' => $this->sicaiCompleto()];

    $route->getPublicProperties();

    $this->assertSame('mario@example.com', $route->properties['sicai']['referente']['email']);
}
```

- [ ] **Step 2: Esegui i test, devono fallire**

Run: `docker exec php-osm2cai2 php artisan test --filter=HikingRoutePublicPropertiesTest`
Expected: FAIL con `Call to undefined method ... getPublicProperties()`

- [ ] **Step 3: Implementa `public function getPublicProperties(): array` in `app/Models/HikingRoute.php`**, accanto a `filterSicaiPropertiesForApi`. Copia di `$this->properties`; se contiene `sicai` lo sostituisce con `filterSicaiPropertiesForApi(...)`. Docblock di una riga: è l'unico punto da cui i canali pubblici leggono `properties`.

- [ ] **Step 4: Esegui i test, devono passare**

Run: `docker exec php-osm2cai2 php artisan test --filter=HikingRoutePublicPropertiesTest`
Expected: PASS (5 test)

- [ ] **Step 5: Commit (istruzione per il dev)**

```bash
git add app/Models/HikingRoute.php tests/Unit/Models/HikingRoutePublicPropertiesTest.php
git commit -m "fix(oc:8893): add HikingRoute::getPublicProperties filtering sicai personal data"
```

---

### Task 2: Endpoint API usano `getPublicProperties()`

**Files:**
- Modify: `app/Http/Controllers/HikingRouteController.php:905-911` (`buildHikingRouteResponse`)
- Modify: `app/Http/Resources/ExportResource.php` (case `HikingRoute`, ~riga 39)
- Test: `tests/Api/HikingRouteControllerV2Test.php`, `tests/Api/ExportControllerTest.php`

**Interfaces:**
- Consumes: `HikingRoute::getPublicProperties(): array` (Task 1)

- [ ] **Step 1: Scrivi i test Feature**

In `HikingRouteControllerV2Test` aggiungi un helper che, dopo `createTestHikingRoute(...)`, imposta `properties` con lo stesso blocco `sicai` del Task 1 (via `updateQuietly(['properties' => [...]])`) e i test:

```php
public function test_hiking_route_show_does_not_expose_sicai_personal_data(): void
{
    $hikingRoute = $this->createTestHikingRouteWithSicai(1, 12345, 3);

    $sicai = $this->get('/api/v2/hiking-route/'.$hikingRoute->id)
        ->assertStatus(200)
        ->json('properties.sicai');

    $this->assertArrayNotHasKey('referente', $sicai);
    $this->assertArrayNotHasKey('referente_regionale', $sicai);
    $this->assertArrayNotHasKey('email_ref_regionale', $sicai);
    $this->assertArrayNotHasKey('sezione_ref_regionale', $sicai);
    $this->assertArrayNotHasKey('sezioni_manutenzione', $sicai);
    $this->assertSame('Sez. Tappa', $sicai['sezione']);
    $this->assertSame('Percorribile', $sicai['percorribilità']);
    $this->assertSame('Buona', $sicai['segnaletica']);
}

public function test_hiking_route_show_by_osm_id_does_not_expose_sicai_personal_data(): void
// stesse asserzioni su GET /api/v2/hiking-route-osm/12345
```

In `ExportControllerTest` aggiungi:

```php
public function test_hiking_route_single_feature_export_does_not_expose_sicai_personal_data(): void
// HikingRoute::factory()->createQuietly([... come test_single_feature_endpoint ..., 'properties' => ['sicai' => <blocco del Task 1>]])
// GET /api/v2/export/hiking-routes/{id} → 200; json('properties.sicai') con le stesse asserzioni del test show
```

Ogni test verifica anche che la risposta grezza non contenga le email: `$this->assertStringNotContainsString('mario@example.com', $response->getContent())` e idem per `luigi@example.com`.

- [ ] **Step 2: Esegui i test, devono fallire**

Run: `docker exec php-osm2cai2 php artisan test --filter='sicai_personal_data'`
Expected: FAIL (3 test) — `referente` presente nella risposta.

- [ ] **Step 3: In `buildHikingRouteResponse`** sostituisci `$hikingRoute->properties` con `$hikingRoute->getPublicProperties()` nel merge (condizione: risultato non vuoto). Aggiorna il commento: i dati personali di `sicai` sono esclusi.

- [ ] **Step 4: In `ExportResource::toArray`, case `HikingRoute`** aggiungi `$result['properties'] = $this->resource->getPublicProperties();`.

- [ ] **Step 5: Esegui i test, devono passare, e la suite delle due classi deve restare verde**

Run: `docker exec php-osm2cai2 php artisan test --filter='HikingRouteControllerV2Test|ExportControllerTest'`
Expected: PASS, nessuna regressione sui test esistenti.

- [ ] **Step 6: Verifica manuale in locale**

Run: `curl -s http://localhost:8008/api/v2/hiking-route/29194 | grep -o '"email[^,]*'` e lo stesso su `/api/v2/export/hiking-routes/29194`
Expected: nessun output. `curl -s http://localhost:8008/api/v2/hiking-route/29194 | grep -o '"sezione":"[^"]*"'` restituisce la sezione.

- [ ] **Step 7: Commit (istruzione per il dev)**

```bash
git add app/Http/Controllers/HikingRouteController.php app/Http/Resources/ExportResource.php tests/Api/HikingRouteControllerV2Test.php tests/Api/ExportControllerTest.php
git commit -m "fix(oc:8893): hide sicai personal data from hiking-route and export APIs"
```

---

### Task 3: GeoJSON verso WMFE filtrato senza condizione su `app_id`

**Files:**
- Modify: `app/Models/HikingRoute.php` (`enhanceHikingRouteProperties`, ~righe 1310-1319)
- Test: `tests/Unit/Models/HikingRouteGeojsonDescriptionTest.php`

**Interfaces:**
- Consumes: `filterSicaiPropertiesForApi()` (invariato)

- [ ] **Step 1: Scrivi il test** (usa l'helper `callEnhanceHikingRouteProperties` già presente nella classe)

```php
public function test_filters_sicai_personal_data_regardless_of_app_id(): void
{
    $route = new HikingRoute();
    $route->app_id = 1;
    $route->properties = ['sicai' => ['referente' => ['name' => 'M', 'email' => 'm@example.com'], 'email_ref_regionale' => 'r@example.com', 'sezione' => 'S']];

    $out = $this->callEnhanceHikingRouteProperties($route, [
        'type' => 'Feature',
        'properties' => ['sicai' => $route->properties['sicai']],
        'geometry' => null,
    ]);

    $this->assertArrayNotHasKey('referente', $out['properties']['sicai']);
    $this->assertArrayNotHasKey('email_ref_regionale', $out['properties']['sicai']);
    $this->assertSame('S', $out['properties']['sicai']['sezione']);
}
```

- [ ] **Step 2: Esegui il test, deve fallire**

Run: `docker exec php-osm2cai2 php artisan test --filter=test_filters_sicai_personal_data_regardless_of_app_id`
Expected: FAIL — `referente` presente (oggi il filtro è dentro `if ((int) $this->app_id === 2)`).

- [ ] **Step 3: Sposta il blocco `if (isset($baseGeojson['properties']['sicai'])) { ... filterSicaiPropertiesForApi ... }` fuori dall'`if` su `app_id`**, subito prima di esso. La scelta di `$resource` resta legata ad `app_id`.

- [ ] **Step 4: Esegui l'intera classe di test**

Run: `docker exec php-osm2cai2 php artisan test --filter=HikingRouteGeojsonDescriptionTest`
Expected: PASS, test esistenti compresi.

- [ ] **Step 5: Formattazione e suite completa**

Run: `docker exec php-osm2cai2 ./vendor/bin/pint --dirty` poi `docker exec php-osm2cai2 php artisan test`
Expected: Pint senza errori; la suite non ha fallimenti nuovi rispetto a `develop` (confronta con un run sul branch di partenza se ci sono fallimenti preesistenti).

- [ ] **Step 6: Commit (istruzione per il dev)**

```bash
git add app/Models/HikingRoute.php tests/Unit/Models/HikingRouteGeojsonDescriptionTest.php
git commit -m "fix(oc:8893): filter sicai personal data in geojson regardless of app_id"
```
