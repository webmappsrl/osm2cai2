> Ticket: oc:8868

# Acqua Sorgente - Scarico GeoJson

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
