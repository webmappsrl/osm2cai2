<?php

namespace Tests\Feature\Ugc;

use App\Services\UgcMediaHashService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\UgcPoi;
use Wm\WmPackage\Models\User;

/**
 * STRATO 1 — DA CANCELLARE (oc:8867): testa la copia locale di UgcMediaHashService (il package ha i suoi
 * test). UgcMediaHashService non deve mai far perdere una foto. Se l'hash non si
 * calcola il media conta come "diverso" (null), non solleva eccezioni.
 */
class UgcMediaHashServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Dischi isolati: wmfe per i media, conf perché creare un'App scrive il suo config.json.
        Storage::fake('wmfe');
        Storage::fake('conf');
    }

    private function poiConMedia(UploadedFile $file): UgcPoi
    {
        $user = User::factory()->create();
        $app = App::factory()->create();

        $poi = UgcPoi::factory()->create(['user_id' => $user->id, 'app_id' => $app->id]);
        $poi->addMedia($file)
            ->toMediaCollection('default');

        return $poi->fresh();
    }

    public function test_hash_of_restituisce_null_se_il_file_non_e_leggibile_senza_sollevare_eccezioni()
    {
        $file = UploadedFile::fake()->image('image_0.png', 5, 5);
        $sha = hash_file('sha256', $file->getRealPath());
        $poi = $this->poiConMedia($file);
        $media = $poi->getFirstMedia('default');

        // Media senza hash salvato e file sparito dal disco (assente su S3 o errore temporaneo).
        $media->forgetCustomProperty(UgcMediaHashService::HASH_PROPERTY);
        $media->saveQuietly();
        Storage::disk($media->disk)->delete($media->getPathRelativeToRoot());

        $hashes = UgcMediaHashService::make();

        $this->assertNull($hashes->hashOf($media->fresh()));
        // Un media "illeggibile" non coincide con nessun contenuto: non viene scartato per errore.
        $this->assertNull($hashes->findByContent($poi, $sha));
    }

    public function test_hash_of_calcola_e_salva_lo_sha256_quando_il_file_e_leggibile()
    {
        $file = UploadedFile::fake()->image('image_0.png', 5, 5);
        $sha = hash_file('sha256', $file->getRealPath());
        $poi = $this->poiConMedia($file);
        $media = $poi->getFirstMedia('default');
        // Si parte da un media senza hash salvato.
        $media->forgetCustomProperty(UgcMediaHashService::HASH_PROPERTY);
        $media->saveQuietly();

        $hash = UgcMediaHashService::make()->hashOf($media);

        $this->assertSame($sha, $hash);
        $this->assertSame($hash, $media->fresh()->getCustomProperty(UgcMediaHashService::HASH_PROPERTY));
    }
}
