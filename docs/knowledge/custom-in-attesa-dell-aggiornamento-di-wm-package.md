# Custom scritto in attesa dell'aggiornamento di wm-package

Cosa esiste in osm2cai2 solo perché il package è indietro, e che va rimosso quando si aggiorna.

## Come funziona oggi

osm2cai2 usa `wm-package` fermo al commit `451ba071` del **27 maggio 2026**, **188 commit**
dietro a `origin/main`. Nel frattempo nel package sono state corrette cose che qui si continuano
a subire. Quando una di queste correzioni serve subito e l'aggiornamento non è percorribile, la
si **riscrive in casa**: è codice con una scadenza, non una scelta di progetto, e va riconosciuto
come tale da chi lo trova.

Il criterio per distinguere i due casi è uno solo: **la correzione esiste già nel package?**

- **Sì** → è codice temporaneo. Va scritto il più possibile identico all'originale — stessi nomi
  di classi e metodi, stesse firme, stessa logica — e va elencato qui. All'aggiornamento si
  cancella.
- **No** → è codice nostro, e resta anche dopo l'aggiornamento. Non va in questa pagina e non va
  mescolato nei file di quello temporaneo.

### Registro del codice temporaneo

| Area | File | Origine nel package | All'aggiornamento |
|---|---|---|---|
| Duplicati UGC: prevenzione | `app/Http/Controllers/Api/UgcPoiController.php`, `app/Http/Controllers/Api/UgcTrackController.php`, rotte in `routes/api.php` | oc:8718 | cancellare i controller, riportare le rotte a quelle del package |
| Duplicati UGC: bonifica | `app/Services/UgcDuplicatesService.php`, `app/Services/UgcMediaHashService.php`, migration `…create_ugc_duplicates_archive_table` | oc:8718 | cancellare i servizi; la migration resta (la tabella è la stessa, la migration del package non la ricrea) |

### Codice nostro che resta

| Area | File | Perché resta |
|---|---|---|
| Duplicati senza uuid | `app/Services/UgcDuplicatesByTimestampService.php` | riconosce i duplicati per utente, istante di compilazione e posizione. Serve solo qui: su camminiditalia tutti i rilevamenti hanno l'uuid (0 su 369), qui no |
| Sanatoria della vecchia bonifica | `app/Console/Commands/RecoverPreviousDuplicateFixCommand.php` | recupera lo stato lasciato dal command di duplicazione che esiste solo in osm2cai2 |

## Perché così

- **Riscrivere invece di aggiornare** (oc:8867): l'aggiornamento di 188 commit di package è un
  lavoro a sé, con il suo rischio; il difetto dei duplicati produceva una quindicina di record in
  eccesso al mese e non poteva aspettarlo.
- **Copia il più fedele possibile invece di una riscrittura migliore** (oc:8867): il valore di
  questo codice è di poter essere **cancellato senza pensarci** il giorno dell'aggiornamento. Ogni
  miglioria introdotta qui è una differenza da riconciliare a mano in quel momento, cioè il
  contrario di quello che serve. Le divergenze inevitabili — dipendenze che in questa versione
  non esistono — vanno annotate nel `notes.md` del lavoro che le introduce.
- **Due strati separati e mai mescolati** (oc:8867): il codice temporaneo e quello definitivo
  stanno in file diversi, e il secondo richiama il primo solo attraverso metodi che esistono
  anche nell'originale del package. Se fossero nello stesso file, all'aggiornamento si
  dovrebbe separarli a mano, rileggendo riga per riga un codice scritto mesi prima.
- **Una pagina sola, per area** (oc:8867): il registro sta qui e non nei singoli `notes.md` dei
  cantieri, perché la domanda a cui risponde — «cosa devo togliere quando aggiorno?» — se la fa
  chi aggiorna, che non sa quali ticket siano passati di qui.

## Come ci siamo arrivati

- **Portare la correzione nel package** (oc:8867, scartata): l'avrebbe risolta per tutti, ma
  osm2cai2 non può prendersi l'aggiornamento adesso, quindi non avrebbe risolto il problema del
  cliente. Lo stesso criterio aggiuntivo per i duplicati senza uuid è stato valutato per il
  package e scartato: su camminiditalia non troverebbe nulla.
