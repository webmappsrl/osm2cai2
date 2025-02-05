<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('province_user', function (Blueprint $table) {
            $table->renameColumn('province_name', 'province_id');
        });

        DB::statement('ALTER TABLE province_user ALTER COLUMN province_id TYPE bigint USING province_id::bigint');

        Schema::table('province_user', function (Blueprint $table) {
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('province_user', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
        });

        DB::statement('ALTER TABLE province_user ALTER COLUMN province_id TYPE varchar USING province_id::varchar');

        Schema::table('province_user', function (Blueprint $table) {
            $table->renameColumn('province_id', 'province_name');
        });
    }
};
