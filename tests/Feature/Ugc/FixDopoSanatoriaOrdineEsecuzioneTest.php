<?php

namespace Tests\Feature\Ugc;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\UgcPoi;
use Wm\WmPackage\Models\UgcTrack;
use Wm\WmPackage\Models\User;

/**
 * STRATO 2 di oc:8867: l'ordine di esecuzione dei due command è vincolante. La sanatoria
 * (osm2cai:recover-previous-duplicate-fix) va prima della bonifica (osm2cai:fix-duplicated-ugc): fondendo una
 * copia marcata dalla vecchia bonifica le chiavi duplicated, duplication_info e has_been_duplicated
 * passerebbero sul record tenuto, che la sanatoria non ripulisce più (esclude ogni riga con la chiave
 * duplicated). La bonifica quindi si ferma, in dry-run come con --execute, finché ne restano.
 */
class FixDopoSanatoriaOrdineEsecuzioneTest extends TestCase
{
    use DatabaseTransactions;

    private User $autore;

    private User $servizio;

    private App $ugcApp;

    /** @var string[] CSV già presenti prima del test: il tearDown cancella solo quelli nuovi */
    private array $csvPrecedenti = [];

    private ?string $csvFissoPrecedente = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csvPrecedenti = glob(storage_path('backups/fix-duplicated-ugc-*.csv')) ?: [];
        $fisso = storage_path('backups/fix-duplicated-ugc.csv');
        $this->csvFissoPrecedente = is_file($fisso) ? file_get_contents($fisso) : null;

        Storage::fake('wmfe');
        Storage::fake('conf');

        $this->autore = User::factory()->create();
        $this->servizio = User::factory()->create(['email' => 'duplicated-ugc-pois@webmapp.it']);
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

