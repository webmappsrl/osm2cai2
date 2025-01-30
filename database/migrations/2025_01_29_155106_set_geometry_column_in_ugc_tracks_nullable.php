<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ugc_tracks', function (Blueprint $table) {
            $table->geometry('geometry', 'MultiLineStringZ', 4326)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ugc_tracks', function (Blueprint $table) {
            $table->geometry('geometry', 'MultiLineStringZ', 4326)->nullable(false)->change();
        });
    }
};
