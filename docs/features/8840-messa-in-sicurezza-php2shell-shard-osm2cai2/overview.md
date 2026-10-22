> Ticket: oc:8840

# Messa in sicurezza PHP2Shell (CVE-2026-109618/109619) sullo shard osm2cai2

## Cosa cambia

Replica su osm2cai2 della messa in sicurezza PHP2Shell già applicata agli altri shard wm-package (riferimento: oc:8833 forestas, oc:8838 carg; strategia completa nel tag `php2shell`).

- L'immagine PHP passa dal tag mobile `php:8.4-fpm` al pin esplicito `php:8.4.27-fpm` (prima versione 8.4 corretta), con rebuild `--pull` e ricreazione dei container PHP su **locale, UAT e produzione**. Le immagini da ricostruire sono **due** (`wm-phpfpm:8.4-fpm` per FPM e `wm-phpfpm:8.4` per Horizon), buildate dallo stesso Dockerfile.
- Il pool FPM viene ripulito in modo permanente: `session.upload_progress.enabled=off` e limiti di upload ridotti dagli attuali 1024M a **`upload_max_filesize=32M` / `post_max_size=128M`** (decisione del dev: i valori del più permissivo fra forestas e camminiditalia, già in produzione lì).
- Verifica di compromissione sui due server, condotta sulle evidenze lato host (come su carg, oc:8838).

### Ambienti (ricognizione del 22/10, in sola lettura via SSH)

| Ambiente | Host | Provider / OS | Stato rilevato |
|---|---|---|---|
| Locale | — | — | container `php-osm2cai2`/`horizon-osm2cai2` attivi |
| UAT | `uat.osm2cai` (91.99.182.227) | Hetzner Cloud; **Ubuntu 20.04.6 fuori supporto** (→ oc:8835) | accesso root; compose: `/root/html/osm2cai2/compose.yml`; container su da **11 mesi**, PHP **8.4.14** (FPM) e **8.4.15** (Horizon), entrambi vulnerabili; `libapache2-mod-php7.4` installato ma **disattivato**, PHP host solo CLI: nessun PHP host-side esposto |
| Produzione | `cai.osm2cai` (195.231.60.153) | Aruba Cloud, infrastruttura CAI/NetSeven; Ubuntu 24.04.5 LTS | utente `netseven` con **sudo pieno NOPASSWD**; compose: `compose.yml` (workdir `/var/www/html/osm2cai2` — il deploy workflow cita `/root/html/osm2cai2`: da chiarire sul posto, probabile symlink); PHP reale **8.4.21, vulnerabile** (il nome `php81-osm2cai2` è solo legacy); **nessun PHP sull'host**; dominio `osm2cai.cai.it`; Elasticsearch `unhealthy` (fuori scope) |

`compose.yml` (usato da entrambi i server) binda tutte le porte su `127.0.0.1`: nessuna superficie esposta oltre ad Apache host-side, che passa i `.php` a FPM via `proxy:fcgi`.

Il deploy automatico (`prod-deploy.yml` / `dev-deploy.yml`) **non raggiunge mai i server**: il job `deploy` ha `needs: [tests]` e il job di test fallisce su ogni push da maggio 2026 (CI rotta sul runner, `role "root" does not exist`). La patch sui server è quindi interamente manuale.

## Perché

Il 21/10/2026 sono state pubblicate due vulnerabilità critiche del motore PHP («PHP2Shell», CVE-2026-109618/109619, CVSS 9.2): RCE non autenticata, exploit pubblico, attacchi osservati in rete già prima della patch. La falla è nel motore, non nel codice applicativo: ogni server che espone PHP è interessato. Versioni corrette: 8.2.35, 8.3.36, 8.4.27, 8.5.12. Su carg (oc:8838) i log hanno mostrato tentativi di exploit reali dal 20/10, tutti respinti: il rischio è attivo, non teorico. Qui i container girano su 8.4.14–8.4.21: tutti vulnerabili.

Fonte: mail NetSeven 21/10/2026 «Emergenza PHP - CVE critica»; mail Hetzner 22/10/2026; advisory Sansec, CERT-EU 2026-018, Tenable.

## Requisiti

