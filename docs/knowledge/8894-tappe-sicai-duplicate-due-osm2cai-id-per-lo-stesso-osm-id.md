# Sentieri e tappe Sentiero Italia nelle API pubbliche

## Come funziona oggi

Sentieri (`app_id = 1`, costante `HikingRoute::OSM2CAI_APP_ID`) e tappe Sentiero Italia (`app_id = 2`: `SiHikingRoute` nel layer 6, `SiMTBRoute` fuori) stanno nella stessa tabella `hiking_routes` (vedi [identita-polimorfica-delle-tracce.md](identita-polimorfica-delle-tracce.md)). Una tappa SI con osm_id è figlia di un sentiero (`parent_hiking_route_id`) e ne condivide l'osm_id, ma ha un altro `id` (l'`osm2cai_id` esposto dalle API), un altro nome e quasi sempre un'altra geometria.

Non esiste uno scope globale che separi le due cose: ogni query delle API decide da sé.

- **API v2**: restituiscono solo sentieri. Il filtro su `app_id` è scritto nella query di ogni endpoint che legge più record o cerca per osm_id: elenchi per regione e bbox, ricerca per osm_id (`/hiking-route-osm/{osm_id}`, `/ecpois/osm/{osm_id}/{type}`), collection per bbox, elenco dell'export (`/export/hiking-routes/list`).
- **Letture per id** (`/hiking-route/{id}`, `/export/hiking-routes/{id}`, …): restituiscono il record chiesto, tappa SI compresa.
- **API v1**: nessun filtro. La ricerca per osm_id può restituire la tappa al posto del sentiero e gli elenchi contengono entrambi.
- **Gruppi montuosi Mitur Abruzzo** (`/api/v2/mitur_abruzzo/mountain_group/{id}`): nessun filtro, includono le tappe SI con stato 4.

Un nuovo endpoint che legge più `HikingRoute`, o ne cerca una per osm_id, deve filtrare `app_id` esplicitamente. Senza filtro e senza `ORDER BY`, `where(osm_id)->first()` restituisce la prima riga nell'ordine fisico della tabella (Seq Scan: sull'osm_id non c'è un indice), che non dipende dall'id e cambia a ogni `UPDATE` di uno dei due record.

## Perché così

- **La v2 restituisce il sentiero, mai la tappa** (oc:8894): il cliente cercava per osm_id e riceveva a volte la tappa SI, con un altro osm2cai_id. Allo scrum del 28/10/2026 è stato deciso di non mostrare le tappe SI; nel DB locale nessun osm_id esiste solo su una tappa, quindi il filtro non toglie risultati a chi cerca per osm_id.
- **Filtro nella query, non uno scope** (oc:8894): coerente con i 5 metodi v2 che lo avevano già; uno scope sul modello è stato rimandato.
- **v1 invariata** (oc:8894): l'import di Geohub usa `/api/v1/hiking-routes/region/...` e un filtro gli farebbe sparire le tappe SI. Prima di toccarla serve sapere dai log di produzione chi la chiama.
- **Niente `orderBy`** (oc:8894): gli osm_id in app 1 sono univoci nei dati, anche se nessun vincolo lo garantisce.
