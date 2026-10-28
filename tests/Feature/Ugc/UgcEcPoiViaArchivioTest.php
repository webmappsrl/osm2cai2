<?php

namespace Tests\Feature\Ugc;

use App\Models\UgcPoi;
use App\Models\UgcTrack;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\UgcPoi as PackageUgcPoi;
use Wm\WmPackage\Models\User;

/**
 * oc:8867: `checkExistingEcPoi()` deve riconoscere come già promosso un UgcPoi sopravvissuto alla bonifica
 * dei duplicati anche quando l'EcPoi era stato promosso da una copia poi cancellata (il suo
 * `properties->ugc->ugc_poi_id` punta a un id archiviato in `ugc_duplicates_archive`). Si cambia solo la
 * lettura: il riferimento storico dell'EcPoi non va riscritto.
 */
class UgcEcPoiViaArchivioTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private App $ugcApp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('wmfe');
        Storage::fake('conf');

        $this->user = User::factory()->create();
        $this->ugcApp = App::factory()->create();
    }

    private function creaPoi(): UgcPoi
    {
        $id = PackageUgcPoi::factory()->create([
            'user_id' => $this->user->id,
            'app_id' => $this->ugcApp->id,
            'name' => 'Sorgente SEC',
            'properties' => ['uuid' => (string) fake()->uuid()],
            'geometry' => DB::raw("ST_GeomFromText('POINT Z (10.5 44.5 0)', 4326)"),
        ])->id;

        return UgcPoi::findOrFail($id);
    }

    /** Inserisce l'EcPoi con SQL diretto: l'observer e i job di sync non c'entrano con ciò che si prova. */
    private function creaEcPoiPromossoDa(int $ugcPoiId): int
    {
        return DB::table('ec_pois')->insertGetId([
            'name' => 'Sorgente SEC',
            'type' => 'natural_spring',
            'app_id' => $this->ugcApp->id,
            'user_id' => $this->user->id,
            'properties' => json_encode(['ugc' => ['ugc_poi_id' => $ugcPoiId]]),
            'geometry' => DB::raw("ST_GeomFromText('POINT Z (10.5 44.5 0)', 4326)"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function archivia(string $modelType, int $originalId, int $referenceId): void
    {
        DB::table('ugc_duplicates_archive')->insert([
            'model_type' => $modelType,
            'original_id' => $originalId,
            'reference_id' => $referenceId,
            'archived_at' => now(),
        ]);
    }

    private function giaPromosso(object $ugc): bool
    {
        $metodo = new \ReflectionMethod($ugc, 'checkExistingEcPoi');
        $metodo->setAccessible(true);

        return $metodo->invoke($ugc);
    }

    public function test_un_poi_con_ecpoi_promosso_da_una_copia_cancellata_risulta_gia_promosso(): void
    {
        $padre = $this->creaPoi();
        $idCopiaCancellata = $padre->id + 100000;
        $ecPoiId = $this->creaEcPoiPromossoDa($idCopiaCancellata);
        $this->archivia($padre->getMorphClass(), $idCopiaCancellata, $padre->id);

        Log::spy();

        $this->assertTrue($this->giaPromosso($padre));

        // Il log di warning dice che l'EcPoi è stato trovato dall'archivio e da quale original_id.
        Log::shouldHaveReceived('warning')->withArgs(
            fn ($msg, $ctx) => ($ctx['trovato_via_archivio'] ?? false) === true
                && $ctx['original_id'] === $idCopiaCancellata
                && $ctx['ec_poi_id'] === $ecPoiId
                && $ctx['ugc_id'] === $padre->id
        )->once();

        // Nessun dato riscritto: il riferimento storico resta quello originale.
        $this->assertSame(
            $idCopiaCancellata,
            (int) DB::table('ec_pois')->where('id', $ecPoiId)->value(DB::raw("properties->'ugc'->>'ugc_poi_id'"))
        );
    }

    public function test_con_due_ecpoi_da_copie_diverse_si_sceglie_il_piu_vecchio(): void
    {
        $padre = $this->creaPoi();
        $copiaA = $padre->id + 100000;
        $copiaB = $padre->id + 100001;
        $this->archivia($padre->getMorphClass(), $copiaA, $padre->id);
        $this->archivia($padre->getMorphClass(), $copiaB, $padre->id);

        // Creati in ordine inverso rispetto agli original_id: conta l'id dell'EcPoi, non l'ordine dell'archivio.
        $ecPoiVecchio = $this->creaEcPoiPromossoDa($copiaB);
        $this->creaEcPoiPromossoDa($copiaA);

        // Un UPDATE sposta la riga fisicamente in coda: senza ORDER BY Postgres restituirebbe l'altro EcPoi.
        DB::table('ec_pois')->where('id', $ecPoiVecchio)->update(['updated_at' => now()]);

        Log::spy();

        $this->assertTrue($this->giaPromosso($padre));

        Log::shouldHaveReceived('warning')->withArgs(
            fn ($msg, $ctx) => ($ctx['trovato_via_archivio'] ?? false) === true
                && $ctx['ec_poi_id'] === $ecPoiVecchio
                && $ctx['original_id'] === $copiaB
        )->once();
    }

    public function test_un_poi_senza_nessun_ecpoi_risulta_non_promosso(): void
    {
        $poi = $this->creaPoi();

        // Anche con una riga d'archivio, se nessun EcPoi punta a quell'id il POI non è promosso.
        $this->archivia($poi->getMorphClass(), $poi->id + 100000, $poi->id);

        $this->assertFalse($this->giaPromosso($poi));
        $this->assertFalse($this->giaPromosso($this->creaPoi()));
    }

    public function test_la_ricerca_diretta_continua_a_funzionare_senza_toccare_l_archivio(): void
    {
        $poi = $this->creaPoi();
        $ecPoiId = $this->creaEcPoiPromossoDa($poi->id);

        Log::spy();

        $this->assertTrue($this->giaPromosso($poi));

        // Trovato direttamente: il log non dichiara il passaggio dall'archivio.
        Log::shouldHaveReceived('warning')->withArgs(
            fn ($msg, $ctx) => ! array_key_exists('trovato_via_archivio', $ctx) && $ctx['ec_poi_id'] === $ecPoiId
        )->once();
    }

    public function test_l_archivio_di_un_altro_model_type_non_conta(): void
    {
        $padre = $this->creaPoi();
        $idCopia = $padre->id + 100000;
        $this->creaEcPoiPromossoDa($idCopia);
        // Stesso id, ma archiviato come traccia: non riguarda i POI.
        $this->archivia(UgcTrack::class, $idCopia, $padre->id);

        $this->assertFalse($this->giaPromosso($padre));
    }

    public function test_senza_la_tabella_archivio_si_comporta_come_prima(): void
    {
        $padre = $this->creaPoi();
        $idCopia = $padre->id + 100000;
        $this->creaEcPoiPromossoDa($idCopia);

        // DatabaseTransactions: il DROP in transazione si annulla a fine test (Postgres ha DDL transazionale).
        DB::statement('DROP TABLE ugc_duplicates_archive');

        $this->assertFalse($this->giaPromosso($padre));

        // La ricerca diretta funziona anche senza tabella.
        $diretto = $this->creaPoi();
        $this->creaEcPoiPromossoDa($diretto->id);
        $this->assertTrue($this->giaPromosso($diretto));
    }

    public function test_le_tracce_non_cercano_nell_archivio(): void
    {
        $traccia = new UgcTrack;
        $traccia->id = 777001;
        $this->creaEcPoiPromossoDa(777002);
        $this->archivia(UgcTrack::class, 777002, 777001);

        // `ugc_poi_id` contiene id di UgcPoi: per una traccia l'archivio darebbe falsi positivi.
        $this->assertFalse($this->giaPromosso($traccia));
    }
}
