<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ugc_tracks', function (Blueprint $table) {
            $table->geography('geometry', 'MultiLineStringZ', 4326)->after('description')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ugc_tracks', function (Blueprint $table) {
            $table->geography('geometry', 'MultiLineString', 4326)->change();
        });
    }
};
