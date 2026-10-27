<?php

namespace Tests\Feature\Ugc;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UgcDuplicatesArchiveSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_tabella_archivio_esiste_con_le_sue_colonne(): void
    {
        $this->assertTrue(Schema::hasTable('ugc_duplicates_archive'));
        $this->assertTrue(Schema::hasColumns('ugc_duplicates_archive', [
            'model_type', 'original_id', 'reference_id', 'uuid', 'user_id', 'app_id',
            'properties', 'media_ids', 'geometry', 'original_created_at', 'original_updated_at', 'archived_at',
        ]));
    }

    public function test_la_geometria_accetta_sia_punti_sia_linee(): void
    {
        foreach (['POINT(10 45)', 'LINESTRING(10 45, 11 46)'] as $wkt) {
            DB::statement(
                "INSERT INTO ugc_duplicates_archive (model_type, original_id, reference_id, archived_at, geometry) VALUES ('t', 1, 2, now(), ST_GeogFromText(?))",
                [$wkt]
            );
        }

        $this->assertSame(2, DB::table('ugc_duplicates_archive')->count());
    }
}
