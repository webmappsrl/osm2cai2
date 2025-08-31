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
        Schema::create('taxonomy_themeables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_theme_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('taxonomy_themeable_id');
            $table->string('taxonomy_themeable_type');
            $table->timestamps();
            
            $table->unique(['taxonomy_theme_id', 'taxonomy_themeable_id', 'taxonomy_themeable_type'], 'themeable_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxonomy_themeables');
    }
};
