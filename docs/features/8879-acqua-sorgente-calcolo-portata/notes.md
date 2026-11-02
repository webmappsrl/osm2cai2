> Ticket: oc:8879

# Notes — Acqua Sorgente - Calcolo portata

## Divergenze dal piano, task per task

### Task 1 e 2 — apice singolo e ™ come secondi

Il piano classificava `11'` e `6™` come tempi non interpretabili (`null`). Il confronto fra la portata salvata e quella nuova su tutte le 2006 schede validate del DB locale ha mostrato che erano gli unici due casi, oltre a quelli voluti, in cui una portata oggi visibile diventava `N/A`:

| Scheda | Volume | Tempo | Prima | Con il piano |
|---|---|---|---|---|
| 985 | `0,75` | `11'` | 0.068 | N/A |
| 1091 | `1 litro` | `6™` | 0.167 | N/A |

Con quei volumi 11 minuti non sono plausibili, e `™` è con ogni probabilità `"` digitato male dalla tastiera del telefono. Su decisione del dev, `'` e `™` sono accettati come «secondi»: le due schede mantengono la portata di prima.

## Bug trovati

- Oltre al caso del ticket (125 schede da N/A a numero, 122 per i dati in `properties.form` e 3 per il nuovo parser), il parser precedente produceva numeri sbagliati già salvati: 9 schede cambiano valore, per esempio 4566 da 337.838 a 0.338 L/s (`500ml`) e 4505 da 0.003 a 0.296 (`00 02 53`).

## Decisioni

- Scope limitato alla scheda Nova (overview, sezione Perché, con citazione della call del 28/10 08:25). Gli export restano in un ticket separato.
- Parser in una classe separata (`App\Services\FlowRateParser`) per poterlo testare senza scrivere sul DB: `UgcPoi::calculateFlowRate()` fa `saveQuietly()`.
- Test con gli attributi `#[DataProvider]` invece delle annotazioni `@dataProvider` usate in altri test del repo: in PHPUnit 11 le annotazioni sono deprecate.
- Branch allineato a `origin/develop` prima di iniziare (5 commit mancanti, fra cui oc:8867 sulle duplicazioni UGC di AcquaSorgente).
- Tag di ambiente e di contenuto su Orchestrator rimandati su richiesta del dev.

## Confronto sul DB locale (2006 schede `water` validate)

| Esito | Schede |
|---|---|
| Valore invariato (comprese 985 e 1091) | 1639 |
| N/A invariato | 230 |
| Da N/A a numero | 125 |
| Numero corretto | 9 |
| Da numero a N/A (`1 litro2`, `0 45`, `7s 80`) | 3 |

### Schede che passano da un numero a N/A

Sono le uniche tre schede con una portata visibile che dopo il deploy mostrano `N/A`. È voluto: il valore inserito non si interpreta con certezza, e il numero di prima nasceva dal parser vecchio che toglieva i caratteri non numerici e attaccava le cifre rimaste. Gli id sono quelli del dump locale, che è una copia della produzione: dopo il deploy vanno aperte in produzione per controllare che mostrino `N/A` e, se serve, chiedere al rilevatore il valore giusto.

| Scheda | Volume | Tempo | Prima | Dopo | Perché non si calcola |
|---|---|---|---|---|---|
| 1110 | `1 litro2` | `2"` | 6 | N/A | Nel volume c'è un `2` attaccato all'unità: può essere un refuso per `1 litro`, oppure `2 litri`. Il parser vecchio leggeva `12` litri. |
| 1602 | `0 45` | `3` | 15 | N/A | Nel volume due gruppi di cifre separati da uno spazio: può essere `0,45` oppure `45`. Il parser vecchio leggeva `045`, cioè 45 litri. |
| 4669 | `500` | `7s 80` | 0.641 | N/A | Nel tempo l'unità `s` sta in mezzo: può essere `7,80` secondi, ma non è una forma riconosciuta. Il parser vecchio leggeva `780` secondi. Anche il volume `500` senza unità è dubbio: probabilmente sono millilitri. |

## Verifiche (Task 5 e 6)

- Test della portata (`FlowRateParserTest`, `UgcPoiFlowRateTest`): 88 test verdi, Pint senza rilievi.
- Suite `Unit` completa: due esecuzioni interrotte da riavvii dei container. Nella parte eseguita falliscono 2 test di `ManageHikingRouteValidationActionTest`, che si aspettano testi in italiano e ricevono quelli in inglese: riguardano la validazione dei percorsi, non toccata da questo lavoro.
- Nova locale, scheda 8621: *Flow Rate L/s* mostra 0.067; con *Flow Rate Validation* = invalid il campo diventa `N/A` e torna 0.067 rimettendola su valid. Attenzione a non confondere *Flow Rate Validation* con *Validation Status* (`validated`), che non influisce sulla portata.

Documentazione per chi tocca questo calcolo in futuro: [docs/knowledge/calcolo-della-portata-delle-sorgenti.md](../../knowledge/calcolo-della-portata-delle-sorgenti.md).

## Follow-up

- Ticket separato «Export sorgenti allineati ai dati wm-package»: 433 schede validate con `form_id` vuoto escluse da GeoJSON/GPX/KML/shapefile; `range_volume` letto solo da `raw_data`; portata esportata con l'etichetta `range_time`; `raw_data.flow_rate` salvato e non aggiornato (ricalcolo al salvataggio e comando di ricalcolo).
- Dopo il deploy: aprire la scheda 10296 in produzione e verificare il valore.
- Dopo il deploy: aprire in produzione le schede 1110, 1602 e 4669 e controllare che mostrino `N/A` (vedi [Schede che passano da un numero a N/A](#schede-che-passano-da-un-numero-a-na)).
