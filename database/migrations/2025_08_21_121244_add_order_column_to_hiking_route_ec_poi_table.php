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
        Schema::table('hiking_route_ec_poi', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('buffer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hiking_route_ec_poi', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
