# Aggiornare le immagini PHP sui server osm2cai2

Quando cambia `docker/configs/phpfpm/` (nuovo pin per una CVE, limiti FPM), il deploy automatico **non applica nulla**: i workflow non ricostruiscono le immagini. Si fa a mano, un server per volta, dopo la verifica in locale. (Origine: oc:8840.)

| Ambiente | Server | Utente | Checkout | Compose |
|---|---|---|---|---|
| UAT | `91.99.182.227` (Hetzner) | `root` | `/root/html/osm2cai2` | `compose.yml` |
| Prod | `195.231.60.153` (Aruba, infrastruttura CAI) | `netseven` (sudo pieno) | `/var/www/html/osm2cai2` | `compose.yml` |

L'accesso è via SSH con chiave; gli alias tipo `uat.osm2cai` che compaiono in altri documenti sono scorciatoie del `~/.ssh/config` personale di chi ha fatto il lavoro, non nomi condivisi.

Per ogni server, nell'ordine:

1. **Porta il codice aggiornato sul server** (pull del branch mergiato; su prod i comandi git e docker vanno con `sudo`). Se `git status` mostra modifiche locali, fermati e capisci cosa sono prima di qualsiasi checkout: sul working tree di prod vivono fix mai committati.
2. **Tag di ritorno** di entrambe le immagini correnti: `docker tag wm-phpfpm:8.4-fpm wm-phpfpm:pre-<ticket>-fpm && docker tag wm-phpfpm:8.4 wm-phpfpm:pre-<ticket>` (tag locali: un `docker system prune` li cancella).
3. **Build a container vivi**: `docker compose -f compose.yml build --pull php horizon`. Sempre `--pull` (senza, la base in cache rende il rebuild un no-op silenzioso). Se la build fallisce, nessun danno: i container vecchi sono ancora su.
4. **Ricreazione**: `docker exec <container-php> php artisan horizon:terminate`, poi `docker compose -f compose.yml up -d php horizon` (downtime di pochi secondi).
5. **Verifica** — mai fidarsi dell'esito della build:
   - `php -v` dentro **entrambi** i container (FPM e Horizon): versione attesa;
   - `php artisan horizon:status` nel container Horizon: exit 0 (l'output INFO può non passare dalla pipe);
   - home del sito: 200/302;
   - upload oversize (body > `post_max_size`): 413 e home di nuovo viva subito dopo.
