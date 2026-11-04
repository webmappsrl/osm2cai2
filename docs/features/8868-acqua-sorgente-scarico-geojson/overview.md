> Ticket: oc:8868

# Acqua Sorgente - Scarico GeoJson

# Ciclo 2 — GeoJSON delle sorgenti: tutti i rilievi validati e data della misura (04/11/2026)

## Cosa cambia

Il file `/api/v2/source_survey/survey.geojson` cambia in due punti:

1. **Restituisce tutti i rilievi validati che Nova mostra come validi.** Oggi prende solo quelli con `form_id = 'water'`; dopo prende anche quelli con `form_id` vuoto e `properties.form.id = 'water'`, cioè tutti i rilievi inviati dall'app dalla sera del 27/10/2025. I valori di misura di questi rilievi (temperatura, conducibilità, volume, `active`, `info`, portata) si leggono da `properties.form`, dove l'app li salva.
2. **Ogni sorgente ha il campo `date`**: la data e l'ora in cui è stata fatta la misura.

Per i rilievi che il file restituisce già oggi, i valori diventano quelli che Nova mostra nel pannello «Form»: dove la copia in `raw_data` era diversa il valore cambia: sul DB locale `info` in 894 sorgenti (da `N/A` al testo del form), `temperature` in 30, `range_volume` in 17, `conductivity` e `active` in una ciascuno.

## Perché

### Rilievi mancanti

Il 04/11 alle 14:06 Matteo Nigro, il cliente, ha scritto nella richiesta del ticket: «in questi giorni ho validato parecchi punti e nonostante questo il geojson che si scarica dal link a cui mi riferivo non pare essersi aggiornato. al momento risultano ca. 2900 POI in stato di validazione = valid, tuttavia il geojson arriva solo a 2107».

La causa è il filtro: il file usa `form_id = 'water'` (`app/Http/Controllers/SourceSurveyController.php:92`), Nova `form_id = 'water'` **oppure** `properties->form->id = 'water'` (`app/Nova/AbstractValidationResource.php:22-26`). Sul DB locale (dump fermo al 09/03/2026):

| Rilievi validati | Quanti | Dal | Al |
|---|---|---|---|
| con `form_id = 'water'` (nel file) | 2216 | 28/12/2023 | 27/10/2025 00:38 |
| con `form_id` vuoto e `properties.form.id = 'water'` (esclusi) | 434 | 27/10/2025 19:01 | 09/03/2026 |

In produzione la differenza è di circa 800 (2895 validi in Nova, 2107 nel file). Nella call del pomeriggio del 04/11 Alessandro Peci ha deciso di correggerlo in questo ciclo: le feature del GeoJSON devono essere le stesse che si vedono validate in Nova, con i dati conformi a come Nova li mostra.

Nei 434 rilievi esclusi `raw_data` contiene solo `flow_rate`: temperatura, conducibilità, volume, tempo, `active` e `info` sono in `properties.form`. Leggendo solo `raw_data` il file mostrerebbe `N/A`.

### Data della misura

