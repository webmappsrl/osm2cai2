<?php

namespace Tests\Unit\Commands;

use App\Models\UgcPoi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FixDuplicatedUgcPoisCommandTest extends TestCase
{
    use DatabaseTransactions;

    private $connection = 'pgsql';

    private const IDS_TO_KEEP = [2, 3, 4, 5, 6, 414, 3867, 7975];

    private const IDS_TO_DELETE = [407, 408, 410, 413, 3817, 7974];

    private const ALL_IDS = [2, 3, 4, 5, 6, 407, 408, 410, 413, 414, 3817, 3867, 7974, 7975];

    private const EXPECTED_TOTAL_DUPLICATED_COUNT = 6;

    private const UGC_POIS_JSON_PATH = __DIR__.'/data/ugc_pois.json';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUgcPoisForTest();
    }

    /**
     * Popola ugc_pois con i record dal file ugc_pois.json.
     * I duplicati (stesso properties.uuid) sono 6; il comando elimina IDS_TO_DELETE e mantiene IDS_TO_KEEP.
     */
    private function seedUgcPoisForTest(): void
    {
        if (! is_file(self::UGC_POIS_JSON_PATH)) {
            $this->markTestSkipped('File fixture mancante: '.self::UGC_POIS_JSON_PATH);
        }

        $json = file_get_contents(self::UGC_POIS_JSON_PATH);
        $records = json_decode($json, true);
        if (! is_array($records) || count($records) === 0) {
            $this->markTestSkipped('File ugc_pois.json vuoto o non valido.');
        }

        // Riferimenti da altre tabelle prima di svuotare ugc_pois
        if (Schema::hasTable('ugc_media')) {
            DB::table('ugc_media')->whereIn('ugc_poi_id', self::ALL_IDS)->update(['ugc_poi_id' => null]);
        }
        if (Schema::hasTable('trail_survey_ugc_poi')) {
            DB::table('trail_survey_ugc_poi')->whereIn('ugc_poi_id', self::ALL_IDS)->delete();
        }

        UgcPoi::query()->delete();

        foreach ($records as $row) {
            $row = (array) $row;
            $geometry = $row['geometry'] ?? null;
            unset($row['geometry']);
            if ($geometry !== null && $geometry !== '') {
                $row['geometry'] = DB::raw("ST_GeomFromEWKT('".addslashes($geometry)."')::geography");
            } else {
                $row['geometry'] = null;
            }
            DB::table('ugc_pois')->insert($row);
        }

        if (Schema::hasTable('ugc_pois')) {
            $maxId = DB::table('ugc_pois')->max('id');
            if ($maxId !== null) {
                DB::statement("SELECT setval(pg_get_serial_sequence('ugc_pois', 'id'), ?)", [$maxId]);
            }
        }
    }

    /** @test */
    public function it_reports_total_duplicated_count_of_6_and_deletes_only_expected_ids(): void
    {
        $this->assertCount(14, UgcPoi::whereIn('id', self::ALL_IDS)->get(), 'Setup: devono esistere 14 UgcPoi prima del comando.');

        $exitCode = Artisan::call('osm2cai:fix-duplicated-ugc-pois', ['--execute' => 1]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);

        // totalDuplicatedCount deve essere 6
        $this->assertStringContainsString(
            'Duplicated total UGC POIs count: '.self::EXPECTED_TOTAL_DUPLICATED_COUNT,
            $output,
            'L\'output del comando deve contenere "Duplicated total UGC POIs count: 6".'
        );

        // Record che devono essere cancellati: non devono più esistere
        foreach (self::IDS_TO_DELETE as $id) {
            $this->assertDatabaseMissing('ugc_pois', ['id' => $id], $this->connection);
            //"L'id {$id} doveva essere cancellato."
        }

        // Record che devono restare: devono esistere
        foreach (self::IDS_TO_KEEP as $id) {
            $this->assertDatabaseHas('ugc_pois', ['id' => $id], $this->connection);
            //"L'id {$id} non doveva essere cancellato."
        }

        $remaining = UgcPoi::whereIn('id', self::ALL_IDS)->count();
        $this->assertSame(8, $remaining, 'Devono restare esattamente 8 record (quelli in IDS_TO_KEEP).');
    }
}
