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
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->string('description_cai_it', 5000)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->text('description_cai_it')->nullable()->change();
        });
    }
};
