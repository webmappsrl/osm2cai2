<?php

namespace Tests\Feature\Ugc;

use App\Services\UgcMediaHashService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\UgcPoi;
use Wm\WmPackage\Models\UgcTrack;
use Wm\WmPackage\Models\User;

/**
 * STRATO 1 — NON CANCELLARE (oc:8867): resta e va modificato solo il `use` di UgcMediaHashService
 * (da App\Services a Wm\WmPackage\Services\Models). Dopo l'aggiornamento di wm-package diventa la prova
 * che il package fa ciò che qui si è riscritto. Lo store dei rilevamenti UGC deve riconoscere il retry dell'app
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

    private function contaPoi(string $uuid): int
    {
        return UgcPoi::where('properties->uuid', $uuid)->count();
    }

    /**
     * Immagine con byte sempre uguali: simula l'app che a ogni retry rimanda le stesse foto con
     * nome posizionale. Ogni chiamata restituisce un nuovo UploadedFile.
     */
    private function immagine(string $nome = 'image_0.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($nome, $png);
    }

    public function test_due_invii_con_lo_stesso_uuid_aggiornano_lo_stesso_record()
    {
        $feature = $this->poiFeature('uuid-retry-poi');

        $first = $this->storeMultipart('/api/v2/ugc/poi/store', $feature);
        $second = $this->storeMultipart('/api/v2/ugc/poi/store', $feature);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, $this->contaPoi('uuid-retry-poi'));
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

    /**
     * Test-guardia: passa anche senza la correzione (senza fix i record sono comunque 2). Sorveglia
     * il filtro per utente di findExistingByUuid(): se lo si toglie, il secondo utente aggiornerebbe
     * il record del primo e questo test fallisce.
     */
    public function test_lo_stesso_uuid_di_un_altro_utente_crea_un_record_nuovo()
    {
        $feature = $this->poiFeature('uuid-condiviso');
        $altroUtente = User::factory()->create();

        $this->storeMultipart('/api/v2/ugc/poi/store', $feature)->assertStatus(201);
        $this->storeMultipart('/api/v2/ugc/poi/store', $feature, [], $altroUtente)->assertStatus(201);

        $this->assertSame(2, $this->contaPoi('uuid-condiviso'));
    }

    /**
     * Test-guardia: passa anche senza la correzione, perché il formato JSON al primo livello era già
     * riconosciuto dal package. Sorveglia la non-regressione di quel formato, non prova il fix.
     */
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
        $this->assertSame(1, $this->contaPoi('uuid-primo-livello'));
    }

    public function test_due_invii_di_una_traccia_con_lo_stesso_uuid_aggiornano_lo_stesso_record()
    {
        $feature = $this->trackFeature('uuid-retry-track');

        $first = $this->storeMultipart('/api/v2/ugc/track/store', $feature);
        $second = $this->storeMultipart('/api/v2/ugc/track/store', $feature);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, UgcTrack::where('properties->uuid', 'uuid-retry-track')->count());
    }

    public function test_il_reinvio_con_la_stessa_immagine_non_la_salva_due_volte()
    {
        $feature = $this->poiFeature('uuid-stessa-immagine');

        $first = $this->storeMultipart('/api/v2/ugc/poi/store', $feature, [$this->immagine()]);
        $second = $this->storeMultipart('/api/v2/ugc/poi/store', $feature, [$this->immagine()]);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('id'), $second->json('id'));
        $poi = UgcPoi::findOrFail($first->json('id'));
        $this->assertCount(1, $poi->getMedia('default'));
        $this->assertNotEmpty($poi->getFirstMedia('default')->getCustomProperty(UgcMediaHashService::HASH_PROPERTY));
    }

    public function test_il_reinvio_con_un_immagine_diversa_la_aggiunge()
    {
        $feature = $this->poiFeature('uuid-immagine-nuova');

        $first = $this->storeMultipart('/api/v2/ugc/poi/store', $feature, [$this->immagine()]);
        $second = $this->storeMultipart('/api/v2/ugc/poi/store', $feature, [
            $this->immagine(),
            UploadedFile::fake()->image('image_1.png', 3, 3),
        ]);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $poi = UgcPoi::findOrFail($first->json('id'));
        $this->assertCount(2, $poi->getMedia('default'));
    }

    public function test_il_reinvio_non_cancella_le_properties_scritte_dal_server()
    {
        $feature = $this->poiFeature('uuid-merge-properties', ['note' => 'prima']);

        $first = $this->storeMultipart('/api/v2/ugc/poi/store', $feature);
        $first->assertStatus(201);

        // Chiave scritta dal server che l'app non conosce (es. layer_id calcolato).
        $poi = UgcPoi::findOrFail($first->json('id'));
        $poi->properties = array_merge($poi->properties, ['layer_id' => 42]);
        $poi->saveQuietly();

        $feature['properties']['note'] = 'dopo';
        $second = $this->storeMultipart('/api/v2/ugc/poi/store', $feature);
        $second->assertStatus(201);

        $this->assertSame($first->json('id'), $second->json('id'));
        $poi = UgcPoi::findOrFail($first->json('id'));
        $this->assertSame(42, $poi->properties['layer_id']);
        // Le properties ricevute vanno sopra quelle salvate.
        $this->assertSame('dopo', $poi->properties['note']);
    }
}
