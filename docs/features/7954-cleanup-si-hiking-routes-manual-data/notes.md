> Ticket: oc:7954

# Notes — Cleanup manual_data su SiHikingRoute

## Deviazioni dal piano

### 1. `--verbose` → `-v` (flag built-in Symfony)
Il piano prevedeva un'opzione custom `--verbose`. In fase di esecuzione è emerso
che `--verbose` è già un'opzione nativa di Symfony Console e causa conflitto
(`LogicException: An option named "verbose" already exists`).
Soluzione: rimosso `--verbose` dalla signature, usato `$this->output->isVerbose()`
che si attiva con il flag standard `-v` di Artisan.

### 2. `chunk()` → `chunkById()` (bug offset shifting)
Il piano usava `chunk(100)`. In fase di esecuzione reale è emerso che modificare
i record durante l'iterazione (saveQuietly rimuove manual_data → il record esce
dalla WHERE clause) causa lo spostamento dell'OFFSET, facendo saltare record.
Primo lancio: 295/520 puliti invece di 520.
Soluzione: sostituito con `chunkById(100)` che pagina per ID e non risente delle
modifiche alla result set. Secondo lancio (idempotente): puliti i restanti 225.

### 3. Operatore `?` PostgreSQL → `??` in whereRaw
PDO interpreta `?` come placeholder di bind. Necessario raddoppiare in `??`
per escaparlo correttamente in `whereRaw`.

## Bug trovati
- `chunk()` + modifica della result set durante l'iterazione = record saltati.
  Pattern pericoloso da evitare in tutti i command che modificano i record
  su cui stanno iterando. Preferire sempre `chunkById()` in questi casi.

## Decisioni
- Il command è stato rilanciato una seconda volta sfruttando l'idempotenza.
  Nessun dato è andato perso né duplicato.
- Nessun record senza DEM/OSM data — il warning finale non è apparso.

## Follow-up
- Nessuno. Il cleanup è completo: 520 record puliti, 5 skippati (manual_data vuoto).
