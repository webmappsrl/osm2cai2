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
        Schema::create('ugc_media', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('geohub_id')->nullable();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->geography('geometry', 'POINT', 4326)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('ugc_poi_id')->nullable();
            $table->unsignedBigInteger('ugc_track_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('ugc_poi_id')->references('id')->on('ugc_poi')->onDelete('set null');
            $table->foreign('ugc_track_id')->references('id')->on('ugc_track')->onDelete('set null');
            $table->jsonb('raw_data')->nullable();
            $table->string('taxonomy_wheres')->nullable();
            $table->string('relative_url')->nullable();
            $table->string('app_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ugc_media');
    }
};
