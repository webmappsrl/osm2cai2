> Ticket: oc:8840

# Messa in sicurezza PHP2Shell sullo shard osm2cai2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Portare i container PHP di osm2cai2 (locale, UAT, prod) alla versione corretta 8.4.27 con pool FPM ripulito, e verificare che i server non siano stati compromessi.

**Architecture:** Due file di configurazione nel repo (`Dockerfile` pin + `www.conf` hardening); il resto è operativo: build `--pull` delle due immagini (`wm-phpfpm:8.4-fpm` per FPM, `wm-phpfpm:8.4` per Horizon) e ricreazione dei container su tre ambienti, in sequenza locale → UAT → prod, con merge su `main` solo alla fine. Verifica di compromissione lato host su UAT e prod.

**Tech Stack:** Docker Compose (`compose.yml` sui server, `local.compose.yml` in locale), PHP-FPM 8.4, Laravel Horizon, Apache host-side.

**Spec:** `docs/features/8840-messa-in-sicurezza-php2shell-shard-osm2cai2/overview.md`

## Global Constraints

- Nessuna operazione git (commit, push, branch, checkout) eseguita senza approvazione esplicita del dev: i comandi git nel piano sono istruzioni testuali.
- Pin esatto `php:8.4.27-fpm` (mai tag mobile).
- Limiti FPM: `upload_max_filesize = 32M`, `post_max_size = 128M`, `session.upload_progress.enabled = off` (decisione dev, valori di camminiditalia).
- Le immagini da ricostruire sono **sempre due**: `wm-phpfpm:8.4-fpm` e `wm-phpfpm:8.4`; la verifica è sempre `php -v` dentro **entrambi** i container, mai l'esito della build.
- Build sempre con `--pull`, e sempre **prima** di fermare i container in esecuzione.
- Su ogni server, prima del rebuild: `docker tag` di entrambe le immagini correnti come `pre-8840`.
- Il submodule `wm-package` non si tocca (oc:8841).
- Sequenza vincolante: locale → UAT → prod; merge su `main` solo dopo la patch dei server.

## Review Focus

- Upload legittimo oltre i nuovi limiti (multi-file Nova / base64 API > 32M/128M): il client deve ricevere un errore gestito, non un worker morto → test oversize nel Task 2.
- Horizon lasciato sull'immagine vecchia (è già successo: su UAT girano 8.4.14 e 8.4.15): `php -v` su entrambi i container in ogni ambiente → Task 2, 3, 4.
- Build senza `--pull` che riusa la base in cache (no-op silenzioso): il comando di build nel piano include sempre `--pull`; la verifica è `php -v`, non l'esito della build → Task 2, 3, 4.
- App lasciata in maintenance mode da un `artisan down` interrotto: dopo ogni ricreazione, curl della home con esito atteso 200/302 → Task 3, 4.
- Falso senso di sicurezza dalla verifica di compromissione: ogni pattern di exploit trovato nei log va controllato per codice di risposta; solo «nessun 2xx» chiude l'esito → Task 5.

---

### Task 1: Modifica repo (pin immagine + hardening pool FPM)

**Files:**
- Modify: `docker/configs/phpfpm/Dockerfile:1`
- Modify: `docker/configs/phpfpm/www.conf:541-543`

**Interfaces:**
- Produces: immagine base pinnata e pool FPM con i valori della Global Constraint; i Task 2–4 buildano da questi file.

- [ ] **Step 1: Pin dell'immagine base nel Dockerfile**

`docker/configs/phpfpm/Dockerfile:1`: `FROM php:8.4-fpm` → `FROM php:8.4.27-fpm`

- [ ] **Step 2: Hardening del pool FPM in `www.conf`**

Alle righe 541-543, lasciare `memory_limit` invariato e portare:

```ini
php_admin_value[upload_max_filesize] = 32M
php_admin_value[post_max_size] = 128M
php_admin_flag[session.upload_progress.enabled] = off
```

(la riga `php_admin_flag` è nuova, aggiunta sotto le altre.)

- [ ] **Step 3: Verifica statica**

Run: `grep -n 'FROM php' docker/configs/phpfpm/Dockerfile && grep -n -E 'upload_max_filesize|post_max_size|upload_progress' docker/configs/phpfpm/www.conf`
Expected: `php:8.4.27-fpm`, `32M`, `128M`, `off`.

