<?php

namespace Tests\Feature\Ugc;

use App\Services\UgcMediaHashService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Media;
use Wm\WmPackage\Models\UgcPoi as PackageUgcPoi;
use Wm\WmPackage\Models\User;

/**
 * STRATO 2 di oc:8867: il command popola lo sha256 dei media UGC e in sola segnalazione non scrive.
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

    /** Crea un media UGC senza hash; il file è un PNG reale. */
    private function mediaSenzaHash(string $nome, int $lato): Media
    {
        $poi = PackageUgcPoi::factory()->create([
            'user_id' => User::factory()->create()->id,
            'app_id' => App::factory()->create()->id,
        ]);
        $media = $poi->addMedia(UploadedFile::fake()->image($nome, $lato, $lato))->toMediaCollection('default');
        $media->forgetCustomProperty(UgcMediaHashService::HASH_PROPERTY);
        $media->saveQuietly();

        return $media->fresh();
    }

    private function hashSalvato(Media $media): ?string
    {
        return $media->fresh()->getCustomProperty(UgcMediaHashService::HASH_PROPERTY);
    }

    public function test_calcola_e_salva_lo_sha256_dei_media_ugc_che_non_ce_l_hanno()
    {
        $media = $this->mediaSenzaHash('a.png', 5);
        $atteso = hash_file('sha256', Storage::disk($media->disk)->path($media->getPathRelativeToRoot()));

        $this->artisan('osm2cai:backfill-ugc-media-hash', ['--execute' => true, '--chunk' => 1])
            ->assertSuccessful();

        $this->assertSame($atteso, $this->hashSalvato($media));
    }

    public function test_non_ricalcola_quelli_che_ce_l_hanno_gia()
    {
        $media = $this->mediaSenzaHash('a.png', 5);
        $media->setCustomProperty(UgcMediaHashService::HASH_PROPERTY, 'gia-presente');
        $media->saveQuietly();

        $this->artisan('osm2cai:backfill-ugc-media-hash', ['--execute' => true])
            ->expectsOutputToContain('Hash calcolati: 0. Falliti: 0.')
            ->assertSuccessful();

        $this->assertSame('gia-presente', $this->hashSalvato($media));
    }

    public function test_un_media_illeggibile_viene_contato_fra_i_falliti_e_non_blocca_gli_altri()
    {
        $illeggibile = $this->mediaSenzaHash('a.png', 5);
        $leggibile = $this->mediaSenzaHash('b.png', 7);
        Storage::disk($illeggibile->disk)->delete($illeggibile->getPathRelativeToRoot());

        $this->artisan('osm2cai:backfill-ugc-media-hash', ['--execute' => true, '--chunk' => 1])
            ->expectsOutputToContain('Hash calcolati: 1. Falliti: 1.')
            ->assertSuccessful();

        $this->assertNull($this->hashSalvato($illeggibile));
        $this->assertNotNull($this->hashSalvato($leggibile));
    }

    public function test_senza_execute_conta_soltanto()
    {
        $media = $this->mediaSenzaHash('a.png', 5);

        $this->artisan('osm2cai:backfill-ugc-media-hash')
            ->expectsOutputToContain('Media UGC senza sha256:')
            ->assertSuccessful();

        $this->assertNull($this->hashSalvato($media));
    }
}
