> Ticket: oc:8879

# Acqua Sorgente - Calcolo portata

Il ticket ha due cicli. Il secondo, qui sotto, si aggiunge al primo senza cambiarne il calcolo: il primo ciclo resta valido e descritto più in basso.

# Ciclo 2 — Il motivo del N/A

## Cosa cambia

Quando la portata non si può calcolare, la scheda Nova della sorgente dice **perché**. Nel dettaglio e nel form di modifica il campo *Flow Rate L/s* passa da «N/A» a «N/A (motivo)». Nell'elenco Nova e nel dato salvato (`raw_data.flow_rate`) resta «N/A».

| Caso | Schede nel DB locale* | Valore mostrato (it) |
|---|---|---|
| Portata non validata (`water_flow_rate_validated` ≠ valid) | — | N/A (portata non validata) |
| Sorgente non attiva, volume e tempo vuoti | 79 | N/A (sorgente non attiva) |
| Volume e tempo entrambi vuoti (sorgente attiva) | 128 | N/A (volume e tempo non inseriti) |
| Solo volume vuoto | 5 | N/A (volume non inserito) |
| Solo tempo vuoto | 2 | N/A (tempo non inserito) |
| Volume non interpretabile (es. `1 litro2`) | 3 | N/A (volume non valido: inserire solo numeri) |
| Tempo non interpretabile (es. `7s 80`) | 3 | N/A (tempo non valido: inserire solo numeri) |
| Volume e tempo non interpretabili | 4 | N/A (volume e tempo non validi: inserire solo numeri) |
| Tempo pari a zero | 2 | N/A (il tempo non può essere zero) |

\* Schede `water` con portata validata: 1884, di cui 227 in N/A. «Sorgente attiva» si legge da `properties.form.active`, con fallback su `raw_data.active`.

Esempio: scheda 8833, volume e tempo vuoti e portata `not_validated`. Oggi il dettaglio mostra «N/A»; dopo mostra «N/A (portata non validata; volume e tempo non inseriti)»: la portata non validata è sempre N/A, ma i problemi dei dati si elencano comunque, altrimenti comparirebbero solo dopo la validazione.

| Dove | Prima | Dopo |
|---|---|---|
| Elenco Nova | N/A | N/A |
| Dettaglio Nova | N/A | N/A (motivo) |
| Form di modifica Nova (campo in sola lettura) | N/A | N/A (motivo); il testo di aiuto fisso resta invariato |
| `raw_data.flow_rate` salvato, export, popup dell'overlay | N/A | N/A (invariati) |

## Perché

- **Scrum del 03/11** (trascrizione incollata dalla dev): Giuseppe Bonfanti chiede di rendere esplicito il N/A, perché chi ha scritto «1 litro2» non capisce perché la portata non c'è: «quell ND magari tra parentesi gli possiamo mettere la motivazione». La correzione del primo ciclo resta; in più si aggiunge un controllo all'inserimento nel form dell'app, in un ticket separato.
- Il dato salvato resta «N/A» per non cambiare nulla per chi lo legge: l'overlay lo confronta con `!== 'N/A'` (`SourceSurveyController.php:345`) e gli export lo scrivono così com'è. Il motivo si calcola quando lo si mostra.
- Nell'elenco il motivo non va perché la colonna è stretta; nel dettaglio va nel valore e non nel testo di aiuto, perché Nova 5 mostra l'aiuto solo nel form di modifica (verificato sulla scheda 8833 in locale).
- Le sorgenti non attive hanno un motivo a parte perché lì non misurare è corretto: «volume e tempo non inseriti» farebbe pensare a una dimenticanza (challenge: 79 schede su 207 con volume e tempo vuoti).

## Requisiti

