> Ticket: oc:8620

# Morph class di SiHikingRoute — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** far sì che `SiHikingRoute` e `SiMTBRoute` leggano le relazioni polimorfiche come `HikingRoute`, così il documento Elasticsearch scritto al salvataggio da Nova conserva `layers` e `taxonomyActivities`.

**Architecture:** un solo override di `getMorphClass()` in `app/Models/HikingRoute.php`, che fissa l'identità polimorfica a `App\Models\HikingRoute` per sé e per tutte le sottoclassi. Nessuna migration, nessuna modifica alle relazioni, nessun intervento su `wm-package`. Stesso pattern già in produzione nel repo per i POI (`app/Models/SiPoi.php:61`).

**Tech Stack:** Laravel 11, PHP 8.4, Laravel Nova 5, Laravel Scout + Elasticsearch, PostgreSQL/PostGIS, Docker (`php-osm2cai2`).

**Spec:** [overview.md](overview.md)

## Global Constraints

- **Nessuna modifica a `wm-package/`** né a `wm-osmfeatures/`. Verificato che aggiornare il package non risolverebbe il problema: `getMorphClass()` è identico in v1.5.0-19 (in uso) e v1.5.0-147 (la più recente in azienda).
- **Nessuna migration.** Il database contiene già il valore corretto (`App\Models\HikingRoute`) su tutte le righe dei pivot di dominio.
- **Nessun commit o branch eseguito automaticamente.** I comandi `git` in questo piano sono istruzioni per il dev, da eseguire dopo approvazione esplicita.
- **Commit convention:** `fix(oc:8620): ...`
- **PR verso `develop`**, mai verso `main`.
- **Test su PostgreSQL/PostGIS reale** (non SQLite), eseguiti nel container: `docker exec php-osm2cai2 php artisan test --filter=...`
- `SCOUT_QUEUE=true` nel `.env`: ogni test che verifica il documento indicizzato deve forzare `config(['scout.queue' => false])` nel setup, altrimenti l'indicizzazione finisce in coda e l'assert gira a vuoto.
- Le factory del repo hanno `definition()` vuota: ogni campo necessario va passato esplicitamente a `create()`, incluse le geometrie via `DB::raw("ST_GeomFromText('LINESTRINGZ(...)', 4326)")`.

---

## File Structure

| File | Responsabilità |
|---|---|
| `app/Models/HikingRoute.php` | il fix: `getMorphClass()` che fissa `App\Models\HikingRoute` |
| `tests/Unit/Models/HikingRouteMorphClassTest.php` | test delle relazioni e del documento indicizzato per entrambe le sottoclassi |

