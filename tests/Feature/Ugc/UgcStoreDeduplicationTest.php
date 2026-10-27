<?php

namespace Tests\Feature\Ugc;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\UgcPoi;
use Wm\WmPackage\Models\UgcTrack;
use Wm\WmPackage\Models\User;

/**
 * STRATO 1 di oc:8867: lo store dei rilevamenti UGC deve riconoscere il retry dell'app
 * (stesso properties.uuid) e aggiornare il record invece di crearne uno nuovo.
 *
 * Si asserisce sui modelli del package (Wm\WmPackage\Models\UgcPoi / UgcTrack), che sono quelli
 * che lo store istanzia davvero.
 */
class UgcStoreDeduplicationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private App $ugcApp;

    protected function setUp(): void
    {
        parent::setUp();

        // Il disco dei media (wmfe) non è raggiungibile nell'ambiente di test; il disco conf
        // va isolato perché la creazione di un'App scrive il suo config.json sotto storage/json/conf.
        Storage::fake('wmfe');
        Storage::fake('conf');

        $this->user = User::factory()->create();
        $this->ugcApp = App::factory()->create();
    }

    private function poiFeature(string $uuid, array $propertiesExtra = []): array
    {
        return [
            'type' => 'Feature',
            'properties' => array_merge([
                'name' => 'Sorgente di prova',
                'uuid' => $uuid,
                'app_id' => $this->ugcApp->id,
            ], $propertiesExtra),
            'geometry' => ['type' => 'Point', 'coordinates' => [10.5, 44.5]],
        ];
    }

    private function trackFeature(string $uuid): array
    {
        return [
            'type' => 'Feature',
            'properties' => [
                'name' => 'Traccia di prova',
                'uuid' => $uuid,
                'app_id' => $this->ugcApp->id,
            ],
            'geometry' => ['type' => 'LineString', 'coordinates' => [[10.5, 44.5], [10.6, 44.6]]],
        ];
    }

    private function storeMultipart(string $url, array $feature, array $files = [], ?User $user = null)
    {
        $payload = ['feature' => json_encode($feature)];
        if ($files) {
            $payload['images'] = $files;
        }

        return $this->actingAs($user ?? $this->user, 'api')->post($url, $payload, ['Accept' => 'application/json']);
    }

    public function test_due_invii_con_lo_stesso_uuid_aggiornano_lo_stesso_record()
    {
        $feature = $this->poiFeature('uuid-retry-poi');

        $first = $this->storeMultipart('/api/v2/ugc/poi/store', $feature);
        $second = $this->storeMultipart('/api/v2/ugc/poi/store', $feature);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, UgcPoi::count());
    }

    public function test_il_reinvio_senza_foto_non_cancella_quelle_gia_salvate()
    {
        $feature = $this->poiFeature('uuid-retry-foto');

        $first = $this->storeMultipart('/api/v2/ugc/poi/store', $feature, [UploadedFile::fake()->image('image_0.jpg')]);
        $first->assertStatus(201);

        $second = $this->storeMultipart('/api/v2/ugc/poi/store', $feature);
        $second->assertStatus(201);

        $poi = UgcPoi::findOrFail($first->json('id'));
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertCount(1, $poi->getMedia('default'));
    }

    public function test_lo_stesso_uuid_di_un_altro_utente_crea_un_record_nuovo()
    {
        $feature = $this->poiFeature('uuid-condiviso');
        $altroUtente = User::factory()->create();

        $this->storeMultipart('/api/v2/ugc/poi/store', $feature)->assertStatus(201);
        $this->storeMultipart('/api/v2/ugc/poi/store', $feature, [], $altroUtente)->assertStatus(201);

        $this->assertSame(2, UgcPoi::count());
    }

    public function test_riconosce_il_rilevamento_anche_quando_i_campi_sono_al_primo_livello()
    {
        $feature = $this->poiFeature('uuid-primo-livello');
        $payload = [
            'type' => $feature['type'],
            'properties' => $feature['properties'],
            'geometry' => $feature['geometry'],
        ];

        $first = $this->actingAs($this->user, 'api')->postJson('/api/v2/ugc/poi/store', $payload);
        $second = $this->actingAs($this->user, 'api')->postJson('/api/v2/ugc/poi/store', $payload);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, UgcPoi::count());
    }

    public function test_due_invii_di_una_traccia_con_lo_stesso_uuid_aggiornano_lo_stesso_record()
    {
        $feature = $this->trackFeature('uuid-retry-track');

        $first = $this->storeMultipart('/api/v2/ugc/track/store', $feature);
        $second = $this->storeMultipart('/api/v2/ugc/track/store', $feature);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, UgcTrack::count());
    }
}
