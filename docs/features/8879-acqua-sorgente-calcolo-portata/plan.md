> Ticket: oc:8879

# Piano — Acqua Sorgente - Calcolo portata

Il ticket ha due cicli. Il piano del secondo è qui sotto; quello del primo, già eseguito, resta più in basso.

# Ciclo 2 — Il motivo del N/A

Repo: `osm2cai2` (tutto custom, nessun submodule). Riferimento: [overview.md](overview.md), sezione «Ciclo 2».

**Approccio:** in `UgcPoi` un solo metodo privato fa tutti i controlli e restituisce valore e motivi insieme. `computeFlowRate()` ne prende il valore (risultato e dato salvato identici a oggi); un metodo nuovo, `flowRateForDisplay()`, compone «N/A (motivo)» traducendo i motivi con `__()`. In Nova il campo *Flow Rate L/s* si sdoppia: nell'elenco resta com'è, nel dettaglio e nel form mostra il valore con il motivo. TDD: prima i test, poi il codice.

> ⚠️ Commit e branch sono istruzioni per il dev: nessun `git commit`/`git push` automatico. Il branch `feature/oc-8879-acqua-sorgente-calcolo-portata` esiste già e contiene il primo ciclo.

---

## Ciclo 2 — Task 1: test dei motivi (rossi)

File: `tests/Unit/Models/UgcPoiFlowRateTest.php` (stesso `makeSurvey()`, nessun accesso al DB). In `setUp()` imposta `app()->setLocale('it')`.

Un test per riga, su `flowRateForDisplay()`; gli input vengono da schede reali del DB locale:

| Caso | Input (form, `water_flow_rate_validated`) | Atteso |
|---|---|---|
| Non validata | volume `1`, tempo `10`, `not_validated` | `N/A (portata non validata)` |
| Sorgente non attiva (1084) | `active` = `no`, volume e tempo assenti | `N/A (sorgente non attiva)` |
| Non attiva ma misurata | `active` = `no`, volume `1`, tempo `10` | `0.1` (si calcola normalmente) |
| Entrambi vuoti (1122) | `active` = `yes`, volume e tempo assenti | `N/A (volume e tempo non inseriti)` |
| Solo volume vuoto (1831) | tempo `4` | `N/A (volume non inserito)` |
| Solo tempo vuoto (1060) | volume `1,5` | `N/A (tempo non inserito)` |
| Volume non interpretabile (1337) | `0'.5` / `5.90` | `N/A (volume non valido: inserire solo numeri)` |
| Tempo non interpretabile (1166) | `0,75` / `Mezzo secondo` | `N/A (tempo non valido: inserire solo numeri)` |
| Entrambi non interpretabili (4510) | `lt1..` / `1 secondo e 50 cent` | `N/A (volume e tempo non validi: inserire solo numeri)` |
| Misto (4610) | `Impossibile determinazione stillicidio d'acqua` / assente | `N/A (volume non valido: inserire solo numeri; tempo non inserito)` |
| Tempo zero (1449) | `0` / `0` | `N/A (il tempo non può essere zero)` |
| Calcolabile | `0,500` / `3` | `0.167` (uguale a `computeFlowRate()`) |
| Valore solo spazi in `properties.form`, valido in `raw_data` | form volume `' '`, raw_data volume `2`, tempo `10` | `0.2` |
| `active` in `raw_data` (scheda vecchia) | form senza `active`, raw_data `active` = `no`, nessun volume e tempo | `N/A (sorgente non attiva)` |

Più un test di coerenza con i data provider del primo ciclo (`FlowRateParserTest::volumeProvider()` e `timeProvider()`, già `public static`): per ogni volume con tempo fisso `10`, e per ogni tempo con volume fisso `1`, verifica che `flowRateForDisplay()` inizi con `N/A (` **se e solo se** `computeFlowRate()` è `'N/A'`, e che altrimenti i due valori siano identici.

I test esistenti su `computeFlowRate()` non si toccano.