Un solo file di test: le quattro verifiche riguardano tutte lo stesso comportamento (l'identità polimorfica) e cambiano insieme. Separarle per suite creerebbe due file che si modificano sempre in coppia.

---

### Task 1: Override di `getMorphClass()` in `HikingRoute`

**Files:**
- Modify: `app/Models/HikingRoute.php` (dopo `shouldBeSearchable()`, riga ~133)
- Test: `tests/Unit/Models/HikingRouteMorphClassTest.php`

**Interfaces:**
- Consumes: niente da task precedenti.
- Produces: `HikingRoute::getMorphClass(): string` che ritorna sempre `'App\Models\HikingRoute'`, ereditato da `SiHikingRoute` e `SiMTBRoute`. Il Task 2 si appoggia su questo comportamento.

- [ ] **Step 1: Scrivere il test che fallisce — le relazioni devono coincidere fra le classi**

Crea `tests/Unit/Models/HikingRouteMorphClassTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\HikingRoute;
use App\Models\SiHikingRoute;
use App\Models\SiMTBRoute;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Wm\WmPackage\Models\Layer;

class HikingRouteMorphClassTest extends TestCase
{
    private const GEOM = "ST_GeomFromText('LINESTRINGZ(12.4924 41.8902 0, 12.4925 41.8903 0)', 4326)";

    /**
     * Crea una traccia app_id=2 con un layer associato via pivot polimorfico,
     * scritto con l'identita' che il database usa davvero: App\Models\HikingRoute.
     */
    private function createRouteWithLayer(): array
    {
        $route = HikingRoute::factory()->create([
            'app_id' => 2,
            'osm2cai_status' => 3,
            'geometry' => DB::raw(self::GEOM),
        ]);

        // properties e' NOT NULL senza default sulla tabella layers: va sempre passata
        $layer = Layer::create([
            'name' => 'Layer di test oc:8620',
            'properties' => [],
            'app_id' => 2,
        ]);

        DB::table('layerables')->insert([
            'layer_id' => $layer->id,
            'layerable_type' => 'App\Models\HikingRoute',
            'layerable_id' => $route->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$route, $layer];
    }

    /** @test */
    public function si_hiking_route_sees_the_same_layers_as_hiking_route()
    {
        [$route, $layer] = $this->createRouteWithLayer();

        $asParent = HikingRoute::find($route->id);
        $asChild = SiHikingRoute::find($route->id);

        $this->assertSame(
            $asParent->layers()->pluck('layers.id')->toArray(),
            $asChild->layers()->pluck('layers.id')->toArray(),
            'SiHikingRoute deve vedere gli stessi layer di HikingRoute sullo stesso record'
        );
        $this->assertContains($layer->id, $asChild->layers()->pluck('layers.id')->toArray());
    }
}
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `docker exec php-osm2cai2 php artisan test --filter=si_hiking_route_sees_the_same_layers_as_hiking_route`

Expected: FAIL. `$asChild->layers()` restituisce un array vuoto mentre `$asParent->layers()` contiene l'id del layer, perché la morphToMany filtra su `layerable_type = 'App\Models\SiHikingRoute'`, valore assente dal pivot.

- [ ] **Step 3: Scrivere il fix**

In `app/Models/HikingRoute.php`, subito dopo il metodo `shouldBeSearchable()`:

```php
    /**
     * Usa un'unica identita' polimorfica per tutta la gerarchia.
     *
     * GeometryModel::getMorphClass() (wm-package) compone il morph type come
     * 'App\Models\'.class_basename($this): per le sottoclassi produce
     * 'App\Models\SiHikingRoute' e 'App\Models\SiMTBRoute', valori che nel
     * database non esistono su nessuna riga. I pivot polimorfici
     * (layerables, taxonomy_activityables, media, signage_projectables)
     * contengono solo 'App\Models\HikingRoute', perche' la tabella e' una
     * sola e le sottoclassi sono viste Nova filtrate per app_id.
     *
     * Senza questo override le relazioni tornano vuote quando il record e'
     * caricato da una sottoclasse, e toSearchableArray() indicizza
     * 'layers' => [] su Elasticsearch: la traccia sparisce dal layer (oc:8620).
     *
     * Stesso rimedio gia' adottato per i POI in SiPoi::getMorphClass().
     */
    public function getMorphClass()
    {
        return HikingRoute::class;
    }
```

- [ ] **Step 4: Eseguire il test e verificare che passi**

Run: `docker exec php-osm2cai2 php artisan test --filter=si_hiking_route_sees_the_same_layers_as_hiking_route`

Expected: PASS.

- [ ] **Step 5: Commit** (solo dopo approvazione esplicita del dev)

```bash
git add app/Models/HikingRoute.php tests/Unit/Models/HikingRouteMorphClassTest.php
git commit -m "fix(oc:8620): allinea la morph class delle sottoclassi a HikingRoute"
```

---

### Task 2: Test di regressione sul documento indicizzato

**Files:**
- Modify: `tests/Unit/Models/HikingRouteMorphClassTest.php`

**Interfaces:**
- Consumes: `HikingRoute::getMorphClass()` dal Task 1, e il metodo privato `createRouteWithLayer(): array` definito nello stesso file di test.
- Produces: niente per i task successivi.

Il test del Task 1 verifica la relazione. Questi tre verificano ciò che il cliente ha visto: il documento che finisce su Elasticsearch, e il ciclo completo di salvataggio — il percorso reale del bug, non `::find()->searchable()`.

- [ ] **Step 1: Scrivere i tre test che mancano**

Aggiungi in coda alla classe `HikingRouteMorphClassTest`:

```php
    /** @test */
    public function searchable_array_of_a_si_hiking_route_contains_the_layers()
    {
        [$route, $layer] = $this->createRouteWithLayer();

        $document = SiHikingRoute::find($route->id)->toSearchableArray();

        $this->assertContains(
            $layer->id,
            $document['layers'],
            "Il documento indicizzato di una SiHikingRoute deve contenere i layer: e' l'assert che avrebbe intercettato oc:8620"
        );
    }

    /** @test */
    public function searchable_array_of_a_si_mtb_route_contains_the_layers()
    {
        [$route, $layer] = $this->createRouteWithLayer();

        $document = SiMTBRoute::find($route->id)->toSearchableArray();

        $this->assertContains($layer->id, $document['layers']);
    }

    /** @test */
    public function saving_a_si_hiking_route_indexes_a_document_with_the_layers()
    {
        config(['scout.queue' => false]);

        [$route, $layer] = $this->createRouteWithLayer();

        $subject = SiHikingRoute::find($route->id);
        $subject->name = 'Tappa modificata da Nova oc:8620';
        $subject->save();

        $this->assertContains(
            $layer->id,
            $subject->fresh()->toSearchableArray()['layers'],
            'Dopo il salvataggio il documento deve conservare i layer'
        );
    }
```

- [ ] **Step 2: Eseguire i tre test e verificare che passino**

Run: `docker exec php-osm2cai2 php artisan test --filter=HikingRouteMorphClassTest`

Expected: PASS, 4 test.

- [ ] **Step 3: Verificare che i test falliscano senza il fix**

Commenta temporaneamente il metodo `getMorphClass()` aggiunto nel Task 1, rilancia la suite e conferma che tutti e quattro i test falliscono. Poi ripristina il metodo. Serve a escludere un test tautologico che passerebbe comunque.

Run: `docker exec php-osm2cai2 php artisan test --filter=HikingRouteMorphClassTest`

Expected: 4 FAIL con il metodo commentato, 4 PASS dopo averlo ripristinato.

- [ ] **Step 4: Eseguire la suite Unit completa per escludere regressioni**

Run: `docker exec php-osm2cai2 php artisan test --testsuite=Unit`

Expected: nessun test che passava prima deve fallire ora. Se qualcuno fallisce, il candidato più probabile è un test che si aspetta `App\Models\SiHikingRoute` come morph type: annotalo in `notes.md` e valutalo con il dev prima di modificarlo.

- [ ] **Step 5: Formattare il codice**

Run: `docker exec php-osm2cai2 ./vendor/bin/pint --ansi`

- [ ] **Step 6: Commit** (solo dopo approvazione esplicita del dev)

```bash
git add tests/Unit/Models/HikingRouteMorphClassTest.php
git commit -m "test(oc:8620): copre relazioni e documento indicizzato delle sottoclassi SI"
```

---

### Task 3: Verifica del caso "layer stantii" e ticket separato

**Files:**
- Modify: `docs/features/8620-morph-class-sihikingroute-layers-elasticsearch/notes.md`

**Interfaces:**
- Consumes: il fix del Task 1 applicato in locale.
- Produces: un esito documentato in `notes.md`, ed eventualmente un nuovo ticket Orchestrator.

Il fix fa leggere i layer dal pivot. Se il pivot non viene riallineato quando cambiano le attività di una tappa modificata dalla vista SI, il documento uscirà con layer presenti ma vecchi — il fix toglierebbe il sintomo diagnostico di un problema che non causa. Questo task accerta se il caso è reale, senza allargare lo scope a `wm-package`.

- [ ] **Step 1: Registrare lo stato del pivot prima della modifica**

```bash
docker exec php-osm2cai2 php artisan tinker --execute='
$id = 29904;
echo "layer prima: ".json_encode(DB::table("layerables")->where("layerable_id",$id)->pluck("layer_id")->toArray())."\n";
echo "attivita prima: ".json_encode(App\Models\HikingRoute::find($id)->taxonomyActivities()->pluck("taxonomy_activities.id")->toArray())."\n";
'
```

- [ ] **Step 2: Cambiare l'attività della tappa dalla risorsa Nova SI**

Apri `http://localhost:8008/resources/si-hiking-routes/29904` nel browser, modifica il campo delle attività (taxonomy activities) scegliendo un valore diverso, e salva dal form Nova. Il passaggio dall'interfaccia è necessario: il difetto ipotizzato dipende dal percorso della request (`nova-api/si-hiking-routes*`), che un salvataggio da tinker non riproduce.

- [ ] **Step 3: Rileggere lo stato del pivot**

```bash
docker exec php-osm2cai2 php artisan tinker --execute='
$id = 29904;
echo "layer dopo: ".json_encode(DB::table("layerables")->where("layerable_id",$id)->pluck("layer_id")->toArray())."\n";
echo "attivita dopo: ".json_encode(App\Models\HikingRoute::find($id)->taxonomyActivities()->pluck("taxonomy_activities.id")->toArray())."\n";
'
```

- [ ] **Step 4: Registrare l'esito in `notes.md`**

Se i layer **non** si sono riallineati alla nuova attività, riporta in `notes.md` (sezione `## Follow-up`) l'esito con i due output a confronto, e apri un ticket Orchestrator separato di tipo Bug intitolato «I layer automatici non si riallineano salvando dalle risorse Nova SI», con nel corpo: gli output di prima e dopo, il riferimento a `EcTrackObserver::syncAutoLayersAfterNovaTrackEdit()` (`wm-package/src/Observers/EcTrackObserver.php:115-124`) e il fatto che il filtro `nova-api/ec-tracks*` esclude le richieste SI.

Se i layer si sono riallineati, scrivi in `notes.md` che il rischio sollevato dalla Challenge non si è materializzato, con gli output a prova.

In entrambi i casi **non modificare `wm-package`**.

---

### Task 4: Controllo pre-deploy e rilascio

**Files:**
- Nessuna modifica di codice.

**Interfaces:**
- Consumes: il fix e i test dei Task 1 e 2, l'esito del Task 3.
- Produces: il fix in produzione e i documenti Elasticsearch ripristinati.

- [ ] **Step 1: Ricontrollare le colonne `*_type` subito prima del deploy**

Fra la diagnosi e il rilascio qualcuno potrebbe aver creato righe con l'identità vecchia (un upload di media da Nova SI, per esempio). Oggi sono zero nelle quattro tabelle di dominio; questo controllo lo riconferma al momento giusto.

```bash
docker exec php-osm2cai2 php artisan tinker --execute='
foreach (["layerables"=>"layerable_type","taxonomy_activityables"=>"taxonomy_activityable_type","media"=>"model_type","signage_projectables"=>"signage_projectable_type"] as $t=>$c) {
  $n = DB::table($t)->whereIn($c, ["App\\\\Models\\\\SiHikingRoute","App\\\\Models\\\\SiMTBRoute"])->count();
  echo str_pad($t,28)." righe con identita vecchia: $n\n";
}
'
```

Expected: `0` su tutte e quattro. Se una qualsiasi è diversa da zero, **fermati**: quelle righe diventerebbero irraggiungibili dopo il fix e vanno normalizzate prima, con una UPDATE mirata da concordare con il dev.

- [ ] **Step 2: Aprire la PR verso `develop`**

Titolo: `fix(oc:8620): la tappa salvata da Nova SI non sparisce piu dal layer`

Nel corpo: il link a `docs/features/8620-morph-class-sihikingroute-layers-elasticsearch/`, la causa in tre righe, e l'avviso che dopo il merge in produzione serve il reindex dello Step 4.

- [ ] **Step 3: Deploy in produzione**

Il deploy avviene sul merge in `main`. Nota: le ultime run di `prod-deploy.yml` falliscono ai test in CI con `Deploy: skipped`, quindi verifica come il rilascio arriva davvero sul server prima di considerarlo fatto.

- [ ] **Step 4: Reindex Scout dei documenti già corrotti**

Da Nova in produzione: `https://osm2cai.cai.it/resources/apps/2` → seleziona l'app → action **Reindex Scout**. Reindicizza tutte le 1.188 tracce `app_id = 2`, caricandole con `$app->ecTracks()`, cioè come `HikingRoute`.

Verificato in fase di analisi che nessuna traccia `app_id = 2` verrebbe rimossa dall'indice: zero record con geometria nulla, zero con `osm2cai_status = 0`, gli unici due casi esclusi da `shouldBeSearchable()`.

- [ ] **Step 5: Verifica finale in produzione**

```bash
curl -s "https://osm2cai.cai.it/api/v2/elasticsearch?app=geohub_app_2&layer=6" \
  | python3 -c "
import sys,json
d=json.load(sys.stdin)
ids={h.get('id') for h in d['hits']}
print('V03 (29904) presente:', 29904 in ids)
print('V04 (29598) presente:', 29598 in ids)
print('totale risultati layer 6:', len(d['hits']))
"
```

Expected: entrambe `True`, e un totale non inferiore ai 522 risultati misurati prima del fix.

- [ ] **Step 6: Riprovare il ciclo che ha generato la segnalazione**

Apri `https://osm2cai.cai.it/resources/si-hiking-routes/29904`, salva senza modificare nulla, poi rilancia il comando dello Step 5. La tappa deve restare presente: è la prova che il bug non si ripresenta al salvataggio, che è ciò che il cliente ha segnalato.