- [ ] Pin `php:8.4.27-fpm` in `docker/configs/phpfpm/Dockerfile`.
- [ ] Pool FPM (`docker/configs/phpfpm/www.conf`): `php_admin_flag[session.upload_progress.enabled] = off`; `php_admin_value[upload_max_filesize] = 32M`; `php_admin_value[post_max_size] = 128M`.
- [ ] Su ogni server, prima del rebuild: `docker tag` di **entrambe** le immagini correnti (`wm-phpfpm:8.4-fpm` e `wm-phpfpm:8.4`) come punto di ritorno `pre-8840`.
- [ ] Ordine anti-downtime: **build delle nuove immagini prima di fermare qualsiasi container**; poi `horizon:terminate` per chiudere i job in corso e ricreazione dei soli container PHP (downtime di pochi secondi, come su carg).
- [ ] Rebuild sempre con `--pull` (trappola nota del tag: senza, il tag mobile in cache rende il rebuild un no-op).
- [ ] Sequenza: locale → UAT → produzione; la patch sui server avviene prima del merge su `main` (il deploy automatico oggi comunque non arriva al server).
- [ ] Verifica su locale, UAT e prod **con `php -v` dentro entrambi i container** (FPM + Horizon), mai con l'esito della build; app che risponde; Horizon attivo; smoke test di un upload legittimo entro i nuovi limiti.
- [ ] Sul server prod, prima di buildare: chiarire il checkout effettivo (`/var/www/html/osm2cai2` vs `/root/html/osm2cai2`) e buildare da quello che i container usano.
- [ ] Verifica di compromissione lato host su UAT e prod: log Apache dal 20/10 (pattern exploit e relativi codici di risposta), file PHP nuovi/modificati nelle webroot e in /tmp, cron, utenti, `authorized_keys`.
- [ ] Esito registrato sul ticket oc:8840.

## Rischi

- **Niente snapshot prima della patch** (decisione del dev, 22/10): le evidenze dentro i container vanno perse al rebuild. Mitigazione: verifica di compromissione sulle evidenze lato host (log Apache, webroot montata, cron, utenti), che su carg è bastata per un esito solido; `docker tag pre-8840` su entrambe le immagini come punto di ritorno operativo. Worst case accettato consapevolmente: se la verifica host-side desse indizi ambigui, la timeline dentro i container non sarà ricostruibile, e i segreti (non ruotati, per decisione del dev) restano validi.
- **Doppio tag immagine (`:8.4-fpm` per FPM, `:8.4` per Horizon)**: rebuildarne una sola lascia Horizon (o FPM) su PHP vulnerabile — su UAT girano già oggi due build diverse (8.4.14 vs 8.4.15), prova che è già successo. Mitigazione: requisito esplicito di rebuild + verifica `php -v` su entrambi i container.
- **Build `--pull` dopo mesi può fallire** (base Debian nuova vs repo PGDG/`lsb_release`, compile pecl/imagick): mitigato dall'ordine anti-downtime — la build avviene a container vecchi ancora su; una build fallita non causa alcun downtime.
- **Riduzione dei limiti di upload a 32M/128M**: un flusso legittimo oltre quei valori (multi-file Nova, base64 via API) riceverebbe un 413. Rischio accettato dal dev: sono i valori già in produzione sul più permissivo fra forestas e camminiditalia; smoke test post patch.
- **Rollback = downgrade di sicurezza**: ripartire da `pre-8840` rimette online una RCE con exploit pubblico; è un punto di ritorno operativo d'emergenza, non un rollback ordinario. I limiti FPM vivono dentro l'immagine (`ADD www.conf` nel Dockerfile): ogni ritocco richiede rebuild + ricreazione.

## Out of scope

- Aggiornamento/revisione del submodule `wm-package` (fermo al 27/05/2026): tracciato in **oc:8841**; in questo lavoro il submodule non si tocca e l'howto `messa-in-sicurezza-php-shard.md` (wm-package v1.6.0) si consulta da GitHub.
- Rotazione precauzionale dei segreti economici (decisione del dev, 22/10: ignorata per ora; comunque mai `JWT_SECRET`).
- Snapshot dei server (decisione del dev, 22/10: saltato per ora).
- Aggiornamento dell'OS degli host: UAT (Ubuntu 20.04.6 fuori supporto, OpenSSH datato) va aggiunto al censimento di **oc:8835**, senza intervenire qui.
- CI rotta sul runner (`role "root" does not exist`) e workflow di deploy che falliscono da maggio 2026: segnalati nei follow-up, non si sistemano qui.
- Processo strutturale di bump delle immagini fra gli shard (il pin esatto andrà aggiornato alla prossima CVE): debito dichiarato della strategia php2shell, fuori da questo ticket.
- Altri servizi eventualmente presenti sulle stesse macchine: non si toccano. Elasticsearch `unhealthy` su prod: segnalato, fuori scope.

## Moduli toccati

Feature interamente custom, tutto nel repo principale `osm2cai2` (nessun submodule toccato):

- `docker/configs/phpfpm/Dockerfile` — pin dell'immagine base
- `docker/configs/phpfpm/www.conf` — pulizia pool FPM (upload_progress off, 32M/128M)
- `docs/features/8840-messa-in-sicurezza-php2shell-shard-osm2cai2/` — artefatti del lavoro

Operazioni sui server (UAT `uat.osm2cai`, prod `cai.osm2cai`): tag `pre-8840`, build, ricreazione container, verifica, controllo compromissione — manuali, non file di repo.
