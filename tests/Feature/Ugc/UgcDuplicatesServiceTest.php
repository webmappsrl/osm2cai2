<?php

namespace Tests\Feature\Ugc;

use App\Models\UgcPoi;
use App\Models\UgcTrack;
use App\Services\UgcDuplicatesService;
use App\Services\UgcMediaHashService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Media;
use Wm\WmPackage\Models\UgcPoi as PackageUgcPoi;
use Wm\WmPackage\Models\UgcTrack as PackageUgcTrack;
use Wm\WmPackage\Models\User;

/**
 * STRATO 1 di oc:8867: UgcDuplicatesService unisce i rilevamenti con lo stesso properties.uuid nel
 * più vecchio, sposta le foto, archivia le copie e le cancella.
 *
 * I record si creano con le factory del package (quelle locali hanno definition() vuoto); il servizio
 * si usa con i modelli locali App\Models\UgcPoi / UgcTrack, il cui morph coincide con quello dei media.
 */
class UgcDuplicatesServiceTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private App $ugcApp;

    private UgcDuplicatesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Dischi isolati: wmfe per i media, conf perché creare un'App scrive il suo config.json.
        Storage::fake('wmfe');
        Storage::fake('conf');

        $this->user = User::factory()->create();
        $this->ugcApp = App::factory()->create();
        $this->service = UgcDuplicatesService::make();
    }

    private function creaPoi(string $uuid, string $createdAt, array $properties = []): PackageUgcPoi
    {
        return PackageUgcPoi::factory()->create([
            'user_id' => $this->user->id,
            'app_id' => $this->ugcApp->id,
            'name' => 'Sorgente',
            'properties' => array_merge(['uuid' => $uuid], $properties),
            'geometry' => DB::raw("ST_GeomFromText('POINT Z (10.5 44.5 0)', 4326)"),
            'created_at' => $createdAt,
        ]);
    }

    private function creaTraccia(string $uuid, string $createdAt): PackageUgcTrack
    {
        return PackageUgcTrack::factory()->create([
            'user_id' => $this->user->id,
            'app_id' => $this->ugcApp->id,
            'name' => 'Traccia',
            'properties' => ['uuid' => $uuid],
            'geometry' => DB::raw("ST_GeomFromText('LINESTRING Z (10.5 44.5 0, 10.6 44.6 0)', 4326)"),
            'created_at' => $createdAt,
        ]);
    }

    /** PNG reale: con altro contenuto la generazione delle miniature di Spatie solleva CouldNotLoadImage. */
    private function png(string $nome, int $variante = 0): UploadedFile
    {
        // Il PNG 1x1 di base con un chunk di testo che ne cambia i byte (e quindi lo sha256).
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        if ($variante > 0) {
            $png = substr($png, 0, -12).$this->chunkTesto("v{$variante}").substr($png, -12);
        }

        return UploadedFile::fake()->createWithContent($nome, $png);
    }

    private function chunkTesto(string $testo): string
    {
        $dati = 'tEXt'."k\0".$testo;

        return pack('N', strlen($dati) - 4).$dati.pack('N', crc32($dati));
    }

    private function aggiungiFoto($model, string $nome, int $variante = 0): Media
    {
        return $model->addMedia($this->png($nome, $variante))->toMediaCollection('default');
    }

    private function gruppo(string $uuid): array
    {
        return $this->service->groups(UgcPoi::class, $this->ugcApp->id)
            ->firstWhere('uuid', $uuid);
    }

    public function test_raggruppa_per_uuid_e_tiene_il_piu_vecchio_come_padre()
    {
        // Creati fuori ordine, perché l'id non coincida con l'età.
        $medio = $this->creaPoi('uuid-gruppo', '2026-01-02 10:00:00');
        $recente = $this->creaPoi('uuid-gruppo', '2026-01-03 10:00:00');
        $vecchio = $this->creaPoi('uuid-gruppo', '2026-01-01 10:00:00');
        $this->creaPoi('uuid-singolo', '2026-01-01 10:00:00');

        $groups = $this->service->groups(UgcPoi::class, $this->ugcApp->id);
        $gruppo = $groups->firstWhere('uuid', 'uuid-gruppo');

        $this->assertCount(1, $groups);
        $this->assertSame($vecchio->id, $gruppo['parent_id']);
        $this->assertSame([$medio->id, $recente->id], $gruppo['copy_ids']);
        $this->assertEqualsWithDelta(0.0, $gruppo['max_distance_m'], 0.001);
    }

    public function test_la_fusione_sposta_i_media_sul_padre_e_cancella_le_copie()
    {
        $padre = $this->creaPoi('uuid-fusione', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-fusione', '2026-01-02 10:00:00');
        $foto = $this->aggiungiFoto($copia, 'image_0.png');

        $scartati = $this->service->mergeGroup(UgcPoi::class, $this->gruppo('uuid-fusione'));

        $this->assertSame([], $scartati);
        $this->assertSame(1, UgcPoi::where('properties->uuid', 'uuid-fusione')->count());
        $this->assertCount(1, $padre->fresh()->getMedia('default'));
        $this->assertSame($padre->id, (int) $foto->fresh()->model_id);
        $this->assertNull(UgcPoi::find($copia->id));
    }

    public function test_una_foto_identica_per_hash_viene_scartata_e_restituita_al_chiamante()
    {
        $padre = $this->creaPoi('uuid-hash', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-hash', '2026-01-02 10:00:00');
        $this->aggiungiFoto($padre, 'image_0.png');
        $doppione = $this->aggiungiFoto($copia, 'image_0.png');

        $scartati = $this->service->mergeGroup(UgcPoi::class, $this->gruppo('uuid-hash'));

        $this->assertCount(1, $scartati);
        $this->assertSame($doppione->id, $scartati[0]->id);
        $this->assertCount(1, $padre->fresh()->getMedia('default'));
        // Il servizio non cancella: lo fa il chiamante dopo il commit.
        $this->assertNotNull(Media::find($doppione->id));
    }

    public function test_una_foto_diversa_per_hash_viene_spostata_accanto_a_quella_del_padre()
    {
        $padre = $this->creaPoi('uuid-diversa', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-diversa', '2026-01-02 10:00:00');
        $this->aggiungiFoto($padre, 'image_0.png', 0);
        $this->aggiungiFoto($copia, 'image_0.png', 1);

        $scartati = $this->service->mergeGroup(UgcPoi::class, $this->gruppo('uuid-diversa'));

        $this->assertSame([], $scartati);
        $this->assertCount(2, $padre->fresh()->getMedia('default'));
    }

    public function test_un_media_illeggibile_viene_spostato_e_non_scartato()
    {
        $padre = $this->creaPoi('uuid-illeggibile', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-illeggibile', '2026-01-02 10:00:00');
        $foto = $this->aggiungiFoto($copia, 'image_0.png');

        // Senza hash salvato e con il file sparito dal disco: l'hash non è calcolabile.
        $foto->forgetCustomProperty(UgcMediaHashService::HASH_PROPERTY);
        $foto->saveQuietly();
        Storage::disk($foto->disk)->delete($foto->getPathRelativeToRoot());
        $this->assertNull(UgcMediaHashService::make()->hashOf($foto->fresh()));

        $scartati = $this->service->mergeGroup(UgcPoi::class, $this->gruppo('uuid-illeggibile'));

        $this->assertSame([], $scartati);
        $this->assertSame($padre->id, (int) $foto->fresh()->model_id);
        $this->assertCount(1, $padre->fresh()->getMedia('default'));
    }

    public function test_la_copia_finisce_in_archivio_con_utente_app_geometria_e_media_spostati()
    {
        $padre = $this->creaPoi('uuid-archivio', '2026-01-01 10:00:00');
        $copia = $this->creaPoi('uuid-archivio', '2026-01-02 10:00:00', ['note' => 'della copia']);
        $foto = $this->aggiungiFoto($copia, 'image_0.png');
        // L'observer del package assegna lo user_id dal proprietario dell'app: si legge dal record.
        $userIdCopia = (int) $copia->fresh()->user_id;

        $this->service->mergeGroup(UgcPoi::class, $this->gruppo('uuid-archivio'));

        $righe = DB::table('ugc_duplicates_archive')->where('uuid', 'uuid-archivio')->get();
        $this->assertCount(1, $righe);
        $riga = $righe->first();
        $this->assertSame('App\Models\UgcPoi', $riga->model_type);
        $this->assertSame($copia->id, (int) $riga->original_id);
        $this->assertSame($padre->id, (int) $riga->reference_id);
        $this->assertSame($userIdCopia, (int) $riga->user_id);
        $this->assertSame($this->ugcApp->id, (int) $riga->app_id);
        $this->assertNotNull($riga->geometry);
        $this->assertSame('della copia', json_decode($riga->properties, true)['note']);
        $this->assertSame([$foto->id], json_decode($riga->media_ids, true));
    }

    public function test_le_properties_si_uniscono_in_ordine_cronologico_tenendo_le_chiavi_del_server()
    {
        $merged = $this->service->mergedProperties(
            [
                ['uuid' => 'u', 'id' => 99, 'created_at' => 'copia', 'note' => 'prima', 'solo_prima' => 1],
                ['uuid' => 'u', 'id' => 1, 'created_at' => 'padre', 'note' => 'dopo', 'taxonomy_where' => 'copia'],
            ],
            ['uuid' => 'u', 'id' => 1, 'created_at' => 'padre', 'updated_at' => 'padre']
        );

        // L'ultima riga vince sulle chiavi normali, le chiavi del server restano del padre.
        $this->assertSame('dopo', $merged['note']);
        $this->assertSame(1, $merged['solo_prima']);
        $this->assertSame(1, $merged['id']);
        $this->assertSame('padre', $merged['created_at']);
        $this->assertSame('padre', $merged['updated_at']);
        // Chiave del server assente nel padre: non viene ereditata dalla copia.
        $this->assertArrayNotHasKey('taxonomy_where', $merged);
    }

    public function test_la_fusione_scrive_le_properties_unite_sul_padre()
    {
        $padre = $this->creaPoi('uuid-props', '2026-01-01 10:00:00', ['updatedAt' => '2026-01-01T10:00:00Z', 'note' => 'vecchia']);
        $this->creaPoi('uuid-props', '2026-01-02 10:00:00', ['updatedAt' => '2026-01-02T10:00:00Z', 'note' => 'nuova']);

        $this->service->mergeGroup(UgcPoi::class, $this->gruppo('uuid-props'));

        $this->assertSame('nuova', $padre->fresh()->properties['note']);
    }

    public function test_la_distanza_massima_e_in_metri_e_non_in_gradi()
    {
        $padre = $this->creaPoi('uuid-distanza', '2026-01-01 10:00:00');
        // Circa 0,001 gradi di latitudine: poco più di 111 metri.
        $copia = $this->creaPoi('uuid-distanza', '2026-01-02 10:00:00');
        DB::update("UPDATE ugc_pois SET geometry = ST_GeomFromText('POINT Z (10.5 44.501 0)', 4326) WHERE id = ?", [$copia->id]);

        $d = $this->service->maxDistanceMeters('ugc_pois', $padre->id, [$copia->id]);

        $this->assertEqualsWithDelta(111.2, $d, 2.0);
        $this->assertSame(0.0, $this->service->maxDistanceMeters('ugc_pois', $padre->id, []));
    }

    public function test_funziona_anche_per_le_tracce()
    {
        $padre = $this->creaTraccia('uuid-traccia', '2026-01-01 10:00:00');
        $copia = $this->creaTraccia('uuid-traccia', '2026-01-02 10:00:00');
        $foto = $this->aggiungiFoto($copia, 'image_0.png');

        $gruppo = $this->service->groups(UgcTrack::class, $this->ugcApp->id)->firstWhere('uuid', 'uuid-traccia');
        $this->assertSame($padre->id, $gruppo['parent_id']);
        $this->assertSame([$copia->id], $gruppo['copy_ids']);

        $scartati = $this->service->mergeGroup(UgcTrack::class, $gruppo);

        $this->assertSame([], $scartati);
        $this->assertSame(1, UgcTrack::where('properties->uuid', 'uuid-traccia')->count());
        $this->assertCount(1, $padre->fresh()->getMedia('default'));
        $riga = DB::table('ugc_duplicates_archive')->where('uuid', 'uuid-traccia')->first();
        $this->assertSame('App\Models\UgcTrack', $riga->model_type);
        $this->assertSame($padre->id, (int) $riga->reference_id);
        $this->assertSame([$foto->id], json_decode($riga->media_ids, true));
    }
}
