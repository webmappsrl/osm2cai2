# Messa in sicurezza PHP2Shell shard osm2cai2

## Come funziona oggi

- Dal Dockerfile di `docker/configs/phpfpm/` nascono **due immagini** — `wm-phpfpm:8.4-fpm` (container FPM) e `wm-phpfpm:8.4` (container Horizon) — da ricostruire sempre **entrambe**: dimenticarne una lascia un container su PHP vecchio, ed è già successo (su UAT giravano build diverse, 8.4.14 e 8.4.15, rilevato 22/10/2026) (oc:8840). La procedura per patcharle sui server è in [docs/howto/aggiornare-immagini-php-sui-server.md](../howto/aggiornare-immagini-php-sui-server.md).
- `www.conf` è copiato **dentro** l'immagine: ogni modifica ai limiti FPM richiede rebuild e ricreazione dei container, non basta toccare il file (oc:8840).
- `ES_JAVA_OPTS` è parametrico con default 2g, sovrascrivibile dal `.env` del server (oc:8840): con gli 8g fissi precedenti, su macchine da 16GB Elasticsearch e Redis si contendevano la RAM fino all'intervento dell'OOM killer — su UAT crash loop di ES durato ~11 mesi (1.227.156 restart contati il 22/10/2026), su prod 3 kill di java nella sola giornata del 22/10/2026. Gli indici pesano ~27MB (UAT, 22/10/2026): 2g abbonda.
- Il disco `wmdumps` (backup DB) ha i fallback sulle `AWS_*` generiche perché il `.env` di prod non definisce alcuna `AWS_DUMPS_*`: senza fallback il disco resta senza credenziali e il backup muore in silenzio (oc:8840). Su prod punta all'object storage Aruba; dump giornalieri alle 20:00 in `backups/osm2cai2/` (verificato 22/10/2026).
- I workflow GitHub di deploy **non ricostruiscono mai le immagini** e da maggio 2026 non raggiungono i server (il job `deploy` dipende dal job di test, rotto in CI): ogni patch alle immagini è un'operazione manuale sui server (rilevato 22/10/2026, oc:8840).

## Come ci siamo arrivati

- **Tag mobile `php:8.4-fpm`** (superato da oc:8840): non garantiva la versione nei container (prod su 8.4.21, UAT su 8.4.14/15, rilevato 22/10/2026) e un rebuild senza `--pull` riusava la base in cache senza segnalarlo. Sostituito dal pin esatto `php:8.4.27-fpm` per le CVE PHP2Shell (CVE-2026-109618/109619).
- **`ES_JAVA_OPTS=-Xms8g -Xmx8g` fisso** (superato da oc:8840): causa della contesa di RAM descritta sopra; la riduzione a mano dei worker Horizon sul working tree di prod (`maxProcesses` 15→4 e 10→3/4, mai committata) era un tampone allo stesso problema.
- **Limiti FPM a 1024M** (superati da oc:8840): non corrispondevano ad alcun flusso reale; adottati i valori di camminiditalia (32M/128M), il più permissivo fra gli shard già patchati, senza misura dei flussi (decisione del dev).
