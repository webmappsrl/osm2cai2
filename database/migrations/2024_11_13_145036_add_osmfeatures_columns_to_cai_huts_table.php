<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cai_huts', function (Blueprint $table) {
            if (! Schema::hasColumn('cai_huts', 'osmfeatures_id')) {
                DB::statement('ALTER TABLE cai_huts ADD COLUMN osmfeatures_id varchar(255)');
            }

            if (! Schema::hasColumn('cai_huts', 'osmfeatures_data')) {
                DB::statement('ALTER TABLE cai_huts ADD COLUMN osmfeatures_data jsonb');
            }

            if (! Schema::hasColumn('cai_huts', 'osmfeatures_updated_at')) {
                DB::statement('ALTER TABLE cai_huts ADD COLUMN osmfeatures_updated_at timestamp');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cai_huts', function (Blueprint $table) {
            if (Schema::hasColumn('cai_huts', 'osmfeatures_id')) {
                $table->dropColumn('osmfeatures_id');
            }
            if (Schema::hasColumn('cai_huts', 'osmfeatures_data')) {
                $table->dropColumn('osmfeatures_data');
            }
            if (Schema::hasColumn('cai_huts', 'osmfeatures_updated_at')) {
                $table->dropColumn('osmfeatures_updated_at');
            }
        });
    }
};
