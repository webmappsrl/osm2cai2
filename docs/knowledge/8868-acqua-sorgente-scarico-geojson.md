# GeoJSON pubblico delle sorgenti

## Come funziona oggi

`/api/v2/source_survey/survey.geojson` (`SourceSurveyController::surveyGeoJson()`) è il file che il cliente Acqua Sorgente scarica con i suoi script. Pubblico, senza autenticazione né cache: si ricalcola a ogni chiamata.

- **Quali rilievi:** quelli con `validated = 'valid'` e form `water`, selezionati con lo scope `UgcPoi::forForm('water')`, lo stesso che usa l'elenco Nova (`AbstractValidationResource::indexQuery()`). Lo scope prende `form_id = 'water'` **oppure** `properties->form->id = 'water'`: dalla sera del 27/10/2025 l'app salva il form solo in `properties.form.id` e lascia `form_id` vuoto. Una query che filtra solo su `form_id` perde tutti i rilievi recenti.
- **Valori di misura** (`active`, `temperature`, `conductivity`, `range_volume`): `UgcPoi::getFormValue()`, cioè `properties.form` e, se vuoto, `raw_data`. `properties.form` è ciò che Nova mostra e permette di modificare nel pannello «Form» della scheda; `raw_data` è la copia dell'app vecchia e nei rilievi recenti contiene solo `flow_rate`. Un valore vuoto diventa `N/A`. `info` è `description`, poi `info` del form.
- **Portata** (`range_time`, nome storico): `computeFlowRate()`, lo stesso calcolo di Nova, senza salvare. Regole in [calcolo-della-portata-delle-sorgenti.md](calcolo-della-portata-delle-sorgenti.md).
- **Data della misura** (`date`): `UgcPoi::getSurveyDate()`, ISO 8601 in UTC. Fonti in ordine: `properties.createdAt` (data del dispositivo, app attuale), `raw_data.date` (data del dispositivo, app vecchia), `created_at` (arrivo sul server, di solito qualche giorno dopo: scarto mediano 1,6 giorni, 8% oltre un mese). Stringhe vuote o non ISO si saltano: `Carbon::parse('')` darebbe «adesso» e `11/08/2024` l'8 novembre. `created_at` è nell'ora italiana (`config/app.php`, `timezone`) e viene convertito.

Vincoli da sapere:

- Gli altri formati dello stesso controller non sono allineati: gpx, kml e `overlay.geojson` usano lo stesso elenco di rilievi ma leggono i valori solo da `raw_data` (per i rilievi recenti escono `N/A`); lo shapefile ripete `AND form_id = 'water'` nella sua query e non ha i rilievi recenti; il popup dell'overlay calcola la data per conto suo.
- Il campo Nova «Registrato il» (`UgcCommonFieldsTrait::getRegisteredAtAttribute()`) usa un altro ordine (`properties.date`, `properties.createdAt`, `created_at`) e non legge `raw_data.date`: per i rilievi dell'app vecchia mostra la data di arrivo sul server, mentre il GeoJSON mostra quella della misura.
- Filtrano ancora solo su `form_id`: `/api/v2/source_survey/monitorings`, la card della dashboard Acqua Sorgente, la metrica `AcquaSorgenteTrend`, il comando `ConvertValidatedWaterUgcPoisToEcPois`.
- `elevation` viene da `raw_data.position.altitude`: per i rilievi recenti è sempre `N/A`.

## Perché così

- **Stesso scope di Nova** (oc:8868): il cliente contava i validi in Nova (~2900) e le feature del file (2107) e non tornavano. Con la condizione in un solo punto, elenco Nova e file non possono più divergere.
- **`properties.form` prima di `raw_data`, anche per i rilievi vecchi** (oc:8868): è la copia che si corregge in Nova; il file deve dire ciò che il cliente vede in Nova. Al passaggio sono cambiati valori di rilievi già pubblicati (es. conducibilità 1500 → 150).
- **`properties.createdAt` prima di `raw_data.date`** (oc:8868, Alessandro Peci): è la fonte che i rilievi nuovi hanno sempre. L'ordine conta solo per 3 rilievi importati il 25/09/2025 che hanno entrambe.
- **Ripiego su `created_at` invece di una data vuota** (oc:8868): per serie stagionali una data approssimata di qualche giorno serve più di un rilievo che non si colloca nel tempo.
- **Tutto in UTC** (oc:8868): `created_at` è in ora italiana, le altre fonti in UTC; senza normalizzare, i giorni vicini alla mezzanotte slitterebbero negli script che leggono i primi 10 caratteri.
- **Gli altri formati e «Registrato il» non allineati** (oc:8868): non richiesti dal cliente in questo ticket. L'allineamento di GPX, KML e shapefile è il ticket separato previsto nel Follow-up di [oc:8879](../features/8879-acqua-sorgente-calcolo-portata/notes.md).

## Come ci siamo arrivati

- **Data da `raw_data.date` poi `created_at`** (superato, oc:8868): è la regola del popup dell'overlay e di `UgcCommonModelTrait::getRegisteredAtAttribute()`. Ignora `properties.createdAt`, unica data vera dei rilievi dell'app attuale.
- **Data vuota quando manca quella del dispositivo** (scartata, oc:8868): proposta per non dare date sbagliate; scartata perché lo scarto di `created_at` è per lo più entro la settimana.