- [ ] **Step 4: Commit (istruzione per il dev, dopo il review-gate)**

```bash
git add docker/configs/phpfpm/Dockerfile docker/configs/phpfpm/www.conf
git commit -m "fix(oc:8840): pin php:8.4.27-fpm e hardening pool FPM (PHP2Shell)"
```

### Task 2: Locale — build e verifica

**Files:** nessun file nuovo; usa `local.compose.yml`.

**Interfaces:**
- Consumes: i file del Task 1.
- Produces: conferma che le immagini buildano e i container funzionano — prerequisito per toccare i server.

- [ ] **Step 1: Build con `--pull` delle due immagini**

Run: `docker compose -f local.compose.yml build --pull php horizon`
Expected: build completata per entrambi i servizi (se fallisce su PGDG/pecl, fermarsi: i container vecchi sono ancora su, nessun danno).

- [ ] **Step 2: Ricreazione dei container PHP**

Run: `docker exec php-osm2cai2 php artisan horizon:terminate; docker compose -f local.compose.yml up -d php horizon`
Expected: container ricreati.

- [ ] **Step 3: Verifica versione in entrambi i container**

Run: `docker exec php-osm2cai2 php -v && docker exec horizon-osm2cai2 php -v`
Expected: `PHP 8.4.27` in entrambi.

- [ ] **Step 4: Verifica limiti FPM effettivi**

Run: `docker exec php-osm2cai2 bash -c 'php -i | grep -E "upload_max_filesize|post_max_size|session.upload_progress.enabled"'`
Expected: nota — `php -i` da CLI non legge i `php_admin_value` del pool; la verifica vera è via FPM: `curl -s localhost:8008` con uno script `phpinfo` temporaneo oppure controllare che l'app risponda e che un upload oversize (sotto) dia errore gestito. In alternativa: `docker exec php-osm2cai2 bash -c 'grep -E "upload_max|post_max|upload_progress" /usr/local/etc/php-fpm.d/www.conf'` → valori attesi presenti nel file dentro l'immagine.

- [ ] **Step 5: Smoke test app + upload oversize**

Run: `curl -s -o /dev/null -w '%{http_code}' http://localhost:8008` → atteso 200/302; poi oversize (140MB > post_max_size 128M): `head -c 140000000 /dev/zero > /tmp/big.bin && curl -s -o /dev/null -w '%{http_code}' -H 'Expect:' -F "file=@/tmp/big.bin" http://localhost:8008/ ; rm /tmp/big.bin` → atteso 413 o errore gestito, e subito dopo di nuovo home 200/302 (worker vivo).
Expected: l'app risponde prima e dopo l'oversize.

### Task 3: UAT — patch del server

**Files:** nessuno nel repo; operazioni su `uat.osm2cai` (root), checkout `/root/html/osm2cai2`, compose `compose.yml`.

**Interfaces:**
- Consumes: branch della feature pushato (istruzione testuale: il push lo approva il dev).
- Produces: UAT su 8.4.27 — prerequisito per prod.

- [ ] **Step 1: Tag di ritorno delle immagini correnti**

Run: `ssh uat.osm2cai 'docker tag wm-phpfpm:8.4-fpm wm-phpfpm:pre-8840-fpm && docker tag wm-phpfpm:8.4 wm-phpfpm:pre-8840'`
Expected: nessun output, `docker images | grep pre-8840` mostra 2 righe.

- [ ] **Step 2: Portare i file del Task 1 sul server**

Istruzione per il dev (approvazione git): push del branch `feature/oc-8840-...`, poi sul server `cd /root/html/osm2cai2 && git fetch origin && git checkout feature/oc-8840-... ` (oppure checkout dei soli due file: `git fetch origin && git checkout origin/feature/oc-8840-... -- docker/configs/phpfpm/`).
Expected: `grep 'FROM php' docker/configs/phpfpm/Dockerfile` sul server → `php:8.4.27-fpm`.

- [ ] **Step 3: Build con `--pull` (container vecchi ancora su)**

Run: `ssh uat.osm2cai 'cd /root/html/osm2cai2 && docker compose -f compose.yml build --pull php horizon'`
Expected: build ok per entrambe le immagini. Se fallisce: fermarsi, nessun container è stato toccato.

- [ ] **Step 4: Ricreazione con downtime minimo**

