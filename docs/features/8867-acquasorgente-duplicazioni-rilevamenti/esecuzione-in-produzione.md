# oc:8867 — scaletta di esecuzione in produzione

Ogni passo si lancia **da solo**, si guarda l'esito e solo allora si passa al successivo. Dove c'è
scritto **STOP** ci si ferma e si decide insieme: nessun passo successivo parte in automatico.

Server: `netseven@195.231.60.153` (Aruba, infrastruttura CAI), progetto in `/var/www/html/osm2cai2`,
container php `php81-osm2cai2`, container database `postgres-osm2cai2`. Il demone docker vuole `sudo`.

Abbreviazioni usate qui sotto:

```bash
ssh netseven@195.231.60.153
cd /var/www/html/osm2cai2

# artisan
sudo docker exec -it php81-osm2cai2 php artisan <comando>

# sql
sudo docker exec -i postgres-osm2cai2 psql -U osm2cai2 -d osm2cai2 -c "<query>"
```

I numeri attesi vengono dal collaudo sul dump del 27/10/2026. In produzione saranno **vicini ma non
identici**: dal giorno del dump sono arrivati altri rilevamenti.

---

## Fase A — portare il codice in produzione (app su)

### A1. PR, revisione, merge
Niente comandi qui: si apre la PR dal branch `feature/oc-8867-duplicazioni-rilevamenti-ugc`, si fa
rivedere, si fa merge.

### A2. Deploy sul server
Come da prassi del progetto. Al termine, controllo che i command esistano davvero:

```bash
sudo docker exec -it php81-osm2cai2 php artisan list | grep osm2cai:
```

Attesi fra gli altri: `osm2cai:backfill-ugc-media-hash`, `osm2cai:recover-previous-duplicate-fix`,
`osm2cai:fix-duplicated-ugc`. **Non** deve più comparire `osm2cai:fix-duplicated-ugc-pois` (eliminato).

**STOP.** Se manca anche solo uno dei tre, il deploy non è andato: non si prosegue.

### A3. Migration
```bash
sudo docker exec -it php81-osm2cai2 php artisan migrate
```

Crea `ugc_duplicates_archive`. Verifica:

```sql
select count(*) from ugc_duplicates_archive;   -- atteso: 0
```

### A4. Stato di partenza (da conservare)
Questi numeri servono dopo, per il confronto:

```sql
select
  (select count(*) from ugc_pois)   as ugc_pois,
  (select count(*) from ugc_tracks) as ugc_tracks,
  (select count(*) from ec_pois)    as ec_pois,
  (select count(*) from media where model_type in ('App\Models\UgcPoi','App\Models\UgcTrack')) as media_ugc,
  (select count(*) from ugc_pois where properties ? 'duplicated') as copie_marcate,
  (select count(*) from users where email = 'duplicated-ugc-pois@webmapp.it') as utente_servizio;
```

Riferimento dal dump: `ugc_pois` 10.002, `ugc_tracks` 1.616, `ec_pois` 19.548, media UGC 13.087,
copie marcate 660, utente di servizio 1 (id `7736`).

---

## Fase B — gli hash delle fotografie (app su)

### B1. Quanti media sono senza hash
```bash
sudo docker exec -it php81-osm2cai2 php artisan osm2cai:backfill-ugc-media-hash
```

Senza `--execute` conta soltanto. Atteso: **~1.580**, non tutti i 13.087 media UGC — il command guarda
solo i rilevamenti che la bonifica confronta: i gruppi di duplicati (per uuid e per istante) e le copie
lasciate dalla vecchia bonifica con i loro padri. Sul dump erano esattamente 1.583.

### B2. Calcolo degli hash
```bash
sudo docker exec -it php81-osm2cai2 php artisan osm2cai:backfill-ugc-media-hash --execute
```

