<?php

namespace Tests\Feature\Ugc;

use App\Services\UgcDuplicatesByTimestampService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\UgcPoi;
use Wm\WmPackage\Models\User;

/**
 * STRATO 2 di oc:8867: raggruppamento dei duplicati senza uuid per utente, istante di compilazione
 * (properties.createdAt) e posizione. Il caso che governa tutto è il monitoraggio ripetuto della
 * stessa sorgente, che non deve mai essere unito.
 */
class UgcDuplicatesByTimestampTest extends TestCase
{
    use DatabaseTransactions;

    private const ISTANTE = '2024-05-10T08:30:00.000Z';

    private User $user;

    private App $ugcApp;

    /** @var string[] CSV già presenti prima del command: il tearDown cancella solo quelli nuovi */
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

        parent::tearDown();
    }

    /** POI senza uuid; l'istante di compilazione è in properties.createdAt, se presente. */
    private function creaPoi(?string $istante, string $createdAt, ?User $utente = null, string $wkt = 'POINT Z (10.5 44.5 0)', array $properties = []): UgcPoi
    {
        $utente ??= $this->user;
        $props = $properties;
        if ($istante !== null) {
            $props['createdAt'] = $istante;
        }
        $poi = UgcPoi::factory()->create([
            'user_id' => $utente->id,
            'app_id' => $this->ugcApp->id,
            'name' => 'Sorgente',
            'properties' => $props,
            'geometry' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
            'created_at' => $createdAt,
        ]);
        // L'observer del package assegna il record al proprietario dell'app: l'utente si imposta dopo.
        DB::table('ugc_pois')->where('id', $poi->id)->update(['user_id' => $utente->id]);

        return $poi;
    }

    private function gruppi(): array
    {
        return UgcDuplicatesByTimestampService::make()->groups(UgcPoi::class, $this->ugcApp->id)->all();
    }

    private function esegui(array $opzioni = []): string
    {
        Artisan::call('osm2cai:fix-duplicated-ugc', array_merge(['--app-id' => $this->ugcApp->id], $opzioni));

        return Artisan::output();
    }

    public function test_raggruppa_i_record_senza_uuid_per_utente_istante_di_compilazione_e_posizione()
    {
        $padre = $this->creaPoi(self::ISTANTE, '2026-01-01 10:00:00');
        $copiaA = $this->creaPoi(self::ISTANTE, '2026-01-02 10:00:00');
        $copiaB = $this->creaPoi(self::ISTANTE, '2026-01-03 10:00:00');

        $gruppi = $this->gruppi();

        $this->assertCount(1, $gruppi);
        // Stessa forma dei gruppi per uuid, con uuid a null; padre = il più vecchio.
        $this->assertSame(['uuid', 'parent_id', 'copy_ids', 'max_distance_m'], array_keys($gruppi[0]));
        $this->assertNull($gruppi[0]['uuid']);
        $this->assertSame($padre->id, $gruppi[0]['parent_id']);
        $this->assertSame([$copiaA->id, $copiaB->id], $gruppi[0]['copy_ids']);
        $this->assertEqualsWithDelta(0.0, $gruppi[0]['max_distance_m'], 0.001);
    }

    public function test_non_unisce_due_monitoraggi_della_stessa_sorgente_con_istanti_diversi()
    {
        // Stesso utente, stessa posizione, istanti di compilazione diversi: due monitoraggi distinti.
        $primo = $this->creaPoi('2025-01-10T08:30:00.000Z', '2025-01-10 09:00:00');
        $secondo = $this->creaPoi('2025-02-10T08:30:00.000Z', '2025-02-10 09:00:00');

        $this->assertSame([], $this->gruppi());

        $this->esegui(['--execute' => true, '--by-timestamp' => true]);

        $this->assertNotNull(UgcPoi::find($primo->id));
        $this->assertNotNull(UgcPoi::find($secondo->id));
        $this->assertSame(0, DB::table('ugc_duplicates_archive')->count());
    }

    public function test_i_record_senza_uuid_e_senza_istante_non_vengono_mai_uniti()
    {
        // Né uuid né createdAt (assente, vuoto o null): non c'è nulla che dica che siano la stessa scheda.
        $this->creaPoi(null, '2026-01-01 10:00:00');
        $this->creaPoi(null, '2026-01-02 10:00:00');
        $this->creaPoi('', '2026-01-03 10:00:00');
        $this->creaPoi('', '2026-01-04 10:00:00');
        $this->creaPoi(null, '2026-01-05 10:00:00', null, 'POINT Z (10.5 44.5 0)', ['createdAt' => null]);
        $this->creaPoi(null, '2026-01-06 10:00:00', null, 'POINT Z (10.5 44.5 0)', ['createdAt' => null]);

        $this->assertSame([], $this->gruppi());

        $this->esegui(['--execute' => true, '--by-timestamp' => true]);

        $this->assertSame(6, UgcPoi::where('app_id', $this->ugcApp->id)->count());
    }

    public function test_i_record_con_uuid_non_entrano_in_questo_raggruppamento()
    {
        // Stesso istante, stesso utente e posizione, ma con uuid: li tratta lo strato 1, non questo.
        $this->creaPoi(self::ISTANTE, '2026-01-01 10:00:00', null, 'POINT Z (10.5 44.5 0)', ['uuid' => 'uuid-a']);
        $this->creaPoi(self::ISTANTE, '2026-01-02 10:00:00', null, 'POINT Z (10.5 44.5 0)', ['uuid' => 'uuid-b']);
        // Uno con uuid e uno senza: nemmeno questi si uniscono.
        $this->creaPoi(self::ISTANTE, '2026-01-03 10:00:00', null, 'POINT Z (10.5 44.5 0)', ['uuid' => 'uuid-c']);
        $this->creaPoi(self::ISTANTE, '2026-01-04 10:00:00');

        $this->assertSame([], $this->gruppi());
    }

    public function test_il_command_senza_by_timestamp_ignora_i_record_senza_uuid()
    {
        $padre = $this->creaPoi(self::ISTANTE, '2026-01-01 10:00:00');
        $copia = $this->creaPoi(self::ISTANTE, '2026-01-02 10:00:00');

        $output = $this->esegui(['--execute' => true]);

        $this->assertStringContainsString('0 gruppi trovati', $output);
        $this->assertNotNull(UgcPoi::find($padre->id));
        $this->assertNotNull(UgcPoi::find($copia->id));
        $this->assertSame(0, DB::table('ugc_duplicates_archive')->count());
    }

    public function test_il_command_con_by_timestamp_riporta_senza_scrivere_e_con_execute_unisce()
    {
        $padre = $this->creaPoi(self::ISTANTE, '2026-01-01 10:00:00');
        $copia = $this->creaPoi(self::ISTANTE, '2026-01-02 10:00:00');

        $output = $this->esegui(['--by-timestamp' => true]);

        $this->assertStringContainsString('da unire', $output);
        $this->assertNotNull(UgcPoi::find($copia->id));

        $this->esegui(['--execute' => true, '--by-timestamp' => true]);

        $this->assertNotNull(UgcPoi::find($padre->id));
        $this->assertNull(UgcPoi::find($copia->id));
        $archiviata = DB::table('ugc_duplicates_archive')->where('original_id', $copia->id)->first();
        $this->assertSame($padre->id, (int) $archiviata->reference_id);
        $this->assertNull($archiviata->uuid);
    }

    public function test_utenti_diversi_o_posizioni_diverse_non_si_raggruppano()
    {
        $altro = User::factory()->create();
        $this->creaPoi(self::ISTANTE, '2026-01-01 10:00:00');
        $this->creaPoi(self::ISTANTE, '2026-01-02 10:00:00', $altro);
        $this->creaPoi(self::ISTANTE, '2026-01-03 10:00:00', null, 'POINT Z (10.6 44.6 0)');

        $this->assertSame([], $this->gruppi());
    }

    public function test_i_record_senza_utente_non_entrano_nei_gruppi()
    {
        // Senza utente non si può dire che due record siano dello stesso utente: non entrano nei gruppi.
        $a = $this->creaPoi(self::ISTANTE, '2026-01-01 10:00:00');
        $b = $this->creaPoi(self::ISTANTE, '2026-01-02 10:00:00');
        DB::table('ugc_pois')->whereIn('id', [$a->id, $b->id])->update(['user_id' => null]);

        $this->assertSame([], $this->gruppi());
    }
}
