> Ticket: oc:8197

# Fix propagazione sync WMFE/PBF su figli SiHikingRoute Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Far propagare la sync WMFE (e, se serve, PBF) alle SiHikingRoute figlie quando `HikingRouteObserver::syncOsm2caiStatusToChildSiHikingRoutes()` le aggiorna con `saveQuietly()`, così i figli non restano desincronizzati dopo che il parent cambia `osm2cai_status`/`validator_id`/`validation_date`.

**Architecture:** `saveQuietly()` sui figli resta invariato (evita di alterare altri side-effect dell'observer sui figli), ma subito dopo ogni `saveQuietly()` di un figlio effettivamente cambiato si dispatcha esplicitamente `UpdateEcTrackAwsJob` (sempre) e la rigenerazione PBF via `updatePbfsForHikingRoute()` (solo se il figlio ha anche `wasChanged('geometry')`). Nessuna nuova classe: una sola modifica mirata dentro `HikingRouteObserver`.

**Tech Stack:** Laravel 11, PHPUnit (`php artisan test`), `Queue::fake()`.

## Global Constraints

- Non introdurre `EcTrackSyncService` o altre astrazioni: modifica mirata al solo metodo indicato.
- Non toccare altri path `saveQuietly()`/`updateQuietly()` (sync osmfeatures bulk, `ForceUpdateOsmFeaturesFromTo`, SignageMap, cleanup SI) — fuori scope.
- Non modificare i dati storici già desincronizzati (tracce `29360`, `29369`, ecc.) — gestiti dal ticket parallelo oc:8200.
- Nessun commit/branch automatico: ogni commit indicato nel piano è un'istruzione testuale per lo sviluppatore, da eseguire solo dopo revisione.

---

## Task 1: Fix propagazione sync nei figli SiHikingRoute

**Files:**
- Modify: `app/Observers/HikingRouteObserver.php:132-152` (metodo `syncOsm2caiStatusToChildSiHikingRoutes`)
- Test: `tests/Unit/Observers/HikingRouteObserverTest.php` (nuovo file)

**Interfaces:**
- Consumes: `Wm\WmPackage\Jobs\Track\UpdateEcTrackAwsJob` (già importato in cima al file, usato in `saved()` come `UpdateEcTrackAwsJob::dispatch($hikingRoute)`), `HikingRouteObserver::updatePbfsForHikingRoute($hikingRoute): void` (metodo privato già esistente nello stesso file, righe 36-73), `HikingRoute::childHikingRoutes(): HasMany` (relazione già esistente in `app/Models/HikingRoute.php:435-438`).
- Produces: nessuna nuova interfaccia pubblica — il comportamento osservabile è: dopo il salvataggio di un parent con cambio di `osm2cai_status`/`validator_id`/`validation_date`, ogni figlio con quei campi effettivamente cambiati riceve un dispatch di `UpdateEcTrackAwsJob` (sempre) e di `updatePbfsForHikingRoute` (solo se il figlio ha anche `wasChanged('geometry')`).

- [ ] **Step 1: Scrivere il test che fallisce**

Crea `tests/Unit/Observers/HikingRouteObserverTest.php`:

```php
<?php

namespace Tests\Unit\Observers;

use App\Models\HikingRoute;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Wm\WmPackage\Jobs\Track\UpdateEcTrackAwsJob;

class HikingRouteObserverTest extends TestCase
{
    /** @test */
    public function it_dispatches_update_ec_track_aws_job_for_child_when_parent_status_changes()
    {
        Queue::fake();

        $parent = HikingRoute::factory()->create([
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
        ]);

        $child = HikingRoute::factory()->create([
            'parent_hiking_route_id' => $parent->id,
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
        ]);

        Queue::fake();

        $parent->osm2cai_status = 4;
        $parent->save();

        Queue::assertPushed(UpdateEcTrackAwsJob::class, function ($job) use ($child) {
            return $job->ecTrack->id === $child->id;
        });
    }

    /** @test */
    public function it_does_not_dispatch_pbf_regeneration_for_child_when_only_status_changes()
    {
        Queue::fake();

        $parent = HikingRoute::factory()->create([
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
        ]);

        $child = HikingRoute::factory()->create([
            'parent_hiking_route_id' => $parent->id,
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
        ]);

        Queue::fake();

        $parent->osm2cai_status = 4;
        $parent->save();

        Queue::assertNotPushed(\Illuminate\Bus\PendingBatch::class);
        Queue::assertNothingPushed();
    }
}
```

Nota: il secondo test usa `Queue::assertNothingPushed()` solo per la parte PBF — dato che `UpdateEcTrackAwsJob` viene dispatchato comunque, questo secondo test così scritto fallirebbe anche sull'assert corretto. Sostituiscilo con un controllo più preciso al Step 3 (verrà corretto lì sotto, vedi Step 3b).

- [ ] **Step 2: Eseguire il test e verificare che falisca**

Run: `docker exec -it php-osm2cai2 php artisan test tests/Unit/Observers/HikingRouteObserverTest.php`
Expected: FAIL — `it_dispatches_update_ec_track_aws_job_for_child_when_parent_status_changes` fallisce perché `UpdateEcTrackAwsJob` non viene dispatchato per il figlio (viene dispatchato solo per il parent).

- [ ] **Step 3: Correggere il secondo test per un assert preciso**

Il secondo test verifica che *non* venga generato un batch di rigenerazione PBF per il figlio quando cambia solo lo status (nessun cambio di geometria sul figlio). Sostituisci il corpo del secondo test con:

```php
    /** @test */
    public function it_does_not_dispatch_pbf_batch_for_child_when_only_status_changes()
    {
        Queue::fake();

        $parent = HikingRoute::factory()->create([
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
        ]);

        $child = HikingRoute::factory()->create([
            'parent_hiking_route_id' => $parent->id,
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
        ]);

        Queue::fake();

        $parent->osm2cai_status = 4;
        $parent->save();

        \Illuminate\Support\Facades\Bus::assertNothingBatched();
    }
```

Aggiungi in cima al file l'uso di `Illuminate\Support\Facades\Bus` (o mantieni il namespace completo come sopra) e aggiungi `Bus::fake();` subito dopo `Queue::fake();` nel setup di entrambi i test:

```php
        Queue::fake();
        \Illuminate\Support\Facades\Bus::fake();
```

Aggiorna anche il primo test aggiungendo `\Illuminate\Support\Facades\Bus::fake();` dopo il secondo `Queue::fake();`, per coerenza (non è strettamente necessario per l'asserzione ma evita che un batch reale venga dispatchato durante il test).

- [ ] **Step 4: Eseguire di nuovo il test e verificare che falisca in modo coerente**

Run: `docker exec -it php-osm2cai2 php artisan test tests/Unit/Observers/HikingRouteObserverTest.php`
Expected: FAIL — `it_dispatches_update_ec_track_aws_job_for_child_when_parent_status_changes` fallisce (nessun `UpdateEcTrackAwsJob` per il figlio); `it_does_not_dispatch_pbf_batch_for_child_when_only_status_changes` PASSA già (nessun batch viene dispatchato oggi per i figli, quindi questo comportamento è già corretto e serve come test di non-regressione).

- [ ] **Step 5: Implementare il fix minimo**

Apri `app/Observers/HikingRouteObserver.php` e modifica il metodo `syncOsm2caiStatusToChildSiHikingRoutes` (righe 132-152) da:

```php
    private function syncOsm2caiStatusToChildSiHikingRoutes($hikingRoute): void
    {
        $newStatus = $hikingRoute->osm2cai_status;
        $newValidatorId = $hikingRoute->validator_id;
        $newValidationDate = $hikingRoute->validation_date;
        $children = $hikingRoute->childHikingRoutes()->get();

        foreach ($children as $child) {
            $changed = $child->osm2cai_status !== $newStatus
                || $child->validator_id !== $newValidatorId
                || $child->validation_date?->format('Y-m-d') !== $newValidationDate?->format('Y-m-d');
            if (! $changed) {
                continue;
            }
            $child->osm2cai_status = $newStatus;
            $child->validator_id = $newValidatorId;
            $child->validation_date = $newValidationDate;

            $child->saveQuietly();
        }
    }
```

a:

```php
    private function syncOsm2caiStatusToChildSiHikingRoutes($hikingRoute): void
    {
        $newStatus = $hikingRoute->osm2cai_status;
        $newValidatorId = $hikingRoute->validator_id;
        $newValidationDate = $hikingRoute->validation_date;
        $children = $hikingRoute->childHikingRoutes()->get();

        foreach ($children as $child) {
            $changed = $child->osm2cai_status !== $newStatus
                || $child->validator_id !== $newValidatorId
                || $child->validation_date?->format('Y-m-d') !== $newValidationDate?->format('Y-m-d');
            if (! $changed) {
                continue;
            }
            $child->osm2cai_status = $newStatus;
            $child->validator_id = $newValidatorId;
            $child->validation_date = $newValidationDate;

            $child->saveQuietly();

            // saveQuietly() bypassa gli Observer: la sync verso WMFE (e, se serve, PBF)
            // deve essere dispatchata esplicitamente qui. Vedi oc:8197.
            UpdateEcTrackAwsJob::dispatch($child);

            if ($child->wasChanged('geometry')) {
                $this->updatePbfsForHikingRoute($child);
            }
        }
    }
```

- [ ] **Step 6: Eseguire il test e verificare che passi**

Run: `docker exec -it php-osm2cai2 php artisan test tests/Unit/Observers/HikingRouteObserverTest.php`
Expected: PASS — entrambi i test passano.

- [ ] **Step 7: Eseguire l'intera suite Unit per verificare l'assenza di regressioni**

Run: `docker exec -it php-osm2cai2 php artisan test --testsuite=Unit`
Expected: PASS — nessun test esistente rotto dalla modifica.

- [ ] **Step 8: Commit**

```bash
git add app/Observers/HikingRouteObserver.php tests/Unit/Observers/HikingRouteObserverTest.php
git commit -m "fix(oc:8197): dispatch WMFE/PBF sync for children updated via saveQuietly"
```

---

## Self-Review

**Spec coverage:**
- Requisito "dispatchare UpdateEcTrackAwsJob per ogni figlio effettivamente modificato" → Task 1, Step 5.
- Requisito "dispatchare rigenerazione PBF solo se wasChanged('geometry') sul figlio" → Task 1, Step 5 (condizione `if ($child->wasChanged('geometry'))`).
- Requisito "nessuna modifica al comportamento per campi non toccati" → il fix aggiunge solo 2 righe dopo `saveQuietly()`, non toglie/altera nulla di esistente.
- Requisito "test con Queue::fake() che verifica dispatch per i figli" → Task 1, Step 1-4.

**Placeholder scan:** nessun TBD/TODO — tutti gli step hanno codice completo.

**Type consistency:** `UpdateEcTrackAwsJob::dispatch($child)` usa la stessa classe già importata e usata in `saved()` sullo stesso file (`Wm\WmPackage\Jobs\Track\UpdateEcTrackAwsJob`); `updatePbfsForHikingRoute($child)` è lo stesso metodo privato già esistente, stessa firma (`$hikingRoute` generico, qui passato `$child`).