Il 03/11 alle 17:27 Matteo Nigro ha aggiunto nella richiesta del ticket: «Servirebbe che nel file geojson ci fosse anche la data di registrazione, altrimenti è difficile gestire le serie di dati nel tempo». Allo scrum del 04/11 Alessio Piccioli ha deciso di farla («quel dato lì è fondamentale […] facciamolo assolutamente sì») e Giuseppe Bonfanti ha chiesto di farla prima di riprendere l'errore dell'export («concentrati su inserire la data di registrazione […] appena l'hai fatto vediamo assieme il problema del che dà errore»).

Interpretazione, scritta nelle note dev del ticket e letta da Alessandro nella call del 04/11:

- **file:** `survey.geojson`, l'unico GeoJSON che il cliente cita e usa; l'export Nova produce Excel/CSV;
- **data:** quella della misura, non quella di arrivo nel DB, perché serve a ordinare nel tempo più misure della stessa sorgente.

### Da dove si prende la data (verificato sul DB locale)

Oggi il file non espone nessuna data (`app/Http/Controllers/SourceSurveyController.php:107-117`).

- `properties.createdAt`: data del dispositivo al momento del rilievo, salvata dall'app attuale (millisecondi, es. `2026-03-09T09:05:26.687Z`).
- `raw_data.date`: la stessa data, salvata dall'app vecchia. Da giugno 2025 compare solo in 5 rilievi. `properties.form.date`, quando c'è, coincide con `raw_data.date`.
- `created_at`: momento di arrivo nel DB (invio dall'app o importazione). Sui 973 rilievi in cui si può confrontare con `properties.createdAt`, lo scarto mediano è di 1,6 giorni: 67% entro una settimana, 25% fra 8 e 30 giorni, 8% oltre un mese.

**Ordine** (deciso con Alessandro Peci, call del 04/11: «inverti solo quei due […] adesso tanto l'app la mette lì»): `properties.createdAt`, poi `raw_data.date`, poi `created_at`. L'ordine dei primi due conta solo per i 3 rilievi che le hanno entrambe (7252, 7253, 7254, importati il 25/09/2025, con `createdAt` a microsecondi zero): nel 7252 la data passa dal 02/09 al 03/09/2024. Effetto accettato.

**Ripiego su `created_at`** (decisione della dev): meglio una data approssimata di qualche giorno che nessuna data, perché un rilievo senza data non si colloca nella serie. La risposta al cliente dirà che per i rilievi salvati senza la data del dispositivo la data è quella di arrivo sul server.

## Requisiti

- [ ] Il file restituisce i rilievi validati con `form_id = 'water'` oppure `properties.form.id = 'water'`: la condizione è scritta in un solo punto, usato sia da Nova sia dal controller, così i due conteggi non possono più divergere
- [ ] Per ogni rilievo `temperature`, `conductivity`, `range_volume`, `active` e `info` si leggono da `properties.form`, poi da `raw_data` (stesso ordine di oc:8879); `range_time` (la portata) usa il calcolo di oc:8879
- [ ] I valori di misura sono quelli che Nova mostra nel pannello «Form» della scheda (`properties.form`, modificabile), anche per i rilievi già presenti nel file: dove `properties.form` e `raw_data` divergono il valore cambia (decisione della dev, 04/11, dopo la verifica: `info` in 894 sorgenti, altri campi in 49). Un valore vuoto diventa `N/A`
- [ ] Ogni feature ha la property `date`, in ISO 8601 normalizzato in UTC (es. `2024-08-11T12:46:11+00:00`), anche quando viene da `created_at`
- [ ] La data si prende da `properties.createdAt`, poi `raw_data.date`, poi `created_at`, in un solo metodo del modello `UgcPoi` (`getSurveyDate()`)
- [ ] Una data vuota o non interpretabile non fa fallire il file e non diventa «adesso»: si passa alla fonte successiva
- [ ] Test sul metodo della data (tre fonti, data vuota, data non interpretabile) e sulla condizione condivisa (SQL dello scope); il conteggio uguale a Nova si verifica in locale
- [ ] Verifica in locale: numero di feature uguale ai validi di Nova (2650 sul DB locale)

## Rischi

- **Il branch non contiene oc:8879.** È nato prima del merge (`558867f`): la lettura da `properties.form` e il calcolo della portata nuovo sono solo su develop. Prima di scrivere codice va unito develop nel branch.
- **Gli altri formati cambiano solo in parte.** gpx, kml e `overlay.geojson` usano lo stesso metodo che sceglie i rilievi (`getValidatedSurveys()`): dopo la correzione contengono anche i rilievi recenti, ma leggono i valori ancora solo da `raw_data`, quindi per quei rilievi temperatura, conducibilità, volume e `active` escono `N/A` e la portata è quella salvata, non ricalcolata. Lo shapefile invece **non** cambia: la sua query ripete `AND form_id = 'water'` (`SourceSurveyController.php:246`) e resta con i soli rilievi vecchi. Non richiesti dal cliente: restano così (vedi Out of scope).
- **Data diversa da «Registrato il» di Nova** (decisione della dev, 04/11, dopo la review). La scheda Nova ha già un campo «Registrato il» (`app/Traits/Nova/UgcCommonFieldsTrait.php:117-119`, calcolato alle righe 259-271 con `properties.date` → `properties.createdAt` → `created_at`, senza `raw_data.date`). Per gli 803 rilievi validati con la sola `raw_data.date` (app vecchia) Nova mostra la data di arrivo sul server, il GeoJSON quella della misura: per esempio il rilievo 1842 ha 11/08/2024 nel GeoJSON e 12/08/2024 in Nova. La differenza è voluta: il GeoJSON è più vicino alla misura, e cambiare Nova non è stato chiesto.
- **Il file diventa più lento.** Da 2216 a circa 2650 feature in locale (circa 2900 in produzione), con una query per feature per la geometria. Prima della modifica 1,6-2,3 s in locale a seconda della misura; dopo 2,05 s.
- **Ripiego su `created_at`:** per circa un rilievo su tre la data può distare più di una settimana dalla misura, e il cliente non lo distingue. Mitigato con la frase nella risposta al cliente.
- **Nome del campo e significato.** Una volta pubblicato finisce negli script del cliente: cambiarli dopo il rilascio rompe chi lo legge.
- **Fusi orari misti.** `created_at` è salvato nell'ora italiana, le altre fonti in UTC: senza normalizzare, i giorni vicini alla mezzanotte slitterebbero. Per questo il requisito chiede tutto in UTC.

## Out of scope

- L'errore 500 dell'export Nova e la scelta fra A, B e C del ciclo 1: si riprendono con Giuseppe dopo questo ciclo
- Il link GeoJSON con tutti i rilievi indipendentemente dalla validazione
- La data negli altri formati (gpx, kml, shp)
- I valori dei rilievi recenti in gpx, kml e `overlay.geojson`, che leggono solo `raw_data` (escono `N/A`), e lo shapefile, che filtra ancora solo su `form_id` (`SourceSurveyController.php:246`). Non richiesti
- Il campo «Registrato il» di Nova, che calcola la data con un altro ordine di fonti (vedi Rischi). Non richiesto
- Gli altri conteggi che filtrano ancora solo su `form_id = 'water'`: card della dashboard Acqua Sorgente (`app/Helpers/Nova/DashboardCardsHelper.php:290`), metrica `app/Nova/Metrics/AcquaSorgenteTrend.php:18`, comando `app/Console/Commands/ConvertValidatedWaterUgcPoisToEcPois.php:57`. Non richiesti
- `elevation` sempre `N/A` per i rilievi recenti (la quota non è in `raw_data.position`). Non richiesto
- La data nel popup di `overlay.geojson` (`formatSurveyData`, `SourceSurveyController.php:339`), che la calcola per conto suo (`raw_data.date`, poi `created_at`): per i rilievi dell'app attuale mostrerà una data diversa dal GeoJSON (per il 7253, 25/09/2025 contro 04/08/2024). Non richiesto; il popup non risulta usato da nessuna configurazione (domanda 1 di oc:8879)
- Il conteggio di `/api/v2/source_survey/monitorings` (`SourceSurveyController.php:284`), che filtra ancora solo su `form_id = 'water'`. Non richiesto
- La query per feature della geometria (`SourceSurveyController.php:118`), salvo che la nuova misura dei tempi la renda necessaria
- Un identificativo stabile della sorgente per raggruppare le misure: non chiesto dal cliente

## Moduli toccati

Tutto nel repo principale `osm2cai2`, nessuna modifica a `wm-package`:

- `app/Models/UgcPoi.php` — scope `forForm()`, `getFormValue()` (valori del form, `properties.form` poi `raw_data`), `getSurveyDate()` (data della misura)
- `app/Nova/AbstractValidationResource.php` — la condizione sul form diventa riusabile (es. scope sul modello)
- `app/Http/Controllers/SourceSurveyController.php` — `getValidatedSurveys()` con la condizione di Nova; `createFeatures()` con i valori dal form e la property `date`
- `tests/Unit/Models/UgcPoiSurveyDateTest.php`, `tests/Unit/Models/UgcPoiFormTest.php` — test della data, dello scope e di `getFormValue()`

# Ciclo 1 — Analisi dell'export Nova (03/11/2026)

Analisi della situazione e proposta di soluzione per l'export completo dei rilievi Acqua Sorgente. Il titolo del ticket parla di GeoJSON, ma il lavoro riguarda l'azione Nova «Esporta in» (Excel/CSV). Il GeoJSON pubblico (`/api/v2/source_survey/survey.geojson`) resta com'è, cioè con i soli rilievi validati.

## Cosa cambia

Per ora non cambia codice. Questo documento:

1. raccoglie quello che si sa oggi del 500 sull'export completo in produzione;
2. separa i fatti verificati dalle ipotesi;
3. propone come arrivare alla causa e quali correzioni corrispondono a ciascuna causa possibile;
4. segnala due decisioni che spettano a Giuseppe prima di scrivere codice.

Le correzioni si scrivono solo dopo che la proposta è stata accettata e le misure hanno indicato la causa.

## Perché

### La richiesta

Il cliente vuole scaricare tutti i rilievi Acqua Sorgente, qualunque sia lo stato di validazione. Allo scrum del 28/10 si è deciso di non fare un nuovo link GeoJSON e di indicare l'export Nova già esistente. In produzione però quell'export, con tutti i rilievi selezionati (circa 4143), risponde 500 dopo circa 30 secondi. Con circa 3000 rilievi funziona, e in locale funziona con i dati locali. Al cliente è stato detto di esportare in due parti.

Allo scrum del 02/11 Giuseppe ha indicato come causa probabile la memoria PHP («va settato un parametro nella action dove gli ci serve più memoria»).

### Cosa fa l'export (verificato nel codice)

Una richiesta di export completo passa per questi passi, tutti nella stessa richiesta HTTP:

1. **Caricamento dei rilievi.** Nova carica tutti i rilievi selezionati prima di chiamare l'azione, a blocchi di 10000 (`ExportTo::$chunkCount`, `wm-package/src/Nova/Actions/ExportTo.php:48`), quindi in pratica tutti insieme.
2. **Controllo dei permessi**, un rilievo alla volta, con la policy `update` dell'utente che lancia l'export.
3. **Generazione del file** in `ExportUgcPoiTo::handle()` (`app/Nova/Actions/ExportUgcPoiTo.php`). L'exporter lavora su una collection già in memoria (`FromCollection`, senza blocchi) e scrive il file sul disco `public`.
4. **Action event di Nova.** Nova scrive in `action_events` una riga per ogni rilievo esportato, a gruppi di 50. Poi, **per ogni rilievo**, esegue una `DELETE` con subquery per tenere solo gli ultimi 25 eventi di quel rilievo (`ActionEvent::prune`, `vendor/laravel/nova/src/Actions/ActionEvent.php:343-358`). Con 4143 rilievi sono 4143 `DELETE`. Né `ExportTo` né `ExportUgcPoiTo` disattivano gli action event.

Tutto il passo 4 avviene in un'unica transazione.

### Cosa dicono i dati

- **Memoria: ipotesi debole.**
  - Il pool PHP-FPM del repo fissa `php_admin_value[memory_limit] = 2048M` (`docker/configs/phpfpm/www.conf:541`). Il valore c'è dal commit `bf6697df` (23/01/2025, oc:4643).
  - L'immagine di produzione è stata ricostruita il 22/10 (oc:8840), prima della prova fallita del 28/10. Quindi la produzione gira con 2048M.
  - In locale, 3140 rilievi arrivano a circa 380 MB di picco nella sola generazione del file.
  - Per esaurire 2048M con 4143 rilievi, il consumo per rilievo dovrebbe essere circa quattro volte quello misurato.
- **Tempo: ipotesi più probabile, ma la causa del superamento non è nota.**
  - L'errore arriva sempre dopo circa 30 secondi.
  - Il passo 4 cresce con il numero di rilievi, e anche con la dimensione di `action_events` in produzione, che non conosciamo.
  - Il passo 2 cresce con il numero di rilievi e dipende dall'utente che lancia l'export.
- **Errore applicativo: da non escludere.** Il ticket parla di **500**, non di 504. Un timeout di nginx o di un proxy di solito risponde 504. Un 500 può essere anche un'eccezione PHP causata da un rilievo con dati malformati, presente fra i circa 1100 rilievi in più rispetto alla prova riuscita.
- **I log di produzione non sono accessibili a chi lavora al ticket**, quindi oggi nessuna delle tre ipotesi si può confermare da lì.

### Due fatti che cambiano il quadro

1. **I rilievi che Nova esporta non sono solo quelli con `form_id = 'water'`.** La risorsa usa `form_id = 'water' OR properties->form->id = 'water'` (`app/Nova/AbstractValidationResource.php:21-26`). Sul DB locale il primo criterio dà 2607 rilievi, il secondo 3140. Qualsiasi misura va fatta con la query della risorsa.
2. **L'export Nova non contiene i dati di misura delle sorgenti.** Il GeoJSON che il cliente usa oggi espone portata (`range_time`), volume (`range_volume`), stato `active`, temperatura, conducibilità e quota (`app/Http/Controllers/SourceSurveyController.php:97-119`). L'export Nova esclude `raw_data` e `properties.form`, e tiene solo `form.info`, `form.conductivity`, `form.temperature` e la posizione (`app/Nova/UgcPoi.php`, `getExcludedKeys` e `getIncludedPropertiesExceptions`). Anche risolto il 500, il cliente riceverebbe un file senza portata, volume e tempo.

### Limiti della verifica in locale

- In locale Nova è servito da `php artisan serve` (`local.compose.yml:173`), non da PHP-FPM, e non c'è nginx. Le misure locali valgono quindi per tempo e memoria dell'applicazione, non per la configurazione del pool né per i timeout del server.
- CPU, disco e carico sono diversi dalla produzione: si confrontano le proporzioni fra i passi e la crescita al variare dei rilievi, non i tempi assoluti.

## Proposta

### Prima decisione: l'export Nova è lo strumento giusto?

Visto che l'export non contiene portata, volume e tempo, va confermato con Giuseppe che risponde a quello che il cliente chiede. Le strade possibili:

- **A — Export Nova così com'è.** Si risolve solo il 500.
- **B — Export Nova con le colonne di misura.** Si aggiungono portata, volume, tempo e stato alle eccezioni di `app/Nova/UgcPoi.php` (modifica piccola, in osm2cai2). La modifica vale per tutte le risorse UGC che ereditano da `UgcPoi`, salvo limitarla a SourceSurvey.
- **C — Riaprire la decisione del 28/10.** Un link GeoJSON con tutti i rilievi, sul modello di quello esistente. Contiene già i campi di misura e non passa da Nova, quindi non ha né il caricamento di Nova né gli action event.

### Seconda decisione: come trovare la causa del 500

La strada più breve e più certa è **leggere il log di produzione all'ora di una prova**: chi ha accesso al server lancia un export completo e legge `storage/logs/laravel.log` e il log di PHP-FPM. Il log distingue subito fra eccezione (con lo stack), memoria esaurita (`Allowed memory size exhausted`) e richiesta interrotta dal server (nessun errore PHP, timeout nel log di nginx).

Se il log non è disponibile, la causa si cerca in locale con i dati di produzione:

1. salvare il DB locale con `pg_dump` su un file;
2. scaricare l'ultimo dump di produzione con `wm:download-db-backup --s3`, perché senza `--s3` in locale il comando legge da MinIO, e ripristinarlo;
3. misurare tempo e picco di memoria di ciascun passo (caricamento, permessi, generazione file, action event con `prune`) dentro la richiesta Nova vera, con log temporanei di tempo e memoria, alle quantità 3000, 3140 e tutti i rilievi;
4. contare le righe di `action_events` del dump, perché da quelle dipende il costo di `prune`;
5. ripristinare il DB locale dal file del punto 1 e cancellare il dump di produzione e i file di export creati.

### Correzione in base alla causa

| Causa trovata | Correzione proposta | Dove |
|---|---|---|
| Eccezione su un rilievo | Correzione puntuale nell'exporter o nei dati del rilievo | `app/Exports/UgcPoiFilteredModelExporter.php` |
| Action event (passo 4 con una quota rilevante del tempo) | Disattivare gli action event sull'export con `withoutActionEvents()`: l'export non modifica dati, quindi una riga per rilievo non serve a nessuno | `app/Nova/UgcPoi.php:84` (vale per tutte le risorse UGC) |
| Memoria | Alzare il limite solo per l'azione di export (`ini_set` in `handle()`), cambiando nel pool `php_admin_value` in `php_value`, che oggi impedisce di modificarlo | `app/Nova/Actions/ExportUgcPoiTo.php`, `docker/configs/phpfpm/www.conf:541`, più ricostruzione dell'immagine sul server |
| Tempo complessivo oltre il timeout del server, senza un passo da alleggerire | Nessuna modifica al codice: alzare il timeout sul server, oppure l'export in coda (file generato da un job e link consegnato a lavoro finito) | server; per l'export in coda, nuovo lavoro da stimare |

Le cause possono sommarsi. La disattivazione degli action event è la correzione più economica e senza effetti negativi per l'export, ma si applica solo se le misure o il log mostrano che serve.

## Requisiti

- [ ] Decisione di Giuseppe su A, B o C
- [ ] Causa del 500 individuata: dal log di produzione, o dalle misure in locale con i dati di produzione
- [ ] Misure e causa registrate in `notes.md`
- [ ] Correzione corrispondente alla causa, e solo quella
- [ ] Verifica in locale che il passo critico misurato sia rientrato, confrontando i valori prima e dopo
- [ ] Verifica in produzione dopo il deploy: export di tutti i rilievi Acqua Sorgente riuscito
- [ ] Esito comunicato al cliente

## Rischi

- **La causa vera è sul server e il locale non la può mostrare.** Un timeout di nginx o di un proxy si deduce dalle misure, non si vede. Per questo la proposta mette al primo posto il log di produzione.
- **Il dump di produzione contiene dati personali.** Resta solo sul disco della sviluppatrice e si cancella dopo le misure. In `storage/backups/` c'è già un `last_dump.sql.gz` da 800 MB del 10/03/2026, che `wm:download-db-backup` sovrascriverebbe: va cancellato anche quello o spostato prima.
- **`wm:restore-db` cancella il DB locale prima di ripristinare** e legge solo `storage/backups/last_dump.sql.gz`: senza il `pg_dump` del punto 1 i dati locali usati per altri ticket (es. oc:8879) vanno persi.
- **Elasticsearch locale.** Ricreare i container avvia `scout-init`, che reindicizza i dati di produzione in Elasticsearch locale.
- **Correzione sulla memoria:** funziona solo dopo la ricostruzione dell'immagine PHP sul server e allenta il blocco sul `memory_limit` del pool. Va scelta solo se le misure la dimostrano, e annotata nella pagina di conoscenza.
- **File di export rimasti.** Gli export falliti o mai scaricati restano sul disco `public` (quelli scaricati vengono cancellati). Vale anche in produzione.

## Out of scope

- Modifiche a `wm-package` (`ExportTo`, `ModelExporter`), compreso l'export a blocchi con `FromQuery`
- La route `/logs` (`routes/web.php:29`) è fuori dal gruppo `auth`/`nova` e sembra rendere leggibili i log a chiunque: va trattata in un ticket separato
- Test automatici sull'export

## Moduli toccati

Nessuno in questa fase: il documento è un'analisi. Dopo le decisioni, i file possibili sono quelli della tabella «Correzione in base alla causa», più `app/Nova/UgcPoi.php` se si sceglie la strada B, oppure `app/Http/Controllers/SourceSurveyController.php` e `routes/api.php` se si sceglie la C.