Run: `ssh uat.osm2cai 'cd /root/html/osm2cai2 && docker exec php81-osm2cai2uat php artisan horizon:terminate; docker compose -f compose.yml up -d php horizon'`
Expected: container ricreati.

- [ ] **Step 5: Verifica**

Run: `ssh uat.osm2cai 'docker exec php81-osm2cai2uat php -v | head -1; docker exec horizon-osm2cai2uat php -v | head -1; docker exec horizon-osm2cai2uat php artisan horizon:status'`
e `curl -s -o /dev/null -w '%{http_code}' https://osm2cai.uat.maphub.it`
Expected: `PHP 8.4.27` in entrambi, Horizon `running`, home 200/302.

### Task 4: Produzione — patch del server

**Files:** nessuno nel repo; operazioni su `cai.osm2cai` (utente `netseven`, tutti i comandi docker/git con `sudo`).

**Interfaces:**
- Consumes: esito positivo del Task 3.
- Produces: prod su 8.4.27.

- [ ] **Step 1: Chiarire il checkout effettivo**

Run: `ssh cai.osm2cai 'ls -ld /root/html/osm2cai2 /var/www/html/osm2cai2' 2>&1` (con sudo se serve)
Expected: capire quale esiste/è symlink; si lavora nella directory registrata dai label dei container (`/var/www/html/osm2cai2`).

- [ ] **Step 2: Tag di ritorno**

Run: `ssh cai.osm2cai 'sudo docker tag wm-phpfpm:8.4-fpm wm-phpfpm:pre-8840-fpm && sudo docker tag wm-phpfpm:8.4 wm-phpfpm:pre-8840'`
Expected: `sudo docker images | grep pre-8840` → 2 righe.

- [ ] **Step 3: Portare i file, build `--pull`, ricreazione, verifica**

Come Task 3 Step 2–5, con `sudo` e nomi container `php81-osm2cai2` / `horizon-osm2cai2`; home: `https://osm2cai.cai.it`.
Expected: `PHP 8.4.27` in entrambi i container, Horizon running, home 200/302, downtime di pochi secondi.

### Task 5: Verifica di compromissione (UAT + prod, sola lettura)

**Files:** esito in `docs/features/8840-messa-in-sicurezza-php2shell-shard-osm2cai2/notes.md` (sezione dedicata).

**Interfaces:**
- Consumes: accesso SSH ai due server.
- Produces: esito «nessuna evidenza» o elenco di anomalie, con i dati a supporto.

- [ ] **Step 1: Log Apache dal 20/10 — pattern di exploit e codici di risposta**

Run (per ciascun server): grep sui log host (incluse rotazioni che coprono dal 20/10) dei pattern PHP2Shell noti da carg (`auto_prepend_file=php://input`, `allow_url_include`, `%AD`, scanner `libredtail-http`, `leakix`), estraendo il codice di risposta di ogni hit.
Expected: esito chiuso solo se **nessun pattern ha mai ricevuto 2xx**; ogni 2xx va investigato prima di procedere.

- [ ] **Step 2: Filesystem, cron, utenti**

Run (per ciascun server): file PHP nuovi/modificati dal 20/10 nella webroot montata e in `/tmp` (host); `crontab -l` per root/netseven + `/etc/cron.*`; utenti con shell in `/etc/passwd`; mtime di `~/.ssh/authorized_keys`.
Expected: nessun file PHP anomalo, nessun cron/utente/chiave non riconosciuto.

- [ ] **Step 3: Registrare l'esito**

Scrivere l'esito (positivo o no) in `notes.md` e nella bozza di chiusura del ticket oc:8840.
Expected: sezione presente in notes.md con data e comandi usati.

### Task 6: Chiusura — merge e ripristino server

**Files:** nessuno; istruzioni testuali per il dev.

- [ ] **Step 1: PR verso `develop`** (convenzione Webmapp; il merge su `main` per prod lo decide il dev — il deploy automatico oggi fallisce in CI e non tocca i server).
- [ ] **Step 2: Dopo il merge, riportare i checkout dei server sul branch ordinario** (`main` su prod, quello in uso su UAT) con `git checkout <branch> && git pull`: i file del Task 1 vi sono ormai inclusi, l'immagine già buildata non cambia.
- [ ] **Step 3: Esito e artefatti sul ticket oc:8840** (fase update-context di wm-plan: note dev + risposta cliente, con approvazione esplicita).
