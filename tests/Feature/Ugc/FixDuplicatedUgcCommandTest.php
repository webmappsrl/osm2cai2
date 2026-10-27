<?php

namespace Tests\Feature\Ugc;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Media;
use Wm\WmPackage\Models\UgcPoi;
use Wm\WmPackage\Models\UgcTrack;
use Wm\WmPackage\Models\User;

/**
 * STRATO 1 di oc:8867: il command osm2cai:fix-duplicated-ugc unisce i duplicati per uuid (POI e
 * tracce), produce il report a video e in CSV, e segnala i gruppi con record di più utenti.
 */
class FixDuplicatedUgcCommandTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private App $ugcApp;

    /** @var string[] CSV già presenti prima del command: il tearDown cancella solo quelli nuovi */
    private array $csvPrecedenti = [];

    /** Contenuto del report a nome fisso prima del test (null se non c'era): il tearDown lo ripristina */
    private ?string $csvFissoPrecedente = null;

    /** Quando è true, la cancellazione di un Media solleva un'eccezione (file già assente sullo storage) */
    private bool $falliscoCancellazioneMedia = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot dei CSV esistenti: il tearDown cancella solo quelli creati dal test, mai i report veri.
        $this->csvPrecedenti = glob(storage_path('backups/fix-duplicated-ugc-*.csv')) ?: [];

        $fisso = storage_path('backups/fix-duplicated-ugc.csv');
        $this->csvFissoPrecedente = is_file($fisso) ? file_get_contents($fisso) : null;

        Storage::fake('wmfe');
        Storage::fake('conf');

        Media::deleting(function () {
            if ($this->falliscoCancellazioneMedia) {
                throw new \RuntimeException('file già assente');
            }
        });

        $this->user = User::factory()->create();
        $this->ugcApp = App::factory()->create();
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('backups/fix-duplicated-ugc-*.csv')) ?: [] as $file) {
            if (! in_array($file, $this->csvPrecedenti, true)) {
                File::delete($file);
            }
        }

        $fisso = storage_path('backups/fix-duplicated-ugc.csv');
        if ($this->csvFissoPrecedente === null) {
            File::delete($fisso);
        } else {
            file_put_contents($fisso, $this->csvFissoPrecedente);
        }
        $this->falliscoCancellazioneMedia = false;

        parent::tearDown();
    }

    /** PNG reale: con altro contenuto la generazione delle miniature di Spatie solleva CouldNotLoadImage. */
    private function aggiungiFoto(UgcPoi $poi, string $nome): Media
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return $poi->addMedia(UploadedFile::fake()->createWithContent($nome, $png))->toMediaCollection('default');
    }

    private function creaPoi(string $uuid, string $createdAt, ?User $utente = null, string $wkt = 'POINT Z (10.5 44.5 0)'): UgcPoi
    {
        $poi = UgcPoi::factory()->create([
            'user_id' => ($utente ?? $this->user)->id,
            'app_id' => $this->ugcApp->id,
            'name' => 'Sorgente',
            'properties' => ['uuid' => $uuid],
            'geometry' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
            'created_at' => $createdAt,
        ]);
        // L'observer del package assegna il record al proprietario dell'app: l'utente si imposta dopo.
        DB::table('ugc_pois')->where('id', $poi->id)->update(['user_id' => ($utente ?? $this->user)->id]);

        return $poi;
    }

    private function creaTraccia(string $uuid, string $createdAt): UgcTrack
    {
        return UgcTrack::factory()->create([
            'user_id' => $this->user->id,
            'app_id' => $this->ugcApp->id,
            'name' => 'Traccia',
            'properties' => ['uuid' => $uuid],
            'geometry' => DB::raw("ST_GeomFromText('LINESTRING Z (10.5 44.5 0, 10.6 44.6 0)', 4326)"),
            'created_at' => $createdAt,
        ]);
    }

    private function esegui(array $opzioni = []): string
    {
        Artisan::call('osm2cai:fix-duplicated-ugc', array_merge(['--app-id' => $this->ugcApp->id], $opzioni));

        return Artisan::output();
    }

    public function test_senza_execute_non_scrive_nulla()
    {
        $this->creaPoi('uuid-report', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-report', '2026-01-02 10:00:00');
        $prima = UgcPoi::count();

        $output = $this->esegui();

        $this->assertSame($prima, UgcPoi::count());
        $this->assertSame(0, DB::table('ugc_duplicates_archive')->count());
        $this->assertStringContainsString('report', $output);
        $this->assertStringContainsString('da unire', $output);
    }

    public function test_con_execute_unisce_i_gruppi_e_archivia_le_copie()
    {
        $padre = $this->creaPoi('uuid-execute', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-execute', '2026-01-02 10:00:00');

        $output = $this->esegui(['--execute' => true]);

        $this->assertStringContainsString('unito', $output);
        $this->assertSame(1, UgcPoi::where('properties->uuid', 'uuid-execute')->count());
        $this->assertNotNull(UgcPoi::find($padre->id));
        $this->assertNull(UgcPoi::find($copia->id));
        $archiviata = DB::table('ugc_duplicates_archive')->where('uuid', 'uuid-execute')->first();
        $this->assertSame($copia->id, (int) $archiviata->original_id);
        $this->assertSame($padre->id, (int) $archiviata->reference_id);
    }

    public function test_tratta_anche_le_tracce_e_type_le_limita()
    {
        $this->creaPoi('uuid-poi', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-poi', '2026-01-02 10:00:00');
        $this->creaTraccia('uuid-traccia', '2026-01-01 10:00:00');
        $this->creaTraccia('uuid-traccia', '2026-01-02 10:00:00');

        $this->esegui(['--execute' => true, '--type' => 'tracks']);

        $this->assertSame(1, UgcTrack::where('properties->uuid', 'uuid-traccia')->count());
        $this->assertSame(2, UgcPoi::where('properties->uuid', 'uuid-poi')->count());

        $this->esegui(['--execute' => true]);

        $this->assertSame(1, UgcPoi::where('properties->uuid', 'uuid-poi')->count());
    }

    public function test_un_gruppo_con_geometrie_diverse_non_viene_toccato_e_risulta_da_verificare()
    {
        $this->creaPoi('uuid-spostato', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-spostato', '2026-01-02 10:00:00', null, 'POINT Z (10.6 44.6 0)');

        $output = $this->esegui(['--execute' => true]);

        $this->assertSame(2, UgcPoi::where('properties->uuid', 'uuid-spostato')->count());
        $this->assertSame(0, DB::table('ugc_duplicates_archive')->where('uuid', 'uuid-spostato')->count());
        $this->assertStringContainsString('da verificare', $output);
    }

    public function test_un_gruppo_multi_utente_compare_nella_sezione_dedicata_del_report()
    {
        $altro = User::factory()->create();
        $padre = $this->creaPoi('uuid-multi', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-multi', '2026-01-02 10:00:00', $altro);
        // Un gruppo con un solo utente non deve comparire nella sezione.
        $this->creaPoi('uuid-mono', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-mono', '2026-01-02 10:00:00');

        $output = $this->esegui();

        $this->assertStringContainsString('1 gruppi con record di più utenti', $output);
        $this->assertSame(2, substr_count($output, 'uuid-multi'), 'una volta nella tabella principale e una nella sezione multi-utente');
        $this->assertSame(1, substr_count($output, 'uuid-mono'));
        $this->assertStringContainsString($copia->id.':'.$altro->id, $output);
        $this->assertStringContainsString((string) $padre->id, $output);
    }

    public function test_senza_gruppi_multi_utente_la_sezione_non_compare()
    {
        $this->creaPoi('uuid-mono', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-mono', '2026-01-02 10:00:00');

        $this->assertStringNotContainsString('più utenti', $this->esegui());
    }

    /** Imposta user_id a NULL con un UPDATE: la colonna è nullable, ma factory e observer assegnano sempre un utente. */
    private function senzaUtente(UgcPoi $poi): void
    {
        DB::table('ugc_pois')->where('id', $poi->id)->update(['user_id' => null]);
    }

    public function test_un_gruppo_con_il_padre_senza_utente_e_una_copia_con_utente_non_si_fonde()
    {
        $padre = $this->creaPoi('uuid-padre-nullo', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-padre-nullo', '2026-01-02 10:00:00');
        $this->senzaUtente($padre);

        $output = $this->esegui(['--execute' => true]);

        $this->assertSame(2, UgcPoi::where('properties->uuid', 'uuid-padre-nullo')->count());
        $this->assertSame(0, DB::table('ugc_duplicates_archive')->where('uuid', 'uuid-padre-nullo')->count());
        $this->assertStringContainsString('da verificare (padre senza utente)', $output);
        $this->assertStringNotContainsString('geometria diversa', $output);
    }

    public function test_la_geometria_diversa_e_il_padre_senza_utente_si_distinguono_nel_report()
    {
        $padre = $this->creaPoi('uuid-entrambi', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-entrambi', '2026-01-02 10:00:00', null, 'POINT Z (10.6 44.6 0)');
        $this->creaPoi('uuid-solo-geo', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-solo-geo', '2026-01-02 10:00:00', null, 'POINT Z (10.6 44.6 0)');
        $this->senzaUtente($padre);

        $output = $this->esegui();

        $this->assertStringContainsString('da verificare (geometria diversa, padre senza utente)', $output);
        $this->assertStringContainsString('da verificare (geometria diversa)', $output);
    }

    public function test_si_fonde_se_la_copia_e_senza_utente_o_se_lo_sono_tutti()
    {
        // Padre con utente e copia NULL: nessuna attribuzione si perde.
        $this->creaPoi('uuid-copia-nulla', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-copia-nulla', '2026-01-02 10:00:00');
        $this->senzaUtente($copia);
        // Tutti NULL.
        $a = $this->creaPoi('uuid-tutti-nulli', '2026-01-01 10:00:00');
        $b = $this->creaPoi('uuid-tutti-nulli', '2026-01-02 10:00:00');
        $this->senzaUtente($a);
        $this->senzaUtente($b);

        $output = $this->esegui(['--execute' => true]);

        $this->assertSame(1, UgcPoi::where('properties->uuid', 'uuid-copia-nulla')->count());
        $this->assertSame(1, UgcPoi::where('properties->uuid', 'uuid-tutti-nulli')->count());
        $this->assertStringNotContainsString('da verificare', $output);
    }

    public function test_senza_gruppi_non_scrive_il_csv()
    {
        $output = $this->esegui();

        $this->assertSame([], array_diff(glob(storage_path('backups/fix-duplicated-ugc-*.csv')) ?: [], $this->csvPrecedenti));
        $fisso = storage_path('backups/fix-duplicated-ugc.csv');
        $this->assertSame($this->csvFissoPrecedente, is_file($fisso) ? file_get_contents($fisso) : null);
        $this->assertStringContainsString('report CSV non scritto', $output);
    }

    public function test_un_user_id_null_non_conta_come_utente_nella_sezione_multi_utente()
    {
        $this->creaPoi('uuid-null-multi', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-null-multi', '2026-01-02 10:00:00');
        $this->senzaUtente($copia);

        $output = $this->esegui();

        $this->assertStringContainsString('da unire', $output);
        $this->assertStringNotContainsString('multi-utente', $output);
        $this->assertStringNotContainsString('più utenti', $output);
    }

    public function test_il_gruppo_bloccato_per_padre_senza_utente_resta_nella_sezione_se_ha_piu_utenti_reali()
    {
        $altro = User::factory()->create();
        $padre = $this->creaPoi('uuid-bloccato-multi', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-bloccato-multi', '2026-01-02 10:00:00');
        $this->creaPoi('uuid-bloccato-multi', '2026-01-03 10:00:00', $altro);
        $this->senzaUtente($padre);

        $output = $this->esegui();

        $this->assertStringContainsString('1 gruppi con record di più utenti', $output);
        $this->assertSame(2, substr_count($output, 'uuid-bloccato-multi'));
        $this->assertStringContainsString('da verificare (padre senza utente)', $output);
    }

    public function test_il_riepilogo_dice_quanti_gruppi_si_fondono_e_quanti_no()
    {
        $this->creaPoi('uuid-ok-1', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-ok-1', '2026-01-02 10:00:00');
        $this->creaPoi('uuid-ok-2', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-ok-2', '2026-01-02 10:00:00');
        $this->creaPoi('uuid-geo', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-geo', '2026-01-02 10:00:00', null, 'POINT Z (10.6 44.6 0)');
        $padre = $this->creaPoi('uuid-orfano', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-orfano', '2026-01-02 10:00:00');
        $this->senzaUtente($padre);

        $output = $this->esegui();

        $this->assertStringContainsString('Riepilogo: 2 da unire; 1 da verificare (geometria diversa); 1 da verificare (padre senza utente).', $output);
    }

    public function test_type_non_valido_da_errore()
    {
        $this->assertSame(1, Artisan::call('osm2cai:fix-duplicated-ugc', ['--type' => 'foo']));
        $this->assertStringContainsString('--type deve essere tracks oppure pois', Artisan::output());
    }

    public function test_app_id_non_numerico_da_errore_esplicito_e_non_tocca_nulla()
    {
        $this->creaPoi('uuid-app', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-app', '2026-01-02 10:00:00');

        $this->assertSame(1, Artisan::call('osm2cai:fix-duplicated-ugc', ['--app-id' => 'iNaturalist', '--execute' => true]));

        $this->assertStringContainsString('--app-id accetta solo app numeriche', Artisan::output());
        $this->assertSame(2, UgcPoi::where('properties->uuid', 'uuid-app')->count());
    }

    public function test_max_distance_maggiore_di_zero_fonde_anche_geometrie_diverse()
    {
        $this->creaPoi('uuid-vicino', '2026-01-01 10:00:00');
        // Circa 1,1 m a nord del padre (0.00001 gradi di latitudine).
        $this->creaPoi('uuid-vicino', '2026-01-02 10:00:00', null, 'POINT Z (10.5 44.50001 0)');

        $this->esegui(['--execute' => true, '--max-distance' => '0']);
        $this->assertSame(2, UgcPoi::where('properties->uuid', 'uuid-vicino')->count());

        $output = $this->esegui(['--execute' => true, '--max-distance' => '5']);

        $this->assertSame(1, UgcPoi::where('properties->uuid', 'uuid-vicino')->count());
        $this->assertStringContainsString('unito', $output);
    }

    public function test_con_execute_il_csv_ha_il_nome_con_timestamp_e_non_sovrascrive_quello_fisso()
    {
        $this->creaPoi('uuid-ts', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-ts', '2026-01-02 10:00:00');
        $fisso = storage_path('backups/fix-duplicated-ugc.csv');
        file_put_contents($fisso, 'sentinella');

        $this->esegui(['--execute' => true]);

        $this->assertCount(1, array_diff(glob(storage_path('backups/fix-duplicated-ugc-*.csv')), $this->csvPrecedenti));
        $this->assertSame('sentinella', file_get_contents($fisso));
    }

    public function test_senza_execute_il_csv_a_nome_fisso_viene_sovrascritto()
    {
        $this->creaPoi('uuid-fisso', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-fisso', '2026-01-02 10:00:00');
        $fisso = storage_path('backups/fix-duplicated-ugc.csv');
        file_put_contents($fisso, 'vecchio');

        $this->esegui();
        $this->esegui();

        $this->assertStringContainsString('uuid-fisso', file_get_contents($fisso));
        $this->assertSame([], array_diff(glob(storage_path('backups/fix-duplicated-ugc-*.csv')), $this->csvPrecedenti));
    }

    public function test_un_media_che_non_si_cancella_non_interrompe_il_run_e_viene_contato()
    {
        $padre = $this->creaPoi('uuid-media', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-media', '2026-01-02 10:00:00');
        $this->aggiungiFoto($padre, 'image_0.png');
        $this->aggiungiFoto($copia, 'image_0.png');
        // Un secondo gruppo, fuso dopo il primo: deve arrivare in fondo comunque.
        $this->creaPoi('uuid-dopo', '2026-02-01 10:00:00');
        $this->creaPoi('uuid-dopo', '2026-02-02 10:00:00');
        $this->falliscoCancellazioneMedia = true;

        $output = $this->esegui(['--execute' => true]);

        $this->assertStringContainsString('Media doppioni cancellati: 0, falliti: 1', $output);
        $this->assertSame(1, UgcPoi::where('properties->uuid', 'uuid-media')->count());
        $this->assertSame(1, UgcPoi::where('properties->uuid', 'uuid-dopo')->count());
    }

    public function test_un_media_doppione_cancellato_viene_contato()
    {
        $padre = $this->creaPoi('uuid-media-ok', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-media-ok', '2026-01-02 10:00:00');
        $this->aggiungiFoto($padre, 'image_0.png');
        $this->aggiungiFoto($copia, 'image_0.png');

        $output = $this->esegui(['--execute' => true]);

        $this->assertStringContainsString('Media doppioni cancellati: 1, falliti: 0', $output);
    }

    public function test_il_vecchio_nome_non_esiste_piu()
    {
        $this->expectException(CommandNotFoundException::class);

        Artisan::call('osm2cai:fix-duplicated-ugc-pois', ['--execute' => 1]);
    }

    public function test_scrive_il_report_csv_sotto_storage_backups()
    {
        $altro = User::factory()->create();
        $padre = $this->creaPoi('uuid-csv', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-csv', '2026-01-02 10:00:00', $altro);

        $this->esegui();

        // Senza --execute il nome è fisso e nessun file con timestamp viene creato.
        $this->assertSame([], array_diff(glob(storage_path('backups/fix-duplicated-ugc-*.csv')), $this->csvPrecedenti));
        $righe = array_map(fn ($riga) => str_getcsv($riga, ',', '"', ''), file(storage_path('backups/fix-duplicated-ugc.csv'), FILE_IGNORE_NEW_LINES));
        $this->assertSame(['tipo', 'uuid', 'padre', 'copie', 'distanza max (m)', 'esito', 'utenti'], $righe[0]);
        $this->assertCount(2, $righe);
        $this->assertSame(['pois', 'uuid-csv', (string) $padre->id, (string) $copia->id, '0.00', 'da unire', $padre->id.':'.$this->user->id.' '.$copia->id.':'.$altro->id], $righe[1]);
    }
}
