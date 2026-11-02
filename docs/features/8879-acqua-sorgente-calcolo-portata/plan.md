> Ticket: oc:8879

# Piano — Acqua Sorgente - Calcolo portata

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
