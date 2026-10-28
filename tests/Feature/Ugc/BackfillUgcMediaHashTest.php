<?php

namespace Tests\Feature\Ugc;

use App\Services\UgcMediaHashService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Media;
use Wm\WmPackage\Models\UgcPoi as PackageUgcPoi;
use Wm\WmPackage\Models\UgcTrack as PackageUgcTrack;
use Wm\WmPackage\Models\User;

/**
 * STRATO 2 di oc:8867: il command popola lo sha256 dei media, in sola segnalazione non scrive, e
 * guarda **soltanto** i rilevamenti che fanno parte di un gruppo di duplicati — gli unici che la
 * bonifica confronta. Sugli altri l'hash si calcolerebbe per niente, scaricando le fotografie.
 */
class BackfillUgcMediaHashTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('wmfe');
        Storage::fake('conf');
    }

    /** Due rilevamenti con lo stesso uuid (un gruppo di duplicati) e un media senza hash sul primo. */
    private function mediaSuUnDuplicato(string $nome, int $lato, string $classe = PackageUgcPoi::class): Media
    {
        $uuid = (string) Str::uuid();
        $comuni = [
            'user_id' => User::factory()->create()->id,
            'app_id' => App::factory()->create()->id,
            'properties' => ['uuid' => $uuid],
        ];

        $padre = $classe::factory()->create($comuni);
        $classe::factory()->create($comuni);

        return $this->mediaSenzaHash($padre, $nome, $lato);
    }

    /** Un rilevamento con uuid tutto suo: non è duplicato di nessuno. */
    private function mediaSuUnRilevamentoSolo(string $nome, int $lato): Media
    {
        $poi = PackageUgcPoi::factory()->create([
            'user_id' => User::factory()->create()->id,
            'app_id' => App::factory()->create()->id,
            'properties' => ['uuid' => (string) Str::uuid()],
        ]);

        return $this->mediaSenzaHash($poi, $nome, $lato);
    }

    /** @param  PackageUgcPoi|PackageUgcTrack  $modello */
    private function mediaSenzaHash($modello, string $nome, int $lato): Media
    {
        $media = $modello->addMedia(UploadedFile::fake()->image($nome, $lato, $lato))->toMediaCollection('default');
        $media->forgetCustomProperty(UgcMediaHashService::HASH_PROPERTY);
        $media->saveQuietly();

        return $media->fresh();
    }

    private function hashSalvato(Media $media): ?string
    {
        return $media->fresh()->getCustomProperty(UgcMediaHashService::HASH_PROPERTY);
    }

    public function test_calcola_e_salva_lo_sha256_dei_media_dei_duplicati()
    {
        $media = $this->mediaSuUnDuplicato('a.png', 5);
        $atteso = hash_file('sha256', Storage::disk($media->disk)->path($media->getPathRelativeToRoot()));

        $this->artisan('osm2cai:backfill-ugc-media-hash', ['--execute' => true, '--chunk' => 1])
            ->assertSuccessful();

        $this->assertSame($atteso, $this->hashSalvato($media));
    }

    public function test_i_media_di_un_rilevamento_non_duplicato_non_si_toccano()
    {
        $solo = $this->mediaSuUnRilevamentoSolo('solo.png', 5);
        $duplicato = $this->mediaSuUnDuplicato('dup.png', 7);

        $this->artisan('osm2cai:backfill-ugc-media-hash', ['--execute' => true])
            ->expectsOutputToContain('Hash calcolati: 1. Falliti: 0.')
            ->assertSuccessful();

        $this->assertNull($this->hashSalvato($solo), 'Un rilevamento che non ha duplicati non va letto.');
        $this->assertNotNull($this->hashSalvato($duplicato));
    }

    public function test_tratta_anche_le_copie_della_vecchia_bonifica_e_i_loro_padri()
    {
        // Queste copie non hanno un uuid duplicato: sono riconoscibili solo da properties.duplicated,
        // e le guarda osm2cai:recover-previous-duplicate-fix. Se restassero fuori, i loro hash si
        // calcolerebbero durante la sanatoria, cioè ad app ferma.
        $comuni = [
            'user_id' => User::factory()->create()->id,
            'app_id' => App::factory()->create()->id,
        ];
        $padre = PackageUgcPoi::factory()->create($comuni + ['properties' => ['uuid' => (string) Str::uuid()]]);
        $copia = PackageUgcPoi::factory()->create($comuni + ['properties' => [
            'uuid' => (string) Str::uuid(),
            'duplicated' => true,
            'duplication_info' => ['parent_ugc_poi_id' => $padre->id],
        ]]);

        $mediaDelPadre = $this->mediaSenzaHash($padre, 'padre.png', 5);
        $mediaDellaCopia = $this->mediaSenzaHash($copia, 'copia.png', 7);

        $this->artisan('osm2cai:backfill-ugc-media-hash', ['--execute' => true])
            ->expectsOutputToContain('Hash calcolati: 2. Falliti: 0.')
            ->assertSuccessful();

        $this->assertNotNull($this->hashSalvato($mediaDelPadre));
        $this->assertNotNull($this->hashSalvato($mediaDellaCopia));
    }

    public function test_non_ricalcola_quelli_che_ce_l_hanno_gia()
    {
        $media = $this->mediaSuUnDuplicato('a.png', 5);
        $media->setCustomProperty(UgcMediaHashService::HASH_PROPERTY, 'gia-presente');
        $media->saveQuietly();

        $this->artisan('osm2cai:backfill-ugc-media-hash', ['--execute' => true])
            ->expectsOutputToContain('Hash calcolati: 0. Falliti: 0.')
            ->assertSuccessful();

        $this->assertSame('gia-presente', $this->hashSalvato($media));
    }

    public function test_un_media_illeggibile_viene_contato_fra_i_falliti_e_non_blocca_gli_altri()
    {
        $illeggibile = $this->mediaSuUnDuplicato('a.png', 5);
        $leggibile = $this->mediaSuUnDuplicato('b.png', 7);
        Storage::disk($illeggibile->disk)->delete($illeggibile->getPathRelativeToRoot());

        $this->artisan('osm2cai:backfill-ugc-media-hash', ['--execute' => true, '--chunk' => 1])
            ->expectsOutputToContain('Hash calcolati: 1. Falliti: 1.')
            ->assertSuccessful();

        $this->assertNull($this->hashSalvato($illeggibile));
        $this->assertNotNull($this->hashSalvato($leggibile));
    }

    public function test_senza_execute_conta_soltanto()
    {
        $media = $this->mediaSuUnDuplicato('a.png', 5);

        // Il servizio non deve proprio essere chiamato: hashOf() scrive sul database.
        $this->mock(UgcMediaHashService::class, function ($mock) {
            $mock->shouldNotReceive('hashOf');
        });

        $this->artisan('osm2cai:backfill-ugc-media-hash')
            ->expectsOutputToContain('Media senza sha256 sui rilevamenti duplicati: 1')
            ->assertSuccessful();

        $this->assertNull($this->hashSalvato($media));
    }

    public function test_tratta_i_media_delle_tracce_duplicate_e_ignora_gli_altri_tipi()
    {
        $dellaTraccia = $this->mediaSuUnDuplicato('a.png', 5, PackageUgcTrack::class);
        $altroTipo = $this->mediaSuUnDuplicato('b.png', 7);
        // Il file resta leggibile: cambia solo il tipo del modello proprietario.
        Media::whereKey($altroTipo->id)->update(['model_type' => 'App\\Models\\EcPoi']);

        $this->artisan('osm2cai:backfill-ugc-media-hash', ['--execute' => true])
            ->expectsOutputToContain('Hash calcolati: 1. Falliti: 0.')
            ->assertSuccessful();

        $this->assertNotNull($this->hashSalvato($dellaTraccia));
        $this->assertNull($this->hashSalvato($altroTipo));
    }
}
