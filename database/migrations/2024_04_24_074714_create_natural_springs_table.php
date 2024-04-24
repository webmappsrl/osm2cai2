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
        Schema::create('natural_springs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('code', 9)->nullable();
            $table->string('loc_ref')->nullable();
            $table->string('source')->nullable();
            $table->string('source_ref')->nullable();
            $table->string('source_code')->nullable();
            $table->string('name')->nullable();
            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('municipality')->nullable();
            $table->string('operator')->nullable();
            $table->string('type')->nullable();
            $table->decimal('volume', 8, 2)->nullable();
            $table->smallInteger('time')->nullable();
            $table->decimal('mass_flow_rate', 8, 2)->nullable();
            $table->decimal('temperature', 8, 2)->nullable();
            $table->decimal('conductivity', 8, 2)->nullable();
            $table->date('survey_date')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lon', 11, 8)->nullable();
            $table->smallInteger('elevation')->nullable();
            $table->string('note')->nullable();
            $table->geography('geometry', 'point', 4326)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('natural_springs');
    }
};
