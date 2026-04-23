# Runbook — Aggiornamento develop post merge wm-package update-forestas

**Data:** 2026-04-23  
**Branch sorgente:** wm-package/update-forestas  
**Applicato su:** osm2cai2/develop

---

## Cosa è cambiato

- `wm-package` aggiornato (65 commit nuovi)
- `laravel/framework` aggiornato da `11.38.*` a `^12.0`
- `laravel/nova` aggiornato da `5.3.1` a `5.7.6`
- Rimossi pacchetti inutilizzati: `subfission/cas`, `idez/nova-date-range-filter`, `eminiarts/nova-tabs`
- `composer.lock` rigenerato da zero
- 7 nuove migration aggiunte
- Nuova risorsa Nova `TaxonomyWhere` creata e registrata
- `wm-osmfeatures/composer.json` e `wm-internal/composer.json` aggiornati per supportare Laravel 12

---

## Procedura

### 1. Aggiorna il codice

```bash
git pull origin develop
git submodule update wm-package
```

### 2. Installa le dipendenze

```bash
docker exec laravel-osm2cai2 composer install --no-interaction
```

> Usa `install` (non `update`) — il `composer.lock` è già corretto e tracciato nel repo.

### 3. Migration

```bash
docker exec laravel-osm2cai2 php artisan migrate
```

Le 7 nuove migration da applicare:

| Migration | Operazione |
|-----------|------------|
| `2026_04_23_..._create_taxonomy_whereables_table` | CREATE |
| `2026_04_23_..._create_taxonomy_wheres_table` | CREATE (PostGIS) |
| `2026_04_23_..._z_add_foreign_keys_to_taxonomy_whereables_table` | ALTER — FK |
| `2026_04_23_..._create_feature_collections_table` | CREATE |
| `2026_04_23_..._create_feature_collection_layer_table` | CREATE |
| `2026_04_23_..._add_overlays_fields_to_apps_table` | ALTER — aggiunge `config_overlays` jsonb |
| `2026_04_23_..._rename_theme_filters_and_create_app_filter_layers_table` | RENAME 3 colonne + CREATE pivot |

#### Se `migrate` fallisce con "already exists" o "duplicate column"

La colonna/tabella esiste già nel DB ma non è tracciata. Inserisci il record manualmente e rilancia:

```bash
docker exec laravel-osm2cai2 php artisan tinker --execute="
DB::table('migrations')->insertOrIgnore([
    'migration' => 'NOME_ESATTO_MIGRATION_CHE_FALLISCE',
    'batch' => DB::table('migrations')->max('batch'),
]);
"
docker exec laravel-osm2cai2 php artisan migrate
```

Ripeti per ogni migration che fallisce.

### 4. Svuota la cache

```bash
docker exec laravel-osm2cai2 php artisan optimize:clear
```

### 5. Verifica log

```bash
docker exec laravel-osm2cai2 tail -n 30 storage/logs/laravel.log | grep -i "error\|exception"
```

Il log deve essere pulito.

---

## Smoke test Nova

Dopo aver completato i passi sopra, verificare nel browser:

- [ ] Nova → **App** → apri un record → tab **Theme** e **Overlays** caricano senza errori JS
- [ ] Nova → **Taxonomies** → **TaxonomyWhere** → la pagina carica
- [ ] Nova → **Layer** → apri un record → nessun errore in console
- [ ] Nova → **EcTrack** → apri un record → sezione **DEM Classification** visibile
- [ ] Nova → **ConfigHome** → Flexible field → tipo **Horizontal Scroll Item** disponibile

---

## Note

- `subfission/cas` e `idez/nova-date-range-filter` sono stati rimossi dal `composer.json` perché non utilizzati nel codice. Non impattano il runtime.
- La migration `z_add_foreign_keys_to_ugc_pois_table` è stata saltata su osm2cai2 perché `ugc_pois.app_id` è `varchar` mentre `apps.id` è `bigint` — tipi incompatibili. È un problema preesistente nel DB, non introdotto da questo aggiornamento.
- I test `SignageMapControllerRoundTravelTimeTest` (3 fallimenti su `null/zero/negative`) sono un bug preesistente, non legato a questo aggiornamento.
