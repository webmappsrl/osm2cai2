# Micro-fix oc:8867

1. Guardia: `guardiaCopieMarcate(array $types)` conta le righe con `properties.duplicated` in ogni tabella dei tipi da trattare (ugc_pois e/o ugc_tracks). Test: `test_la_guardia_scatta_anche_per_le_tracce_marcate_con_type_tracks`.
2. Registro: annotato in `docs/knowledge/custom-in-attesa-dell-aggiornamento-di-wm-package.md` che cancellando `RecoverPreviousDuplicateFixCommand` va aggiornato (o tolto) il messaggio della guardia.
3. Report: `riferisciEsclusi()` non stampa nulla se gli esclusi sono zero. Test: `test_by_timestamp_senza_esclusi_non_stampa_la_sezione_degli_esclusi`.

Test: `--filter=Ugc` 101 passati (419 asserzioni). Pint pulito. DB osm2cai2 non toccato.
