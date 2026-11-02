> Ticket: oc:8879

# Acqua Sorgente - Calcolo portata

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
