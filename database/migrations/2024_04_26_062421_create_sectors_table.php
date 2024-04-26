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
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->geography('geometry', 'multipolygon', 4326);
            $table->string('code', 1);
            $table->string('full_code', 5);
            $table->integer('num_expected');
            $table->text('human_name')->nullable();
            $table->string('manager')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