```bash
docker exec -it laravel-osm2cai2 php artisan test tests/Unit/Models/UgcPoiFlowRateTest.php
```

Atteso: **rosso** (`flowRateForDisplay()` non esiste).

## Ciclo 2 — Task 2: traduzioni

Aggiungi le chiavi, in ordine alfabetico dove il file lo è già, rispettando l'indentazione di ciascun file (`it.json` 4 spazi, `en.json` 2 spazi). Prima verifica che non esistano già: `grep -n '"<chiave>"' lang/vendor/nova/*.json`.

| Chiave | `it.json` | `en.json` |
|---|---|---|
| `Flow rate not validated` | portata non validata | flow rate not validated |
| `Source not active` | sorgente non attiva | source not active |
| `Volume and time not entered` | volume e tempo non inseriti | volume and time not entered |
| `Volume not entered` | volume non inserito | volume not entered |
| `Time not entered` | tempo non inserito | time not entered |
| `Invalid volume: enter numbers only` | volume non valido: inserire solo numeri | invalid volume: enter numbers only |
| `Invalid time: enter numbers only` | tempo non valido: inserire solo numeri | invalid time: enter numbers only |
| `Invalid volume and time: enter numbers only` | volume e tempo non validi: inserire solo numeri | invalid volume and time: enter numbers only |
| `Time cannot be zero` | il tempo non può essere zero | time cannot be zero |

Controllo che i due file restino JSON validi:

```bash
python3 -c "import json;[json.load(open(f)) for f in ['lang/vendor/nova/it.json','lang/vendor/nova/en.json']]" && echo ok
```

## Ciclo 2 — Task 3: `app/Models/UgcPoi.php`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#ciclo-2--task-3-portata-non-validata-e-problemi-dei-dati)

- Nuovo metodo privato `evaluateFlowRate(): array` che restituisce `[float|string $value, array $reasons]` (i motivi sono le chiavi inglesi del Task 2, non tradotte). Ordine:
  1. `water_flow_rate_validated != valid` → `['N/A', ['Flow rate not validated']]` (stesso confronto lasco di oggi).
  2. Legge volume e tempo con `getFlowRateInput()` (invariato). «Vuoto» = `null` oppure stringa vuota dopo `trim`.
  3. Entrambi vuoti → `Source not active` se `active` (da `properties.form.active`, con fallback su `raw_data.active`) vale `no` senza distinzione di maiuscole, altrimenti `Volume and time not entered`.
  4. Altrimenti `parseVolume()`/`parseTime()` sui valori non vuoti. Entrambi non interpretabili → `Invalid volume and time: enter numbers only`. Altrimenti si raccolgono, prima per il volume e poi per il tempo: `Volume not entered` / `Invalid volume: enter numbers only`, `Time not entered` / `Invalid time: enter numbers only`.
  5. Se ci sono motivi → `['N/A', $reasons]`.
  6. Tempo `== 0` → `['N/A', ['Time cannot be zero']]`.
  7. Altrimenti `[round($volume / $time, 3), []]`.
- `computeFlowRate()` diventa `return $this->evaluateFlowRate()[0];`.
- Nuovo `flowRateForDisplay(): float|string`: se ci sono motivi restituisce `'N/A ('.implode('; ', array_map(fn ($key) => __($key), $reasons)).')'`, altrimenti il valore. Non salva nulla.
- `calculateFlowRate()` invariato: continua a salvare il valore senza motivo.

Esegui il test del Task 1 → atteso **verde**, compresi i test esistenti.

## Ciclo 2 — Task 4: `app/Nova/SourceSurvey.php`

In `prepareSurveyFields()` il campo *Flow Rate L/s* diventa due campi sullo stesso attributo:

- **Elenco:** come oggi (`resolveUsing` → `calculateFlowRate()`), con `->onlyOnIndex()`.
- **Dettaglio e form:** `resolveUsing` chiama `calculateFlowRate()` (stesso salvataggio di oggi) e restituisce `flowRateForDisplay()`; `->hideFromIndex()`, `->readonly()`, stesso `->help()` di oggi.

