<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Imposta default(1) su app_id così le nuove HikingRoute create dal sync osmfeatures
     * ricevono automaticamente app_id=1 dall'INSERT che non specifica il campo.
     */
    public function up(): void
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->integer('app_id')->default(1)->nullable()->change();
        });

        DB::table('hiking_routes')->whereNull('app_id')->update(['app_id' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->integer('app_id')->nullable()->change();
        });
    }
};
