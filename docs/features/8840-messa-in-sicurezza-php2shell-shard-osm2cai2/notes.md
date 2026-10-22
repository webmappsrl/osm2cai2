> Ticket: oc:8840

# Notes — Messa in sicurezza PHP2Shell sullo shard osm2cai2

## Deviazioni dal piano

(nessuna al momento)

## Bug trovati

(nessuno al momento)

## Decisioni

- 22/10/2026 — Tag associati al ticket oc:8840 in fase di pianificazione: `php2shell` (706) e `osm2cai2` (613). Candidati scartati dal dev: `OSM2CAI TO OSM2CAI2` (386), `Backend Cyclando` (394), `Documentation: Documentazione backend EcPoi/RelatedPoi` (531).
- 22/10/2026 — **Snapshot dei server saltato** per decisione del dev («saltiamo lo snapshot al momento»): scostamento dall'ordine vincolante del tag php2shell (snapshot → evidenze → patch).
- 22/10/2026 — Evidenze: **come carg (oc:8838)** — patch subito, verifica di compromissione dopo sulle sole evidenze lato host; `docker tag pre-8833` prima del rebuild come punto di ritorno.
- 22/10/2026 — **Rotazione dei segreti economici ignorata per ora** per decisione del dev (come su forestas e carg, resta nei follow-up).
- 22/10/2026 — Vincolo wm-package (repo fermo al 27/05/2026, nessun aggiornamento del submodule prima di una revisione dedicata) tracciato nel ticket **oc:8841** (backlog).
- 22/10/2026 — **Limiti upload fissati a 32M/128M** per decisione del dev («adotta lo stesso limite di chi tra forestas e cammini è più permissivo»): niente misura sullo storage; valori verificati nei repo — forestas 32M/64M, camminiditalia 32M/128M → adottato camminiditalia.
- 22/10/2026 — Ricognizione sui server anticipata alla pianificazione (sola lettura via SSH, su richiesta del dev): esiti nella tabella Ambienti dell'overview. Verificato anche che il deploy automatico non raggiunge mai i server (`needs: [tests]` con CI rotta da maggio 2026).
- 22/10/2026 — **Stima saltata** per decisione del dev («salta la stima»): `estimated_hours` non scritto su Orchestrator. Per riferimento, la stima prodotta da wm-estimate era Misurato 2,45h + Stimato 2,6h = 5,05h.
- 22/10/2026 — Punto di ritorno nominato `pre-8840` (ticket di questo shard) invece del `pre-8833` della procedura comune, per non confondere fra tre mesi a quale intervento appartenga il tag.

- 22/10/2026 — **Interventi extra su UAT durante l'esecuzione** (su direttiva del dev, per rendere UAT stabile): Horizon fermato (`docker stop`, da riavviare/valutare), code Redis svuotate (4,97GB di job vecchi → 909KB, `bgsave` riscritto), heap Elasticsearch parametrizzato in `compose.yml` (default 2g, era 8g fisso) e applicato su UAT → cluster **green** per la prima volta dopo ~11 mesi di crash loop.
- 22/10/2026 — **Fix `wmdumps` integrato nel repo** (commit `a8ec8b39`): la versione a mano viva solo su prod (fallback su credenziali AWS generiche → object storage Aruba, `root => 'backups/'`) è ora nel branch; su prod, al pull, serve `git checkout -- config/filesystems.php` (contenuto identico).
- 22/10/2026 — Ordine del piano deviato su direttiva del dev: merge su `develop`/`main` **prima** dei Task 4 (prod) e 5 (verifica compromissione); prod verrà patchata con pull di `main`.

## Follow-up

- Rotazione precauzionale dei segreti economici (mai `JWT_SECRET`).
- Revisione e allineamento del submodule wm-package: oc:8841.
- Host UAT con OS fuori supporto (Ubuntu 20.04.6, OpenSSH datato) → da aggiungere al censimento oc:8835.
- CI di test rotta sul runner (`role "root" does not exist`, come su forestas) → di conseguenza i workflow di deploy prod/develop falliscono a ogni push da maggio 2026 e non deployano mai.
- Elasticsearch `unhealthy` sul server prod (fuori scope di questo ticket).
- **UAT malato cronico** (rilevato 22/10 durante l'intervento, preesistente): Elasticsearch in crash loop da ~11 mesi (restart count 1.227.156), Redis senza `maxmemory` arrivato a ~5GB RSS e ucciso dall'OOM killer (restart count 10.754; l'OOM delle 16:39 del 22/10 ha buttato giù le sessioni → login momentaneamente rotto, rientrato da solo). Candidati a un ticket dedicato.
- **File non committati sul working tree di prod** (rilevati 22/10, da salvare con un ticket dedicato prima che un checkout o un conflitto li porti via): `config/horizon.php` (maxProcesses ridotti a mano 15→4 e 10→3/4 per contenere la memoria), `config/filesystems.php` (disco `wmdumps` dei backup DB→AWS corretto a mano: fallback su credenziali AWS generiche, `root => 'backups/'`), `storage/json/conf/osm2cai2/{1,3}/config.json` (una riga ciascuno). Non collidono con i file di questo ticket.
- Debito strutturale della strategia php2shell: il pin esatto dell'immagine andrà ribumpato alla prossima CVE; nessun processo condiviso di bump fra gli shard; la CI testa su `php-version: "8.4"` mobile, non sull'immagine Docker.