Il campo in sola lettura non viene riempito al salvataggio del form, quindi «N/A (motivo)» non finisce mai in `raw_data.flow_rate`: verificarlo nel Task 6.

## Ciclo 2 — Task 5: suite e formattazione

```bash
docker exec -it laravel-osm2cai2 php artisan test tests/Unit/Models/UgcPoiFlowRateTest.php tests/Unit/Services/FlowRateParserTest.php
docker exec -it laravel-osm2cai2 ./vendor/bin/pint --ansi app/Models/UgcPoi.php app/Nova/SourceSurvey.php tests/Unit/Models/UgcPoiFlowRateTest.php
```

## Ciclo 2 — Task 6: verifica manuale in Nova (locale, in italiano)

Per ogni scheda: elenco (colonna *Portata L/s* = `N/A` o numero), dettaglio e form di modifica (valore con motivo).

| Scheda | Atteso nel dettaglio |
|---|---|
| [8833](http://127.0.0.1:8008/resources/source-surveys/8833) | N/A (portata non validata; volume e tempo non inseriti) |
| [1084](http://127.0.0.1:8008/resources/source-surveys/1084) | N/A (sorgente non attiva) |
| [1122](http://127.0.0.1:8008/resources/source-surveys/1122) | N/A (volume e tempo non inseriti) |
| [1831](http://127.0.0.1:8008/resources/source-surveys/1831) | N/A (volume non inserito) |
| [1060](http://127.0.0.1:8008/resources/source-surveys/1060) | N/A (tempo non inserito) |
| [1337](http://127.0.0.1:8008/resources/source-surveys/1337) | N/A (volume non valido: inserire solo numeri) |
| [1166](http://127.0.0.1:8008/resources/source-surveys/1166) | N/A (tempo non valido: inserire solo numeri) |
| [4510](http://127.0.0.1:8008/resources/source-surveys/4510) | N/A (volume e tempo non validi: inserire solo numeri) |
| [4610](http://127.0.0.1:8008/resources/source-surveys/4610) | N/A (volume non valido: inserire solo numeri; tempo non inserito) |
| [1449](http://127.0.0.1:8008/resources/source-surveys/1449) | N/A (il tempo non può essere zero) |
| [197](http://127.0.0.1:8008/resources/source-surveys/197) | 0.167, invariato |

Dopo aver aperto e salvato il form della 1084, controlla in tinker che `raw_data->flow_rate` sia ancora `N/A`.

## Ciclo 2 — Task 7: commit (su conferma del dev)

```
fix(oc:8879): motivo del N/A della portata nel dettaglio Nova delle sorgenti
docs(oc:8879): overview, piano e note del secondo ciclo
```

PR verso `develop`. Dopo il push: avvisare il tester del ticket che il secondo ciclo è pronto (richiesta dello scrum del 03/11).

A fine workflow: aprire su Orchestrator il ticket del form dell'app per Alessandro Peci (volume e tempo numerici, aiuto «in litri»), con dentro le quattro domande della sezione «Domande da riportare nel ticket del form per Alessandro» dell'overview.

---

# Ciclo 1 — Calcolo della portata (eseguito)

Repo: `osm2cai2` (tutto custom, nessun submodule). Riferimento: [overview.md](overview.md).

**Approccio:** il parser va in una classe a sé (`App\Services\FlowRateParser`), senza dipendenze da Eloquent, così si testa con un data provider senza toccare il DB. `UgcPoi` separa il calcolo puro (`computeFlowRate()`) dal salvataggio (`calculateFlowRate()`, comportamento invariato per Nova). TDD: prima i test, poi il codice.

> ⚠️ Commit e branch sono istruzioni per il dev: nessun `git commit`/`git push` automatico.

---

## Task 0 — Branch

```bash
git checkout develop && git pull
git checkout -b feature/oc-8879-acqua-sorgente-calcolo-portata
```

## Task 1 — Test del parser (rossi)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-e-2--apice-singolo-e--come-secondi)

File nuovo: `tests/Unit/Services/FlowRateParserTest.php` (`Tests\TestCase`, nessun accesso al DB).

Due data provider, uno per `parseVolume()` e uno per `parseTime()`. Ogni riga: valore inserito → litri/secondi attesi (`float`) oppure `null` (= non interpretabile → la portata sarà `N/A`). Contengono **tutti i 62 valori non numerici reali** estratti dal DB locale (schede `water` validate) più i casi puliti.

**Volume (`parseVolume`)**

| Input | Atteso | | Input | Atteso |
|---|---|---|---|---|
| `1` | 1.0 | | `1 litro` | 1.0 |
| `0.75` | 0.75 | | `1litro` | 1.0 |
| `0,75` | 0.75 | | `1l` | 1.0 |
| `.6` | 0.6 | | `1 lt` | 1.0 |
| `0, 5` | 0.5 | | `1 lt.` | 1.0 |
| `1, 5` | 1.5 | | `0.500 l` | 0.5 |
| `o.5` | 0.5 | | `0,500 litri` | 0.5 |
| `O,5` | 0.5 | | `0,5 lt` | 0.5 |
| `o,500` | 0.5 | | `1,5 l` | 1.5 |
| `500ml` | 0.5 | | `500 ml` | 0.5 |
| `NA` | null | | `0 45` | null |
| `0'.5` | null | | `1 litro2` | null |
| `lt1..` | null | | `Impossibile determinazione stillicidio d'acqua` | null |
| `''` | null | | `null` | null |

**Tempo (`parseTime`)**

| Input | Atteso | | Input | Atteso |
|---|---|---|---|---|
| `15` | 15.0 | | `9.5` | 9.5 |
| `15"` | 15.0 | | `3.5"` | 3.5 |
| `6"`, `3"`, `9"`, `2"`, `5"`, `7"` | 6, 3, 9, 2, 5, 7 | | `12"`, `27"`, `11"`, `25"`, `29"`, `30"` | 12, 27, 11, 25, 29, 30 |
| `17''` | 17.0 | | `1,8''` | 1.8 |
| `03''`, `2''`, `3''`, `5''`, `7''`, `8''`, `30''` | 3, 2, 3, 5, 7, 8, 30 | | `10 secondi` | 10.0 |
| `secondi 3.85` | 3.85 | | `1, 46` | 1.46 |
| `19, 6` | 19.6 | | `00:05.65` | 5.65 |
| `01:05.3` | 65.3 | | `00 01 73` | 1.73 |
| `00 02 53` | 2.53 | | `00 05 02` | 5.02 |
| `00 11 98` | 11.98 | | `00 01 00` | 1.0 |
| `00 06. 79` | null | | `11'` | null |
| `7s 80` | null | | `6™` | null |
| `1 secondo e 50 cent` | null | | `Mezzo secondo` | null |
| `NA` | null | | `NÀ` | null |

Confronto con `assertEqualsWithDelta(..., 0.0001)` per i float, `assertNull` per i `null`.

Esegui: `docker exec -it php-osm2cai2 php artisan test --filter=FlowRateParserTest` → atteso **rosso** (classe inesistente).

## Task 2 — `App\Services\FlowRateParser`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-e-2--apice-singolo-e--come-secondi)

File nuovo: `app/Services/FlowRateParser.php`, due metodi pubblici statici `parseVolume(mixed $value): ?float` e `parseTime(mixed $value): ?float`.

Regole (dall'overview, sezione Requisiti → Parser):

1. `null`, non stringa/numero, o stringa vuota dopo `trim` → `null`. Un `int`/`float` passa così com'è.
2. Normalizzazione: `trim`, `mb_strtolower`.
3. **Volume:** se termina con `ml` (con o senza spazio) → fattore 0.001 e tolgo l'unità; altrimenti tolgo un'unità finale fra `litri`, `litro`, `lt.`, `lt`, `l`.
4. **Tempo:**
   - `^(\d{1,2}):(\d{1,2}(?:[.,]\d+)?)$` → minuti × 60 + secondi;
   - `^(\d{2}) (\d{2}) (\d{2})$` → minuti × 60 + secondi + centesimi / 100;
   - altrimenti tolgo un'unità iniziale o finale fra `secondi`, `secondo`, `s`, `''`, `"`.
5. **Numero decimale** (comune): `o` iniziale seguita da `.`/`,` → `0`; poi deve corrispondere a `^(\d*)\s*[.,]\s*(\d+)$` (con almeno una cifra totale) oppure `^\d+$`. Qualsiasi altra forma → `null`.

Esegui il test del Task 1 → atteso **verde**.

## Task 3 — Test del calcolo nel modello (rossi)

File nuovo: `tests/Unit/Models/UgcPoiFlowRateTest.php`, su istanze `new UgcPoi()` **non salvate** (nessuna scrittura sul DB), chiamando `computeFlowRate()`:

- volume/tempo solo in `properties.form` (`1` / `15`), `raw_data = ['flow_rate' => 'N/A']`, valid → `0.067` (caso 8621);
- volume/tempo solo in `raw_data` → calcolato (schede vecchie);
- chiave in entrambi con valori diversi → vince `properties.form`;
- chiave presente in `properties.form` ma vuota (`''`) → fallback su `raw_data`;
- `water_flow_rate_validated = invalid` e `not_validated` → `'N/A'`;
- tempo `0` → `'N/A'`;
- volume non interpretabile (`1 litro2`) → `'N/A'`;
- `500ml` / `1,48` → `0.338` (caso 4566).

Esegui `--filter=UgcPoiFlowRateTest` → atteso **rosso** (metodo inesistente).

## Task 4 — Refactor di `app/Models/UgcPoi.php`

- Nuovo `computeFlowRate(): float|string` — calcolo puro: legge volume e tempo con fallback, usa `FlowRateParser`, applica le condizioni attuali (valid, tempo ≠ 0), `round(..., 3)`, restituisce `'N/A'` se non calcolabile.
- Nuovo metodo privato `getFlowRateInput(string $key): mixed` — `properties['form'][$key]` se presente e non vuoto, altrimenti `raw_data[$key]` (gestendo `raw_data` stringa JSON o `null`, come fa oggi `calculateFlowRate()`).
- `calculateFlowRate()` resta con la stessa firma e lo stesso comportamento verso Nova e `SourceSurveyController`: chiama `computeFlowRate()`, scrive `raw_data['flow_rate']` e fa `saveQuietly()`.
- Rimuovi `formatNumericValue()` (nessun altro chiamante: verificato con `grep -rn formatNumericValue app/`).

Esegui i test dei Task 1 e 3 → atteso **verde**.

## Task 5 — Suite e formattazione

```bash
docker exec -it php-osm2cai2 php artisan test --testsuite=Unit
docker exec -it php-osm2cai2 ./vendor/bin/pint --ansi app/Services/FlowRateParser.php app/Models/UgcPoi.php tests/Unit/Services/FlowRateParserTest.php tests/Unit/Models/UgcPoiFlowRateTest.php
```

Nessun test esistente deve diventare rosso.

## Task 6 — Verifica manuale in Nova (locale)

Apri in Nova e controlla il campo **Flow Rate L/s**:

| Scheda | Atteso |
|---|---|
| [8621](http://localhost:8008/resources/source-surveys/8621) | 0.067 |
| [8619](http://localhost:8008/resources/source-surveys/8619) | 0.1 |
| [4566](http://localhost:8008/resources/source-surveys/4566) | 0.338 |
| una scheda vecchia con valori puliti in `raw_data` | invariata |

## Task 7 — Commit (su conferma del dev)

```
fix(oc:8879): calcolo portata sorgenti da properties.form e parser conservativo dei valori
test(oc:8879): data provider con i valori reali di volume e tempo
docs(oc:8879): overview, piano e note
```

PR verso `develop`.

## Dopo il deploy

Aprire in produzione la scheda 10296 e verificare il valore. Se resta `N/A`, leggere i valori grezzi e aggiungerli al data provider.
