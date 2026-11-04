> Ticket: oc:8868

# Piano — Ciclo 2: GeoJSON delle sorgenti con tutti i rilievi validati e data della misura

Riferimento: [overview.md](overview.md), sezione «Ciclo 2». Tutto nel repo `osm2cai2`, nessuna modifica a `wm-package`.

**Commit:** nessun commit, merge o push senza conferma esplicita della dev, uno per volta. I messaggi indicati sono proposte, con scope `oc:8868`.

## Task 0 — Allineare il branch a develop

Il branch non contiene oc:8879 (`558867f`): mancano la lettura dei valori da `properties.form` e `computeFlowRate()`.

1. Con conferma della dev: `git merge develop` nel branch `feature/oc-8868-acqua-sorgente-scarico-geojson`.
2. Aggiornare il submodule: `git submodule update wm-package`, così il puntatore coincide con quello di develop.
3. Controllare che `app/Models/UgcPoi.php` contenga `computeFlowRate()` e `getFlowRateInput()`.

## Task 1 — Fotografia del GeoJSON prima delle modifiche

Serve a verificare che i rilievi già presenti non cambino.

```bash
curl -s http://localhost:8008/api/v2/source_survey/survey.geojson > <scratchpad>/survey-prima.geojson
```

Annotare numero di feature e tempo di risposta (oggi 2216 feature, 2,3 s).

## Task 2 — Condizione del form in un solo punto

- `app/Models/UgcPoi.php`: scope `scopeForForm(Builder $query, string $formId)` con `where(form_id = $formId OR properties->form->id = $formId)`, raggruppato in una closure perché si combina con altri `where`.
- `app/Nova/AbstractValidationResource.php`: `indexQuery()` usa `$query->forForm(static::getFormId())`. Comportamento invariato.

## Task 3 — Lettura dei valori del form

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3--lettura-dei-valori-del-form)

- `app/Models/UgcPoi.php`: rendere pubblico l'accesso ai valori del form con un metodo `getFormValue(string $key): mixed` (prima `properties.form`, poi `raw_data`; valore vuoto → fonte successiva). `getFlowRateInput()` di oc:8879 diventa una chiamata a questo metodo, senza cambiare comportamento.
- I test di oc:8879 (`tests/Unit/Models/UgcPoiFlowRateTest.php`) devono restare verdi.

## Task 4 — Data della misura

`app/Models/UgcPoi.php`, metodo `getSurveyDate(): ?Carbon`, in UTC:

1. `properties.createdAt`
2. `raw_data.date`
3. `created_at`, convertito in UTC

Regole:
- una stringa vuota o non ISO 8601 (non inizia con `AAAA-MM-GG`) si salta: evita che `Carbon::parse('')` restituisca «adesso» e che `11/08/2024` diventi l'8 novembre;
- un'eccezione di parse si salta;
- `null` solo se manca anche `created_at` (non dovrebbe succedere).

Test `tests/Unit/Models/UgcPoiSurveyDateTest.php`, su modelli non salvati come `UgcPoiFlowRateTest`:
- `createdAt` vince su `raw_data.date` (caso del 7252);
- `raw_data.date` quando manca `createdAt`;
- `created_at` quando mancano entrambe, convertito in UTC (es. `2025-05-02 10:00:00` Europe/Rome → `2025-05-02T08:00:00+00:00`);
- stringa vuota e stringa non ISO saltate;
- output sempre con offset `+00:00`.

## Task 5 — Controller

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5--controller)

`app/Http/Controllers/SourceSurveyController.php`:

- `getValidatedSurveys()`: `UgcPoi::forForm('water')->where('validated', 'valid')->get()`. Vale anche per gpx, kml, shp e `overlay.geojson` (atteso, vedi overview).
- `createFeatures()`:
  - `active`, `temperature`, `conductivity`, `range_volume` con `getFormValue()`, ripiego `'N/A'` come oggi;
  - `info`: `description` del modello, poi `getFormValue('info')`, poi `'N/A'`;
  - `elevation`: invariato (`raw_data.position.altitude`); verificare se i rilievi recenti hanno la quota altrove e, se sì, annotarlo in notes.md senza allargare lo scope;
  - `range_time`: `computeFlowRate()` (nessuna scrittura sul DB, a differenza di `calculateFlowRate()`);
  - `date`: `getSurveyDate()?->toIso8601String()`.

## Task 6 — Test della condizione condivisa

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6--test-della-condizione-condivisa)

`tests/Unit/Models/UgcPoiForFormScopeTest.php`: lo SQL di `UgcPoi::forForm('water')` contiene entrambe le condizioni in OR raggruppate, e combinato con `where('validated', 'valid')` il filtro sulla validazione resta in AND (`toSql()` + bindings, senza DB).

## Task 7 — Verifica in locale

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-7--verifica-in-locale)

1. `php artisan test --filter='UgcPoi'` nel container; poi la suite `Unit`.
2. GeoJSON dopo: numero di feature uguale ai validi della risorsa Nova:
   ```sql
   select count(*) from ugc_pois where validated='valid' and (form_id='water' or properties->'form'->>'id'='water');
   ```
   (2650 sul DB locale).
3. Confronto con `survey-prima.geojson`, per id: per i 2216 rilievi già presenti, `title`, `active`, `info`, `temperature`, `conductivity`, `range_volume`, `elevation` e geometria identici. `range_time` può cambiare solo dove il valore salvato era vecchio (oc:8879 ne aveva trovati 18): elencare le differenze in notes.md.
4. Tutte le feature hanno `date` con offset `+00:00`; contare quante vengono da ciascuna fonte.
5. Rilievo 7253: `date` = `2024-08-04T11:08:10+00:00` nel GeoJSON.
6. Un rilievo recente (es. 8833): valori di misura presi da `properties.form`, non `N/A`.
7. Tempo di risposta del file dopo la modifica, da confrontare con i 2,3 s di prima.

## Task 8 — Formattazione

`./vendor/bin/pint --ansi` nel container sui file toccati.

## Task 9 — Documentazione

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-9--documentazione)

- `notes.md`: divergenze, differenze di `range_time`, numeri della verifica.
- Pagina di conoscenza in `docs/knowledge/` sul GeoJSON delle sorgenti (quali rilievi, da dove vengono valori e data), con riga in `## Feature disponibili` del `CLAUDE.md` solo se la dev lo approva (il team ha chiesto di non toccare il CLAUDE.md).

Commit proposti, dopo la review e su conferma:
- `fix(oc:8868): il GeoJSON delle sorgenti restituisce gli stessi rilievi validati di Nova`
- `feat(oc:8868): data della misura nel GeoJSON delle sorgenti`
- `docs(oc:8868): piano, note e pagina di conoscenza del ciclo 2`
