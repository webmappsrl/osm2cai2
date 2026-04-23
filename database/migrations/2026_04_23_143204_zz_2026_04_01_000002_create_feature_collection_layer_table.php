<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_collection_layer', function (Blueprint $table) {
            $table->foreignId('feature_collection_id')->constrained('feature_collections')->cascadeOnDelete();
            $table->foreignId('layer_id')->constrained('layers')->cascadeOnDelete();
            $table->primary(['feature_collection_id', 'layer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_collection_layer');
    }
};