- [ ] Un solo metodo privato di `UgcPoi` fa tutti i controlli e restituisce insieme valore e motivo, senza salvare nulla. `computeFlowRate()` ne usa solo il valore: risultato e dato salvato restano identici a oggi.
- [ ] Ordine dei motivi: «portata non validata» in testa se la portata non è validata, seguito dai problemi dei dati se ci sono; poi sorgente non attiva con volume e tempo vuoti; campi vuoti; valori non interpretabili; tempo zero. Quando volume e tempo hanno problemi diversi (uno vuoto, l'altro non interpretabile) i due motivi si uniscono con «; ».
- [ ] «Vuoto» e «non interpretabile» si distinguono sul valore letto con la stessa regola del calcolo (`properties.form` con fallback su `raw_data`): vuoto se assente o stringa vuota dopo `trim`, non interpretabile se presente ma il parser restituisce `null`.
- [ ] Il campo Nova *Flow Rate L/s* mostra «N/A (motivo)» nel dettaglio e nel form di modifica, «N/A» nell'elenco; i valori numerici non cambiano e il valore salvato resta «N/A».
- [ ] Ogni motivo è una chiave inglese passata a `__()`, con traduzione in `lang/vendor/nova/it.json` e `lang/vendor/nova/en.json`.
- [ ] Test unitari: un caso per ogni riga della tabella, il caso misto (volume vuoto e tempo non interpretabile) e il caso calcolabile (nessun motivo). Un test sul data provider dei valori reali del primo ciclo verifica che il motivo ci sia se e solo se la portata è «N/A». I test esistenti su `computeFlowRate()` restano invariati.
- [ ] Verifica manuale in Nova locale, in italiano: dettaglio, modifica ed elenco della scheda 8833 e di una scheda per ciascun motivo.

## Rischi

- **Traduzioni in locale:** fino al 03/11 il locale non leggeva `lang/vendor/nova/*.json` per via di due cartelle vuote e non versionate in `resources/lang/`, che spostavano `lang_path()`; per questo la scheda 8833 mostrava «Flow Rate L/s». Cancellate con l'ok della dev: ora `__('Flow Rate L/s')` restituisce «Portata L/s». In produzione le cartelle non esistono, perché non sono in git.
- **Calcolo e motivo divergenti:** gestito con un solo metodo per valore e motivo e con il test «motivo se e solo se N/A» (challenge).
- **Il campo Nova scrive nel DB anche quando lo si mostra:** `calculateFlowRate()` fa `saveQuietly()` dentro `resolveUsing`, anche per ogni riga dell'elenco, e salva solo se il valore cambia. È il comportamento del primo ciclo e non cambia: il motivo non viene mai salvato.

## Domande da riportare nel ticket del form per Alessandro

Incongruenze notate durante l'analisi, estranee alla richiesta del 03/11. Non si toccano in questo ciclo: vanno nel ticket separato sul form dell'app, da aprire per Alessandro Peci a fine lavoro.

1. **Popup dell'overlay** (`/api/v2/source_survey/overlay.geojson`): restituisce per ogni sorgente un riquadro HTML con la portata, ma nel repo e nelle configurazioni delle app non risulta chi lo mostra. Domanda per Alessandro Peci: dove compare, e se si usa ancora? Se sì, oggi mostra la portata salvata, che può essere vecchia (sul DB locale 18 schede su 2216, per esempio la 1602 con «15 L/s» invece di N/A), e non mostrerebbe il motivo.
2. Lo stesso popup ha 8 label scritte a mano in italiano e «Conducibilitá» con l'accento sbagliato: portarle a `__()` e correggere l'accento?
3. Nel dettaglio Nova *Flow Rate Validation* mostra il valore grezzo (`not_validated`) invece di una label leggibile. Va sistemato?
4. La label del form dell'app «TEMPO DI RIEPIMENTO IN SECONDI» manca della L. Lo schema del form sta nel campo `poi_acquisition_form` della tabella `apps`: va corretto nel ticket del form?

## Out of scope

- **Controllo all'inserimento nel form dell'app** (volume e tempo numerici, aiuto «in litri», punto e virgola gestiti dal form): ticket separato da aprire per Alessandro Peci, deciso nello scrum del 03/11. Lo schema è in `apps.poi_acquisition_form`.
- **Motivo nel popup dell'overlay e negli export**: il popup si valuta dopo la risposta di Alessandro nel ticket del form (domanda 1 qui sopra); gli export e il ricalcolo di `raw_data.flow_rate` nel ticket separato già previsto dal primo ciclo.

## Moduli toccati

Tutto nel repo principale `osm2cai2`, nessuna modifica a `wm-package`:

- `app/Models/UgcPoi.php` — metodo unico per valore e motivo; `computeFlowRate()` ne usa il valore; metodo che compone «N/A (motivo)» per la visualizzazione
- `app/Nova/SourceSurvey.php` — campo *Flow Rate L/s*: motivo nel dettaglio e nel form, «N/A» nell'elenco
- `lang/vendor/nova/it.json`, `lang/vendor/nova/en.json` — chiavi dei motivi
- `tests/Unit/Models/UgcPoiFlowRateTest.php` — test dei motivi e del legame motivo/N/A

# Ciclo 1 — Calcolo della portata (approvato il 02/11/2026)

## Cosa cambia

Nella scheda Nova delle sorgenti (`SourceSurvey`, form `water`) con *Validazione Portata* = **Valid**, il campo **Flow Rate L/s** (volume / tempo) mostra il valore corretto in due situazioni in cui oggi sbaglia:

1. **Schede inserite dall'app tramite `wm-package`**: volume e tempo stanno in `properties.form`, mentre il calcolo li cerca solo in `raw_data`, quindi il campo resta `N/A`. Esempio reale, scheda 8621: `properties.form.range_volume = 1`, `range_time = 15`, `raw_data = {"flow_rate": "N/A"}`. Oggi la scheda mostra `N/A`; dopo il fix mostra `0.067`.
2. **Valori scritti in modo «sporco»**: il parser accetta sia `,` sia `.` come separatore decimale, riconosce solo forme esplicite e dà `N/A` invece di un numero sbagliato.

| Valore inserito | Oggi | Dopo |
|---|---|---|
| `0,75` | 0.75 | 0.75 |
| `1, 5` | 1.5 | 1.5 |
| `0.500 l` | N/A | 0.5 |
| `1 litro` | 1 | 1 |
| `500ml` | 500 | 0.5 |
| `o.5` / `O,5` | N/A / 0.5 | 0.5 |
| `secondi 3.85` | N/A | 3.85 |
| `15"` / `17''` | 15 / 17 | 15 / 17 |
| `00:05.65` (mm:ss.cc) | N/A | 5.65 |
| `00 01 73` (mm ss cc) | 173 | 1.73 |
| `0 45` | 45 | N/A |
| `1 litro2` | 12 | N/A |
| `7s 80` | 780 | N/A |
| `Mezzo secondo` | N/A | N/A |

## Perché

- **Scope:** nella call del 28/10 08:25 ([trascrizione](https://docs.google.com/document/d/1pZ9i4siVfHKVKAKxdF_jbYAigS7NBcoldKx9CO9elJY)) Giuseppe Bonfanti descrive il ticket come il calcolo della portata nelle schede delle sorgenti («Nelle schede delle sorgenti, se validazione portata uguale valid, la portata dovrebbe essere calcolata come volume fratto tempo») e chiede di «prevedere tutti i casi di errori» partendo dai dati reali; gli export non vengono menzionati, per questo sono trattati in un ticket separato.
- **Fonte dati:** `wm-package` (`UgcController::fillModelWithRequest`, `wm-package/src/Http/Controllers/Api/Abstracts/UgcController.php:108-113`) salva i dati del form solo in `properties`. Nel DB locale 122 schede `water` validate hanno la portata `N/A` per questo motivo; quelle del 2026 sono tutte in questa situazione. Il record 10296 segnalato dal cliente è più recente del dump locale (id massimo circa 8830), quindi quasi certamente è uno di questi casi.
- **Parser:** `UgcPoi::formatNumericValue()` (`app/Models/UgcPoi.php:87-96`) restituisce il valore così com'è se contiene un punto, e altrimenti toglie tutto tranne cifre e virgole. Il risultato è `N/A` su valori validi come `0.500 l` e numeri sbagliati su altri: la scheda 4566 (`500ml` / `1,48`) mostra oggi 337.838 L/s invece di 0.338. La logica è identica dalla prima versione della resource (commit `377eed61`): non è una regressione del codice.

## Requisiti

**Lettura dei dati**
- [ ] Volume e tempo si leggono da `properties.form.range_volume` / `range_time`; se la chiave manca o è vuota, si usa `raw_data.range_volume` / `range_time`.

**Parser (conservativo)**
- [ ] Accetta un numero con `,` o `.` come separatore decimale, anche con uno spazio dopo la virgola (`1, 46`), e anche senza parte intera (`.6`).
- [ ] Toglie le unità note prima di interpretare il numero: volume `l`, `lt`, `lt.`, `litro`, `litri`, `ml` (con `ml` il valore si divide per 1000); tempo `s`, `secondo`, `secondi`, `"`, `''`, `'`, `™` (gli ultimi due sono errori di battitura del segno dei secondi, vedi [notes.md](notes.md#task-1-e-2--apice-singolo-e--come-secondi)).
- [ ] Legge `o`/`O` come zero solo quando precede direttamente un separatore decimale (`o.5`, `O,5`, `o,500`).
- [ ] Per il tempo riconosce `mm:ss`, `mm:ss.cc` e tre gruppi di due cifre separati da spazi (`00 01 73` = mm ss cc), convertendoli in secondi.
- [ ] Qualsiasi altro valore, in particolare quelli con più gruppi di cifre non riconducibili alle forme sopra, produce `N/A`: mai un numero ricavato attaccando cifre separate.
- [ ] Tempo pari a 0 → `N/A`; portata calcolata solo con `water_flow_rate_validated = valid`; arrotondamento a 3 decimali (comportamenti attuali mantenuti).

**Test**
- [ ] Test unitari del parser con un data provider che contiene **tutti i valori non numerici reali** di `range_volume` e `range_time` presenti nel DB locale (62 forme distinte), ciascuno con il risultato atteso scritto a mano. Un nuovo caso trovato in futuro si aggiunge a questa tabella.
- [ ] Test unitari del calcolo: lettura da `properties.form`, fallback su `raw_data`, precedenza di `properties` quando la chiave è in entrambi, stato non valid → `N/A`, tempo 0 → `N/A`.
- [ ] Verifica manuale in Nova sulle schede locali 8621 (attesa 0.067), 8619 (0.1), 4566 (0.338) e su una scheda con valori puliti in `raw_data`, che non deve cambiare.

## Rischi

- **Record 10296 di produzione non verificato:** i suoi valori grezzi non sono noti. Mitigazione: dopo il deploy, aprire la scheda in produzione e controllare il valore; in caso di `N/A`, leggere i valori grezzi e aggiungerli al data provider.
- **Valori oggi «numerici» che diventano `N/A`:** casi come `0 45` o `1 litro2` oggi mostrano un numero (sbagliato), dopo mostrano `N/A`. È voluto: il numero precedente non era attendibile.
- **Il campo Nova salva a ogni apertura:** `calculateFlowRate()` scrive il risultato in `raw_data.flow_rate` con `saveQuietly()` ogni volta che la scheda viene aperta (comportamento attuale, non modificato). Dopo il deploy, il valore salvato si corregge solo per le schede aperte almeno una volta; il resto rimane com'era fino all'intervento sugli export (ticket separato).
- **Interpretazione di `:` e di `'`:** `1:30` è letto come 1 minuto e 30 secondi; `11'` (un apice solo) è letto come 11 secondi, come faceva il vecchio calcolo: con i volumi registrati 11 minuti non sono plausibili.

## Out of scope

Da tracciare in un ticket separato («Export sorgenti allineati ai dati wm-package»), perché riguarda gli export e non la scheda Nova:

- 433 schede `water` validate con `form_id` vuoto escluse da GeoJSON, GPX, KML e shapefile (`SourceSurveyController.php:90-95`, filtro `where('form_id', 'water')`).
- `range_volume` negli export letto solo da `raw_data`.
- La portata esportata sotto l'etichetta `range_time` (`SourceSurveyController.php:111, 144, 175, 187`).
- `raw_data.flow_rate` salvato e non aggiornato, letto dagli export e dall'overlay (`SourceSurveyController.php:343`): ricalcolo al salvataggio e comando di ricalcolo dei dati esistenti.

Inoltre:
- Validazione lato app/form dei valori inseriti (impedire il testo alla fonte).
- Valori in linguaggio naturale («Mezzo secondo», «1 secondo e 50 cent»): restano `N/A`.

## Moduli toccati

Tutto nel repo principale `osm2cai2` (codice custom, nessuna modifica a `wm-package`):

- `app/Models/UgcPoi.php` — lettura di volume e tempo da `properties.form` con fallback su `raw_data`, calcolo puro separato dal salvataggio, rimozione di `formatNumericValue()`
- `app/Services/FlowRateParser.php` (nuovo) — parser conservativo di volume e tempo
- `tests/Unit/Services/` e `tests/Unit/Models/` — test del parser e del calcolo della portata
