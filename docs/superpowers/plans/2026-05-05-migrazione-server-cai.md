# Migrazione Produzione Webmapp → Server CAI

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Copiare il DB di produzione dal server Webmapp (`prod.osm2cai`, 46.224.37.196) al server CAI (`cai.osm2cai`, 209.227.236.177) e cambiare il DNS di `osm2cai.cai.it`. L'applicazione è già installata e funzionante su entrambi i server tramite Docker.

**Architecture:** Spegni Webmapp → `wm:backup-run` carica il dump su S3 → `wm:download-db-backup --latest --s3` scarica sul CAI → `wm:restore-db` → sync bucket rclone → cambio DNS.

**SSH aliases:** `prod.osm2cai` = server Webmapp (produzione attuale) | `cai.osm2cai` = server CAI (nuova produzione)

**Container names:** `php81-osm2cai2` (PHP/artisan) | `postgres-osm2cai2` (PostgreSQL) | `horizon-osm2cai2` (Horizon)

---

## Task 0: Snapshot Hetzner (pre-migrazione)

> Eseguire prima di qualsiasi altra operazione. Non comporta downtime.

- [ ] **Step 1: Crea snapshot del server CAI su Hetzner**

Dal pannello Hetzner Cloud → server `cai.osm2cai` → Snapshots → **Take snapshot**.
Attendere il completamento prima di procedere.

- [ ] **Step 2: Verifica snapshot creato**

Confermare che lo snapshot sia visibile e marcato come completato nel pannello Hetzner.

---

## Task 1: Spegnimento server Webmapp e backup DB su S3

- [ ] **Step 1: Metti Laravel in maintenance mode sul server Webmapp**

```bash
ssh prod.osm2cai "docker exec php81-osm2cai2 php artisan down --secret=migrazione-cai-2026"
```
Verifica su browser: `https://osm2cai.cai.it/` deve mostrare pagina di manutenzione.
Il team può bypassare la manutenzione visitando `https://osm2cai.cai.it/migrazione-cai-2026` (imposta un cookie di bypass).

- [ ] **Step 2: Fix UGC POI duplicati sul server Webmapp**

```bash
ssh prod.osm2cai "docker exec php81-osm2cai2 bash -c 'cd /var/www/html/osm2cai2 && php artisan osm2cai:fix-duplicated-ugc-pois --execute=1'"
```
Attendi il completamento prima di procedere.

- [ ] **Step 3: Stoppa Horizon sul server Webmapp**

```bash
ssh prod.osm2cai "docker exec php81-osm2cai2 php artisan horizon:terminate"
```
Attendi che i job in esecuzione terminino prima di procedere.

- [ ] **Step 3: Crea il backup DB e caricalo su S3**

```bash
ssh prod.osm2cai "docker exec php81-osm2cai2 php artisan wm:backup-run --only-db"
```
Il dump viene compresso con gzip e caricato automaticamente sul disco `wmdumps` (S3). Attendi il completamento.

---

## Task 2: Restore DB sul server CAI

- [ ] **Step 1: Stoppa Horizon sul server CAI**

```bash
ssh cai.osm2cai "docker exec php81-osm2cai2 php artisan horizon:terminate"
```
Attendi che i job in esecuzione terminino prima di procedere.

- [ ] **Step 2: Scarica il dump da S3**

```bash
ssh cai.osm2cai "docker exec php81-osm2cai2 php artisan wm:download-db-backup --latest --s3"
```
Scarica il backup più recente da S3, lo estrae e lo salva in `storage/backups/last_dump.sql.gz`.

- [ ] **Step 3: Restore**

```bash
ssh cai.osm2cai "docker exec php81-osm2cai2 php artisan wm:restore-db"
```
Legge `storage/backups/last_dump.sql.gz`. Gestisce autonomamente drop, chiusura connessioni, decompressione e import.

- [ ] **Step 4: Verifica conteggi**

```bash
ssh cai.osm2cai "docker exec postgres-osm2cai2 psql -U osm2cai2 -d osm2cai2 -c 'SELECT COUNT(*) FROM hiking_routes;'"
ssh prod.osm2cai "docker exec postgres-osm2cai2 psql -U osm2cai2 -d osm2cai2 -c 'SELECT COUNT(*) FROM hiking_routes;'"
```
I numeri devono corrispondere.