Legge quelle fotografie da Aruba per calcolarne lo sha256: **~10 GB, una ventina di minuti** (i media
coinvolti pesano in media 6,3 MB l'uno). Non tocca UgcPoi, UgcTrack, EcPoi: scrive solo
`custom_properties.sha256` sui media, quindi l'app può restare su.

Un media il cui file non si legge finisce fra i «falliti» e **resta senza hash**: più avanti verrà
spostato sul padre e mai scartato. Un errore di lettura non si trasforma mai in una cancellazione —
provato in locale lasciando di proposito un gruppo senza file (vedi notes.md, collaudo delle fotografie).

Al termine stampa `Hash calcolati: N. Falliti: M.`

**STOP se M è alto.** Qualche fallimento è fisiologico (file mancante su Aruba); molti significano un
problema di accesso allo storage, e senza hash la bonifica non saprebbe riconoscere i doppioni.

### B3. Controllo del file vuoto
```sql
select count(*) from media
where model_type in ('App\Models\UgcPoi','App\Models\UgcTrack')
  and custom_properties->>'sha256' = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
```

Deve essere **0**. Se non lo è, quei media hanno file vuoto e verrebbero presi per doppioni fra loro:
**STOP**, si rivede lo scarto prima di andare avanti.

---

## Fase C — la finestra di fermo (app giù)

### C1. Dry-run della sanatoria, ad app ancora su
```bash
sudo docker exec -it php81-osm2cai2 php artisan osm2cai:recover-previous-duplicate-fix
```

Si leggono i numeri e si confrontano con il collaudo: ~660 copie marcate, ~462 record con chiavi
ereditate, l'utente di servizio con le sue copie. **STOP**: i numeri si guardano insieme.

### C2. Dry-run della bonifica
```bash
sudo docker exec -it php81-osm2cai2 php artisan osm2cai:fix-duplicated-ugc
```

**Qui è atteso un errore**, ed è giusto così: finché esistono righe con `properties->duplicated` la
guardia blocca il command. Serve a confermare che la guardia è viva. Il dry-run vero si fa dopo C5.

### C3. App giù
```bash
sudo docker exec -it php81-osm2cai2 php artisan down
```

Da qui in avanti l'app mobile riceve 503 e nessun rilevamento nuovo entra. Da C3 a C9 si lavora senza
interruzioni: più la finestra è breve, meno rilevamenti vanno in errore.

### C4. Backup su Aruba
```bash
sudo docker exec -it php81-osm2cai2 php artisan wm:backup-run --only-db
```

~12 minuti, ~1,7 GB. Verifica che sia arrivato:

```bash
sudo docker exec -it php81-osm2cai2 php artisan tinker --execute="\$d=Storage::disk('wmdumps'); \$f=collect(\$d->files('osm2cai2'))->last(); echo \$f.' '.date('Y-m-d H:i', \$d->lastModified(\$f));"
```

**STOP se non c'è un file con l'ora di adesso.** Senza backup non si tocca niente.

### C5. Copia sul server
```bash
sudo docker exec -it php81-osm2cai2 php artisan wm:download-db-backup --latest
sudo docker exec -it php81-osm2cai2 cp storage/backups/last_dump.sql.gz storage/backups/pre-oc8867.sql.gz
sudo docker exec -it php81-osm2cai2 ls -lh storage/backups/
```

`cp` e non `mv`: `last_dump.sql.gz` resta dov'è per chi se lo aspetta (lo schedulato delle 20:10 lo
riscrive comunque ogni sera, ed è per questo che ne teniamo una copia con un altro nome).

**STOP se `pre-oc8867.sql.gz` non c'è o pesa molto meno di 1,7 GB.**

### C6. Sanatoria
```bash
sudo docker exec -it php81-osm2cai2 php artisan osm2cai:recover-previous-duplicate-fix --execute
```

Sposta le fotografie sui record tenuti, archivia e cancella le copie marcate, ripulisce le chiavi
ereditate ed elimina l'utente `7736`. Attesi: ~660 copie trattate, ~462 record ripuliti, utente
eliminato. **STOP** se compaiono righe «da revisionare» in numero diverso dal collaudo.

### C7. Dry-run della bonifica, ora che la guardia non blocca più
```bash
sudo docker exec -it php81-osm2cai2 php artisan osm2cai:fix-duplicated-ugc
```

Ora deve partire. Attesi ~192 gruppi, ~252 record in eccesso, e la stima delle fotografie: **~592 da
scartare perché identiche, ~175 da spostare**. Il report finisce in `storage/backups/fix-duplicated-ugc-*.csv`.

**STOP**: questi numeri si leggono insieme prima di eseguire.

### C8. Bonifica
```bash
sudo docker exec -it php81-osm2cai2 php artisan osm2cai:fix-duplicated-ugc --execute
```

### C9. App su
```bash
sudo docker exec -it php81-osm2cai2 php artisan up
```

Da qui l'app riprende a ricevere rilevamenti. Le verifiche della fase D si fanno con l'app su.

---

## Fase D — verifiche (app su)

### D1. Confronto con lo stato di partenza
Stessa query di A4. Attesi rispetto a prima:

| | atteso |
|---|---|
| `ugc_pois` | ~832 in meno |
| `ugc_tracks` | ~75 in meno |
| `ec_pois` | **identico** (la bonifica non crea né modifica EcPoi) |
| media UGC | ~592 in meno (le fotografie doppione), nessun altro perso |
| copie marcate | 0 |
| utente di servizio | 0 |
| `ugc_duplicates_archive` | ~907 righe |

### D2. Nessun media orfano
```sql
select count(*) from media m
where m.model_type in ('App\Models\UgcPoi','App\Models\UgcTrack')
  and not exists (select 1 from ugc_pois p where p.id = m.model_id and m.model_type = 'App\Models\UgcPoi')
  and not exists (select 1 from ugc_tracks t where t.id = m.model_id and m.model_type = 'App\Models\UgcTrack');
```

Deve essere **0**.

### D3. Il caso del ticket
```sql
select id, properties->>'uuid' as uuid from ugc_pois where id in (10828, 10830);
select original_id, reference_id from ugc_duplicates_archive where original_id = 10830;
```

Atteso: `10828` esiste con le sue fotografie, `10830` non c'è più ed è in archivio con
`reference_id = 10828`. (Se nel frattempo in produzione gli id fossero altri, si cerca per uuid.)

### D4. Record senza uuid
```bash
sudo docker exec -it php81-osm2cai2 php artisan osm2cai:fix-duplicated-ugc --by-timestamp
```

Solo dry-run. Nel collaudo trovava **0 gruppi** e ~1.973 record non valutabili: se anche qui è 0, il
passo 5 non serve e si chiude qui. Se trova gruppi, si guardano uno per uno prima di decidere.

---

## Se qualcosa va storto

Il ripristino si fa da `storage/backups/pre-oc8867.sql.gz` con
`wm-package/src/Scripts/restore_db.sh`, ad app giù. Riporta il database al momento esatto prima della
fase C, quindi si perdono solo i rilevamenti entrati dopo — che durante la finestra di fermo sono zero.

`ugc_duplicates_archive` conserva contenuto, geometria e id dei media di ogni copia cancellata, ma non
rimette in piedi le righe: per quello serve il backup.