        parent::tearDown();
    }

    private function creaPoi(array $properties, ?int $userId = null, string $createdAt = '2026-01-01 10:00:00'): UgcPoi
    {
        $poi = UgcPoi::factory()->create([
            'user_id' => $userId ?? $this->autore->id,
            'app_id' => $this->ugcApp->id,
            'name' => 'Sorgente',
            'properties' => $properties,
            'geometry' => DB::raw("ST_GeomFromText('POINT Z (10.5 44.5 0)', 4326)"),
            'created_at' => $createdAt,
        ]);
        // L'observer del package assegna il record al proprietario dell'app: l'utente si imposta dopo.
        DB::table('ugc_pois')->where('id', $poi->id)->update(['user_id' => $userId ?? $this->autore->id]);

        return $poi;
    }

    /** Record tenuto e copia lasciata dalla vecchia bonifica: stesso uuid, copia marcata e dell'utente di servizio. */
    private function creaCoppiaDellaVecchiaBonifica(string $uuid): array
    {
        $padre = $this->creaPoi(['uuid' => $uuid, 'has_been_duplicated' => true, 'duplication_info' => ['copies' => 1]], null, '2026-01-01 10:00:00');
        $copia = $this->creaPoi([
            'uuid' => $uuid,
            'duplicated' => true,
            'duplication_info' => ['parent_ugc_poi_id' => $padre->id, 'parent_ugc_poi_user_id' => $this->autore->id],
        ], $this->servizio->id, '2026-01-02 10:00:00');

        return [$padre, $copia];
    }

    private function bonifica(array $opzioni = []): array
    {
        $exit = Artisan::call('osm2cai:fix-duplicated-ugc', array_merge(['--app-id' => $this->ugcApp->id], $opzioni));

        return [$exit, Artisan::output()];
    }

    /** Righe di ugc_pois che portano ancora una chiave della vecchia bonifica. */
    private function righeMarcate(): int
    {
        return DB::table('ugc_pois')->where('app_id', $this->ugcApp->id)
            ->whereRaw("(jsonb_exists(properties, 'duplicated') OR jsonb_exists(properties, 'duplication_info') OR jsonb_exists(properties, 'has_been_duplicated'))")
            ->count();
    }

    public function test_la_bonifica_in_dry_run_si_ferma_se_restano_copie_marcate()
    {
        $this->creaCoppiaDellaVecchiaBonifica('uuid-vecchia');
        // Il report a nome fisso può esistere da una prova precedente: il tearDown lo ripristina.
        File::delete(storage_path('backups/fix-duplicated-ugc.csv'));

        [$exit, $output] = $this->bonifica();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Esegui prima la sanatoria', $output);
        $this->assertStringContainsString('osm2cai:recover-previous-duplicate-fix', $output);
        // Si è fermata prima di tutto: nessun report e nessuna tabella di gruppi.
        $this->assertStringNotContainsString('gruppi trovati', $output);
        $this->assertFileDoesNotExist(storage_path('backups/fix-duplicated-ugc.csv'));
    }

    public function test_la_bonifica_con_execute_si_ferma_e_non_tocca_nulla()
    {
        [$padre, $copia] = $this->creaCoppiaDellaVecchiaBonifica('uuid-vecchia');
        // Un duplicato qualsiasi, che senza la guardia si fonderebbe.
        $a = $this->creaPoi(['uuid' => 'uuid-normale'], null, '2026-02-01 10:00:00');
        $b = $this->creaPoi(['uuid' => 'uuid-normale'], null, '2026-02-02 10:00:00');

        [$exit, $output] = $this->bonifica(['--execute' => true]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Esegui prima la sanatoria', $output);
        foreach ([$padre, $copia, $a, $b] as $poi) {
            $this->assertNotNull(UgcPoi::find($poi->id), "il record {$poi->id} è stato cancellato");
        }
        $this->assertSame(0, DB::table('ugc_duplicates_archive')->count());
        $this->assertTrue((bool) UgcPoi::find($padre->id)->properties['has_been_duplicated']);
    }

    public function test_la_guardia_scatta_per_la_chiave_di_qualunque_valore()
    {
        $this->creaPoi(['uuid' => 'uuid-stringa', 'duplicated' => 'true']);

        [$exit] = $this->bonifica();

        $this->assertSame(1, $exit);
    }

    public function test_senza_copie_marcate_la_bonifica_procede()
    {
        $this->creaPoi(['uuid' => 'uuid-normale'], null, '2026-02-01 10:00:00');
        $this->creaPoi(['uuid' => 'uuid-normale'], null, '2026-02-02 10:00:00');

        [$exit, $output] = $this->bonifica(['--execute' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('gruppi trovati', $output);
        $this->assertSame(1, UgcPoi::where('properties->uuid', 'uuid-normale')->count());
    }

    public function test_la_guardia_non_riguarda_le_tracce()
    {
        $this->creaPoi(['uuid' => 'uuid-vecchia', 'duplicated' => true]);

        [$exit] = $this->bonifica(['--type' => 'tracks']);

        $this->assertSame(0, $exit);
    }

    public function test_la_guardia_scatta_anche_per_le_tracce_marcate_con_type_tracks()
    {
        UgcTrack::factory()->create([
            'user_id' => $this->autore->id,
            'app_id' => $this->ugcApp->id,
            'name' => 'Traccia',
            'properties' => ['uuid' => 'uuid-traccia', 'duplicated' => true],
            'geometry' => DB::raw("ST_GeomFromText('LINESTRING Z (10.5 44.5 0, 10.6 44.6 0)', 4326)"),
        ]);

        [$exit, $output] = $this->bonifica(['--type' => 'tracks']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('ugc_tracks', $output);
        $this->assertStringContainsString('Esegui prima la sanatoria', $output);
    }

    public function test_nell_ordine_giusto_sanatoria_poi_bonifica_nessun_record_tenuto_resta_marcato()
    {
        [$padre, $copia] = $this->creaCoppiaDellaVecchiaBonifica('uuid-vecchia');
        $a = $this->creaPoi(['uuid' => 'uuid-normale'], null, '2026-02-01 10:00:00');
        $b = $this->creaPoi(['uuid' => 'uuid-normale'], null, '2026-02-02 10:00:00');

        // Prima la sanatoria: archivia la copia marcata e ripulisce il record tenuto.
        Artisan::call('osm2cai:recover-previous-duplicate-fix', ['--execute' => true]);
        $this->assertNull(UgcPoi::find($copia->id));
        $this->assertSame(0, $this->righeMarcate());

        // Poi la bonifica: ora non c'è più nulla che la fermi, e fonde solo il duplicato vero.
        [$exit, $output] = $this->bonifica(['--execute' => true]);

        $this->assertSame(0, $exit, $output);
        $this->assertNotNull(UgcPoi::find($padre->id));
        $this->assertNull(UgcPoi::find($b->id));
        $this->assertNotNull(UgcPoi::find($a->id));
        // Nessuna chiave della vecchia bonifica è passata su un record legittimo.
        $this->assertSame(0, $this->righeMarcate());
    }

    public function test_la_dry_run_della_bonifica_dopo_la_sanatoria_non_e_bloccata()
    {
        $this->creaCoppiaDellaVecchiaBonifica('uuid-vecchia');
        Artisan::call('osm2cai:recover-previous-duplicate-fix', ['--execute' => true]);

        [$exit] = $this->bonifica();

        $this->assertSame(0, $exit);
    }
}
