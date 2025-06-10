## Laravel 11 Project based on Nova 4

Osm2cai2 is a Laravel 11 project based on php 8.2 and Laravel Nova 4 with postgis extension.

## INSTALLATION

First of all install the [GEOBOX](https://github.com/webmappsrl/geobox) repo and configure the [ALIASES command](https://github.com/webmappsrl/geobox#aliases-and-global-shell-variable).
Replace `${instance name}` with the instance name (APP_NAME in .env file)

```sh
git clone git@github.com:webmappsrl/osm2cai2.git osm2cai2
git flow init
```

Important NOTE: remember to checkout the develop branch.

```sh
cd ${instance name}
bash docker/init-docker.sh
docker exec -u 0 -it php81_osm2cai2 bash
chown -R 33 .
```

_Important NOTE_: if you have installed XDEBUG you need to create the xdebug.log file on the docker:

```bash
docker exec -u 0 -it php81_osm2cai2 bash
touch /var/log/xdebug.log
chown -R 33 /var/log/
```

At the end run install command to for this instance

```bash
geobox_install osm2cai2
```

_Important NOTE_:

-   Update your local repository of Geobox following its [Aliases instructions](https://github.com/webmappsrl/geobox#aliases-and-global-shell-variable). Make sure that you have set the environment variable GEOBOX_PATH correctly.
-   Make sure that the version of wm-package of your instance is at leaset 1.1. Use command:

```bash
composer update wm/wp-package
```

Finally to import a fresh copy of database use Geobox restore command:

```bash
geobox_dump_restore osm2cai2
```

## Run web server from shell outside docker

In order to start a web server in local environment use the following command:
Replace `${instance name}` with the instance name (APP_NAME in .env file)

```sh
geobox_serve osm2cai2
```

## Ambiente di Sviluppo con MinIO

Per lo sviluppo locale è disponibile un ambiente completo con MinIO (S3-compatible) per gestire i file e MailPit per catturare le email.

### Setup Rapido

```bash
# 1. Setup completo ambiente di sviluppo
./scripts/dev-setup.sh

# 2. Configura bucket MinIO
./scripts/setup-minio-bucket.sh
```

### Servizi Disponibili

- **Applicazione**: http://localhost:8008
- **MinIO Console**: http://localhost:9003 (minioadmin/minioadmin)
- **MailPit**: http://localhost:8025
- **Elasticsearch**: http://localhost:9200
- **PostgreSQL**: localhost:5508

### Configurazione .env per MinIO

```bash
# MinIO Configuration
MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=minioadmin

# AWS S3 Compatible Settings
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_BUCKET=osm2cai2-bucket
AWS_ENDPOINT=http://minio_osm2cai2:9003
AWS_URL=http://localhost:9002
AWS_USE_PATH_STYLE_ENDPOINT=true

# Email Development  
MAIL_MAILER=smtp
MAIL_HOST=mailpit_osm2cai2
MAIL_PORT=1025
```

### Gestione Ambiente

```bash
# Avvia ambiente di sviluppo
docker-compose up -d
docker-compose -f develop.compose.yml up -d

# Ferma ambiente di sviluppo
docker-compose down
docker-compose -f develop.compose.yml down

# Solo servizi di sviluppo (MinIO, MailPit)
docker-compose -f develop.compose.yml up -d
```

## Configurazione Database Geohub

Per utilizzare gli script di import da Geohub (disponibili in `scripts/wm-package-integration/`), è necessario configurare la connessione al database Geohub nel file `.env`.

### Variabili Ambiente Richieste

Aggiungere le seguenti variabili al file `.env`:

```bash
# Geohub Database Configuration
GEOHUB_DB_HOST=your-geohub-host
GEOHUB_DB_PORT=5432
GEOHUB_DB_DATABASE=geohub
GEOHUB_DB_USERNAME=your-username
GEOHUB_DB_PASSWORD=your-password
```

### Configurazione per Ambiente Locale

**IMPORTANTE**: Se stai eseguendo il progetto in locale con Docker, devi utilizzare:

```bash
GEOHUB_DB_HOST=host.docker.internal
```

Questo permette al container Docker di connettersi al database Geohub in esecuzione sull'host locale.

### Verifica Configurazione

Dopo aver configurato le variabili, pulisci la cache di configurazione:

```bash
docker exec php81_osm2cai2 php artisan config:clear
docker exec php81_osm2cai2 php artisan config:cache
```

E riavvia Horizon per applicare le nuove configurazioni:

```bash
docker exec php81_osm2cai2 php artisan horizon:terminate
docker exec -d php81_osm2cai2 php artisan horizon
```

### Test Connessione

Puoi testare la connessione al database Geohub con:

```bash
docker exec php81_osm2cai2 php artisan tinker --execute="try { DB::connection('geohub')->getPdo(); echo 'Connessione geohub OK'; } catch(Exception \$e) { echo 'Errore connessione: ' . \$e->getMessage(); }"
```

### Differenze ambiente produzione locale

Questo sistema di container docker è utilizzabile sia per lo sviluppo locale sia per un sistema in produzione. In locale abbiamo queste caratteristiche:

-   la possibilità di lanciare il processo processo `php artisan serve` all'interno del container phpfpm, quindi la configurazione della porta `DOCKER_SERVE_PORT` (default: `8000`) necessaria al progetto. Se servono più istanze laravel con processo artisan serve contemporaneamente in locale, valutare di dedicare una porta tcp dedicata ad ognuno di essi. Per fare questo basta solo aggiornare `DOCKER_SERVE_PORT`.
-   la presenza di xdebug, definito in fase di build dell'immagine durante l'esecuzione del comando
-   `APP_ENV=local`, `APP_DEBUG=true` e `LOG_LEVEL=debug` che istruiscono laravel su una serie di comportamenti per il debug e l'esecuzione locale dell'applicativo
-   Una password del db con complessità minore. **In produzione usare [password complesse](https://www.avast.com/random-password-generator#pc)**

### Inizializzazione tramite boilerplate

-   Download del codice del boilerplate in una nuova cartella `nuovoprogetto` e disattivare il collegamento tra locale/remote:
    ```sh
    git clone https://github.com/webmappsrl/laravel-postgis-boilerplate.git nuovoprogetto
    cd nuovoprogetto
    git remote remove origin
    ```
-   Effettuare il link tra la repository locale e quella remota (repository vuota github)

    ```sh
    git remote add origin git@github.com:username/repo.git
    ```

-   Copy file `.env-example` to `.env`

    Questi valori nel file .env sono necessari per avviare l'ambiente docker. Hanno un valore di default e delle convenzioni associate, valutare la modifica:

    -   `APP_NAME` (it's php container name and - postgrest container name, no space)
    -   `DOCKER_PHP_PORT` (Incrementing starting from 9100 to 9199 range for MAC check with command "lsof -iTCP -sTCP:LISTEN")
    -   `DOCKER_SERVE_PORT` (always 8000, only on local environment)
    -   `DOCKER_PROJECT_DIR_NAME` (it's the folder name of the project)
    -   `DB_DATABASE`
    -   `DB_USERNAME`
    -   `DB_PASSWORD`

    Se siamo in produzione, rimuovere (o commentare) la riga:

    ```yml
    - ${DOCKER_SERVE_PORT}:8000
    ```

    dal file `docker-compose.yml`

-   Creare l'ambiente docker
    ```sh
    bash docker/init-docker.sh
    ```
-   Digitare `y` durante l'esecuzione dello script per l'installazione di xdebug

-   Verificare che i container si siano avviati

    ```sh
    docker ps
    ```

-   Avvio di una bash all'interno del container php per installare tutte le dipendenze e lanciare il comando php artisan serve (utilizzare `APP_NAME` al posto di `$nomeApp`):

    ```sh
    docker exec -it php81_$nomeApp bash
    composer install
    php artisan key:generate
    php artisan optimize
    php artisan migrate
    php artisan serve --host 0.0.0.0
    ```

-   A questo punto l'applicativo è in ascolto su <http://127.0.0.1:8000> (la porta è quella definita in `DOCKER_SERVE_PORT`)

### Configurazione xdebug vscode (solo in locale)

Assicurarsi di aver installato l'estensione [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug).

Una volta avviato il container con xdebug configurare il file `.vscode/launch.json`, in particolare il `pathMappings` tenendo presente che **sulla sinistra abbiamo la path dove risiede il progetto all'interno del container**, `${workspaceRoot}` invece rappresenta la pah sul sistema host. Eg:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9200,
            "pathMappings": {
                "/var/www/html/geomixer2": "${workspaceRoot}"
            }
        }
    ]
}
```

Aggiornare `/var/www/html/geomixer2` con la path della cartella del progetto nel container phpfpm.

Per utilizzare xdebug **su browser** utilizzare uno di questi 2 metodi:

-   Installare estensione xdebug per browser [Xdebug helper](https://chrome.google.com/webstore/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc)
-   Utilizzare il query param `XDEBUG_SESSION_START=1` nella url che si vuole debuggare
-   Altro, [vedi documentazione xdebug](https://xdebug.org/docs/step_debug#web-application)

Invece **su cli** digitare questo prima di invocare il comando php da debuggare:

```bash
export XDEBUG_SESSION=1
```

### Scripts

Ci sono vari scripts per il deploy nella cartella `scripts`. Per lanciarli basta lanciare una bash con la path dello script dentro il container php, eg (utilizzare `APP_NAME` al posto di `$nomeApp`):

```bash
docker exec -it php81_$nomeApp bash scripts/deploy_dev.sh
```

### Artisan commands

-   `db:dump_db`
    Create a new sql file exporting all the current database in the local disk under the `database` directory
-   `db:download`
    download a dump.sql from server
-   `db:restore`
    Restore a last-dump.sql file (must be in root dir)

### Problemi noti

Durante l'esecuzione degli script potrebbero verificarsi problemi di scrittura su certe cartelle, questo perchè di default l'utente dentro il container è `www-data (id:33)` quando invece nel sistema host l'utente ha id `1000`:

-   Chown/chmod della cartella dove si intende scrivere, eg:

    NOTA: per eseguire il comando chown potrebbe essere necessario avere i privilegi di root. In questo caso si deve effettuare l'accesso al cointainer del docker utilizzando lo specifico utente root (-u 0). Questo è valido anche sbloccare la possibilità di scrivere nella cartella /var/log per il funzionamento di Xdedug

    Utilizzare il parametro `-u` per il comando `docker exec` così da specificare l'id utente, eg come utente root (utilizzare `APP_NAME` al posto di `$nomeApp`):

    ```bash
    docker exec -u 0 -it php81_$nomeApp bash
    chown -R 33 storage
    ```

Xdebug potrebbe non trovare il file di log configurato nel .ini, quindi generare vari warnings

-   creare un file in `/var/log/xdebug.log` all'interno del container phpfpm. Eseguire un `chown www-data /var/log/xdebug.log`. Creare questo file solo se si ha esigenze di debug errori xdebug (impossibile analizzare il codice tramite breakpoint) visto che potrebbe crescere esponenzialmente nel tempo