- [ ] **Step 5: Pulizia cache Laravel e riavvio Horizon**

```bash
ssh cai.osm2cai "docker exec php81-osm2cai2 php artisan config:clear && docker exec php81-osm2cai2 php artisan cache:clear && docker exec php81-osm2cai2 php artisan horizon"
```

- [ ] **Step 6: Test funzionale su URL temporaneo**

Apri `https://osm2cai.prod.maphub.it/` e verifica login, dati e mappa.

---

## Task 3: Sincronizzazione bucket S3 (AWS wmfe → Aruba wmfe)

> Da lanciare sul server CAI (o da qualsiasi macchina con rclone configurato con entrambi i remote).

- [ ] **Step 1: Sincronizza i bucket**

```bash
nohup rclone copy aws-wmfe:wmfe/osm2cai2 aruba-wmfe:wmfe/osm2cai2 \
  --s3-acl public-read \
  --ignore-times \
  --progress \
  --transfers 16 \
  --checkers 32 \
  --retries 5 \
  --log-file /tmp/rclone-osm2cai2-fixacl.log \
  --log-level INFO > /tmp/rclone-osm2cai2.out 2>&1 &
```
Monitorare con `tail -f /tmp/rclone-osm2cai2-fixacl.log`.

- [ ] **Step 2: Verifica conteggio file**

```bash
rclone size aws-wmfe:wmfe/osm2cai2
rclone size aruba-wmfe:wmfe/osm2cai2
```
Numero di file e dimensione totale devono corrispondere.

---

## Task 4: Sincronizzazione tiles (server tiles → Aruba)

> Da lanciare sul server tiles (`server`, 46.101.124.52). Può girare in parallelo al Task 3.

- [ ] **Step 1: Lancia la sincronizzazione in background**

```bash
ssh server "nohup rclone copy /mnt/volume-fra1-01/tiles aruba-tiles:tiles \
  --s3-acl public-read \
  --ignore-times \
  --progress \
  --transfers 16 \
  --checkers 32 \
  --retries 5 \
  --low-level-retries 10 \
  --log-file /tmp/rclone-tiles-copy.log \
  --log-level INFO > /tmp/rclone-tiles.out 2>&1 &"
```

- [ ] **Step 2: Monitora il progresso**

```bash
ssh server "tail -f /tmp/rclone-tiles-copy.log"
```

- [ ] **Step 3: Verifica completamento**

```bash
ssh server "rclone size /mnt/volume-fra1-01/tiles"
# confronta con:
ssh server "rclone size aruba-tiles:tiles"
```
Numero di file e dimensione totale devono corrispondere.

---

## Task 5: Cambio DNS e finalizzazione

- [ ] **Step 1: Coordinare il cambio DNS con il referente CAI**

I seguenti record A devono essere aggiornati:
- `osm2cai.cai.it` → da `46.224.37.196` a `209.227.236.177`
- `*.osm2cai.cai.it` → da `46.101.124.52` a `209.227.236.177`

- [ ] **Step 2: Aggiorna APP_URL sul server CAI**

```bash
ssh cai.osm2cai "sed -i 's|APP_URL=.*|APP_URL=https://osm2cai.cai.it|' /var/www/html/osm2cai2/.env && docker exec php81-osm2cai2 php artisan config:cache"
```

- [ ] **Step 3: Verifica propagazione DNS**

```bash
dig osm2cai.cai.it +short
```
Atteso: `209.227.236.177`.

- [ ] **Step 4: Verifica SSL sul server CAI**

```bash
ssh cai.osm2cai "certbot certificates | grep osm2cai"
```
Se il certificato non copre `osm2cai.cai.it` (eseguire solo dopo propagazione DNS):
```bash
ssh cai.osm2cai "certbot --apache -d osm2cai.cai.it"
```

- [ ] **Step 5: Test finale**

Apri `https://osm2cai.cai.it/` e verifica login, dati e mappa.

---

## Rollback

Se qualcosa va storto prima del cambio DNS:
```bash
ssh prod.osm2cai "docker exec php81-osm2cai2 php artisan up && docker exec php81-osm2cai2 php artisan horizon"
```
Il DNS non è ancora cambiato, nessun impatto sugli utenti.
