<?php

namespace Tests\Feature\Ugc;

use App\Services\UgcMediaHashService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Media;
use Wm\WmPackage\Models\UgcPoi;
use Wm\WmPackage\Models\User;

/**
 * STRATO 2 di oc:8867: il command osm2cai:recover-previous-duplicate-fix sana lo stato lasciato dalla
 * bonifica precedente (copie marcate duplicated, riassegnate all'utente di servizio, con le foto
 * ancora attaccate e le chiavi ereditate dalle schede tenute).
 */
class RecoverPreviousDuplicateFixTest extends TestCase
{
    use DatabaseTransactions;

    private const EMAIL_SERVIZIO = 'duplicated-ugc-pois@webmapp.it';

    private User $autore;

    private User $servizio;

    private App $ugcApp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('wmfe');
        Storage::fake('conf');

        $this->autore = User::factory()->create();
        $this->servizio = User::factory()->create(['email' => self::EMAIL_SERVIZIO]);
        $this->ugcApp = App::factory()->create();
    }

    /** PNG reale: con altro contenuto la generazione delle miniature di Spatie solleva CouldNotLoadImage. */
    private function foto(UgcPoi $poi, string $nome, int $variante = 0): Media
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        if ($variante > 0) {
            $dati = 'tEXt'."k\0v{$variante}";
            $png = substr($png, 0, -12).pack('N', strlen($dati) - 4).$dati.pack('N', crc32($dati)).substr($png, -12);
        }

        return $poi->addMedia(UploadedFile::fake()->createWithContent($nome, $png))->toMediaCollection('default');
    }

    private function creaPoi(array $properties = [], ?int $userId = null): UgcPoi
    {
        $poi = UgcPoi::factory()->create([
            'user_id' => $userId ?? $this->autore->id,
            'app_id' => $this->ugcApp->id,
            'name' => 'Sorgente',
            'properties' => $properties,
            'geometry' => DB::raw("ST_GeomFromText('POINT Z (10.5 44.5 0)', 4326)"),
        ]);
        // L'observer del package assegna il record al proprietario dell'app: l'utente si imposta dopo.
        DB::table('ugc_pois')->where('id', $poi->id)->update(['user_id' => $userId ?? $this->autore->id]);

        return $poi;
    }

    /** Copia come la lasciava la vecchia bonifica: marcata, dell'utente di servizio, con duplication_info. */
    private function creaCopiaMarcata(int $padreId, ?int $utenteOriginale = null): UgcPoi
    {
        return $this->creaPoi([
            'uuid' => 'uuid-'.$padreId,
            'duplicated' => true,
            'duplication_info' => [
                'parent_ugc_poi_id' => $padreId,
                'parent_ugc_poi_user_id' => $utenteOriginale ?? $this->autore->id,
            ],
        ], $this->servizio->id);
    }

    private function esegui(array $opzioni = []): string
    {
        Artisan::call('osm2cai:recover-previous-duplicate-fix', $opzioni);

        return Artisan::output();
    }

    private function mediaDi(UgcPoi $poi): array
    {
        return Media::where('model_type', $poi->getMorphClass())->where('model_id', $poi->id)->pluck('id')->all();
    }

    public function test_sposta_sul_padre_le_foto_rimaste_sulle_copie_marcate()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $copia = $this->creaCopiaMarcata($padre->id);
        $foto = $this->foto($copia, 'image_0.png', 1);

        $this->esegui(['--execute' => true]);

        $this->assertSame([$foto->id], $this->mediaDi($padre));
        $this->assertSame([], $this->mediaDi($copia));
        // L'archivio ricorda quale foto si è spostata.
        $this->assertSame([$foto->id], json_decode(DB::table('ugc_duplicates_archive')->where('original_id', $copia->id)->value('media_ids'), true));
    }

    public function test_scarta_la_foto_della_copia_che_il_padre_ha_gia()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $copia = $this->creaCopiaMarcata($padre->id);
        $this->foto($padre, 'image_0.png', 1);
        $doppione = $this->foto($copia, 'image_0.png', 1);
        $diversa = $this->foto($copia, 'image_1.png', 2);

        $output = $this->esegui(['--execute' => true]);

        $this->assertNull(Media::find($doppione->id));
        $this->assertContains($diversa->id, $this->mediaDi($padre));
        $this->assertCount(2, $this->mediaDi($padre));
        $this->assertStringContainsString('doppioni cancellati: 1', $output);
    }

    public function test_archivia_e_cancella_le_copie_marcate()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $copia = $this->creaCopiaMarcata($padre->id);

        $this->esegui(['--execute' => true]);

        $this->assertNull(UgcPoi::find($copia->id));
        $this->assertNotNull(UgcPoi::find($padre->id));
        $archiviata = DB::table('ugc_duplicates_archive')->where('original_id', $copia->id)->first();
        $this->assertNotNull($archiviata);
        $this->assertSame($padre->id, (int) $archiviata->reference_id);
        $this->assertSame('uuid-'.$padre->id, $archiviata->uuid);
        $this->assertSame((string) $this->ugcApp->id, (string) $archiviata->app_id);
    }

    public function test_ripristina_l_utente_originale_prima_di_archiviare()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $originale = User::factory()->create();
        $copia = $this->creaCopiaMarcata($padre->id, $originale->id);

        $this->esegui(['--execute' => true]);

        $archiviata = DB::table('ugc_duplicates_archive')->where('original_id', $copia->id)->first();
        $this->assertSame($originale->id, (int) $archiviata->user_id);
        $this->assertNotSame($this->servizio->id, (int) $archiviata->user_id);
    }

    public function test_ripulisce_le_chiavi_ereditate_dalle_schede_che_non_sono_copie()
    {
        $erede = $this->creaPoi([
            'uuid' => 'erede',
            'has_been_duplicated' => true,
            'duplication_info' => ['parent_ugc_poi_id' => 1],
            'altro' => 'resta',
        ]);
        $solo = $this->creaPoi(['uuid' => 'solo', 'has_been_duplicated' => true]);
        $pulita = $this->creaPoi(['uuid' => 'pulita']);
        // Qualunque riga con la chiave duplicated, di qualunque tipo, esce dalla pulizia: duplication_info si conserva.
        $falsa = $this->creaPoi(['uuid' => 'falsa', 'duplicated' => false, 'duplication_info' => ['x' => 1]]);
        $stringa = $this->creaPoi(['uuid' => 'stringa', 'duplicated' => 'true', 'duplication_info' => ['parent_ugc_poi_id' => 1]]);
        $primaStringa = $this->riga($stringa);

        $output = $this->esegui(['--execute' => true]);

        $proprieta = fn (UgcPoi $p) => json_decode(DB::table('ugc_pois')->where('id', $p->id)->value('properties'), true);
        $this->assertSame(['uuid' => 'erede', 'altro' => 'resta'], $proprieta($erede));
        $this->assertSame(['uuid' => 'solo'], $proprieta($solo));
        $this->assertSame(['uuid' => 'pulita'], $proprieta($pulita));
        $this->assertSame(['x' => 1], $proprieta($falsa)['duplication_info']);
        $this->assertSame($primaStringa, $this->riga($stringa), 'un duplicated stringa non è copia riconosciuta e non si tocca');
        $this->assertStringContainsString('2 schede hanno la chiave duplicated', $output);
        $this->assertNotNull(UgcPoi::find($erede->id), 'la scheda non si cancella');
    }

    public function test_una_copia_orfana_con_utente_originale_valido_viene_sanata_e_non_cancellata()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $orfana = $this->creaCopiaMarcata($padre->id + 100000);
        $this->creaPoi(['uuid' => 'altro', 'has_been_duplicated' => true]);
        $fotoOrfana = $this->foto($orfana, 'image_0.png');

        $output = $this->esegui(['--execute' => true]);

        $this->assertNotNull(UgcPoi::find($orfana->id), 'non si cancella');
        $riga = DB::table('ugc_pois')->where('id', $orfana->id)->first();
        $this->assertSame($this->autore->id, (int) $riga->user_id);
        $this->assertSame(['uuid' => 'uuid-'.($padre->id + 100000)], json_decode($riga->properties, true));
        $this->assertSame([$fotoOrfana->id], $this->mediaDi($orfana));
        $this->assertSame(0, DB::table('ugc_duplicates_archive')->count());
        // Resta nell'elenco da guardare, ma non in sospeso.
        $this->assertStringContainsString('il padre non esiste più: sanata', $output);
        $this->assertStringContainsString((string) $orfana->id, $output);
        // Non ha più nulla assegnato all'utente di servizio: si elimina.
        $this->assertFalse(DB::table('users')->where('id', $this->servizio->id)->exists());
    }

    public function test_una_copia_orfana_senza_utente_originale_valido_resta_intatta()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $senzaUtente = $this->creaPoi([
            'uuid' => 'x',
            'duplicated' => true,
            'duplication_info' => ['parent_ugc_poi_id' => $padre->id + 100000],
        ], $this->servizio->id);
        $utenteInesistente = $this->creaPoi([
            'uuid' => 'y',
            'duplicated' => true,
            'duplication_info' => ['parent_ugc_poi_id' => $padre->id + 100001, 'parent_ugc_poi_user_id' => 99999999],
        ], $this->servizio->id);
        $prima = [$senzaUtente->id => $this->riga($senzaUtente), $utenteInesistente->id => $this->riga($utenteInesistente)];

        $output = $this->esegui(['--execute' => true]);

        foreach ($prima as $id => $riga) {
            $this->assertSame($riga, $this->riga(UgcPoi::find($id)), "la copia {$id} non va toccata");
        }
        $this->assertStringContainsString('il padre non esiste più e utente originale non indicato', $output);
        $this->assertStringContainsString('il padre non esiste più e l\'utente originale non esiste più', $output);
        // Hanno ancora l'utente di servizio: non si elimina.
        $this->assertTrue(DB::table('users')->where('id', $this->servizio->id)->exists());
        $this->assertStringContainsString('NON eliminato', $output);
    }

    public function test_un_padre_che_e_a_sua_volta_una_copia_marcata_va_a_revisione_e_non_si_tocca()
    {
        // Resta marcata: ha un utente originale inesistente, quindi non si sana.
        $altraCopia = $this->creaCopiaMarcata(1, 99999999);
        $figlia = $this->creaCopiaMarcata($altraCopia->id);
        $prima = $this->riga($figlia);

        $output = $this->esegui(['--execute' => true]);

        $this->assertSame($prima, $this->riga(UgcPoi::find($figlia->id)));
        $this->assertStringContainsString('il padre è a sua volta una copia marcata', $output);
    }

    private function riga(UgcPoi $poi): array
    {
        return (array) DB::table('ugc_pois')->where('id', $poi->id)->first();
    }

    public function test_senza_execute_non_scrive_nulla()
    {
        $padre = $this->creaPoi(['uuid' => 'p', 'has_been_duplicated' => true]);
        $copia = $this->creaCopiaMarcata($padre->id);
        $foto = $this->foto($copia, 'image_0.png');
        $orfana = $this->creaCopiaMarcata($padre->id + 100000);
        $prima = $this->riga($orfana);

        $output = $this->esegui();

        // Il ramo delle orfane è una scrittura: senza --execute resta intatta, chiavi di duplicazione comprese.
        $this->assertSame($prima, $this->riga($orfana));
        $proprietaOrfana = json_decode(DB::table('ugc_pois')->where('id', $orfana->id)->value('properties'), true);
        $this->assertTrue($proprietaOrfana['duplicated']);
        $this->assertArrayHasKey('duplication_info', $proprietaOrfana);
        $this->assertStringContainsString('verrebbe sanata', $output);

        $this->assertNotNull(UgcPoi::find($copia->id));
        $this->assertSame($this->servizio->id, (int) DB::table('ugc_pois')->where('id', $copia->id)->value('user_id'));
        $this->assertSame([$foto->id], $this->mediaDi($copia));
        $this->assertSame([], $this->mediaDi($padre));
        $this->assertNull($foto->fresh()->getCustomProperty(UgcMediaHashService::HASH_PROPERTY), 'nessun hash scritto sul media');
        $this->assertSame(0, DB::table('ugc_duplicates_archive')->count());
        $this->assertArrayHasKey('has_been_duplicated', json_decode(DB::table('ugc_pois')->where('id', $padre->id)->value('properties'), true));
        $this->assertTrue(DB::table('users')->where('id', $this->servizio->id)->exists());
        $this->assertStringContainsString('nessuna scrittura', $output);
        $this->assertStringContainsString('verrebbe eliminato', $output);
    }

    public function test_a_fine_lavoro_l_utente_di_servizio_viene_eliminato()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $this->creaCopiaMarcata($padre->id);
        // Ruolo e permesso diretto dell'utente di servizio: sono morph senza foreign key, vanno via con lui.
        $this->servizio->assignRole(Role::findOrCreate('Administrator', 'web'));
        $this->servizio->givePermissionTo(Permission::findOrCreate('validate source surveys', 'web'));
        $altroUtente = User::factory()->create();
        $altroUtente->assignRole('Administrator');
        $this->assertSame(1, DB::table('model_has_roles')->where('model_id', $this->servizio->id)->count());

        $output = $this->esegui(['--execute' => true]);

        $this->assertFalse(DB::table('users')->where('email', self::EMAIL_SERVIZIO)->exists());
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $this->servizio->id)->count());
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_id', $this->servizio->id)->count());
        $this->assertSame(1, DB::table('model_has_roles')->where('model_id', $altroUtente->id)->count(), 'i ruoli degli altri utenti non si toccano');
        $this->assertStringContainsString('eliminato', $output);
        $this->assertSame(1, DB::table('ugc_duplicates_archive')->count());
    }

    public function test_l_utente_di_servizio_con_altri_record_non_viene_eliminato()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $this->creaCopiaMarcata($padre->id);
        // Un POI qualunque ancora assegnato all'utente di servizio: non è una copia, non va toccato.
        $altro = $this->creaPoi(['uuid' => 'altro'], $this->servizio->id);

        $output = $this->esegui(['--execute' => true]);

        $this->assertTrue(DB::table('users')->where('id', $this->servizio->id)->exists());
        $this->assertNotNull(UgcPoi::find($altro->id));
        $this->assertStringContainsString('ugc_pois.user_id: 1', $output);
    }

    public function test_i_riferimenti_senza_foreign_key_impediscono_l_eliminazione_dell_utente()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $this->creaCopiaMarcata($padre->id);
        DB::table('favorites')->insert(['user_id' => $this->servizio->id, 'favoriteable_type' => 'App\\Models\\UgcPoi', 'favoriteable_id' => $padre->id]);
        DB::table('nova_notifications')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $this->servizio->id, 'data' => '{}',
        ]);

        $dry = $this->esegui();
        $output = $this->esegui(['--execute' => true]);

        $this->assertStringContainsString('non verrebbe eliminato', $dry);
        $this->assertTrue(DB::table('users')->where('id', $this->servizio->id)->exists());
        $this->assertStringContainsString('NON eliminato', $output);
        $this->assertStringContainsString('favorites.user_id: 1', $output);
        $this->assertStringContainsString('nova_notifications.notifiable_id: 1', $output);
    }

    public function test_e_idempotente()
    {
        $padre = $this->creaPoi(['uuid' => 'p']);
        $this->creaCopiaMarcata($padre->id);

        $this->esegui(['--execute' => true]);
        $output = $this->esegui(['--execute' => true]);

        $this->assertSame(1, DB::table('ugc_duplicates_archive')->count());
        $this->assertStringContainsString('0 copie marcate', $output);
    }
}
