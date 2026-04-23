<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('name');
            $table->jsonb('label')->nullable();
            $table->boolean('enabled')->default(false);
            $table->enum('mode', ['generated', 'upload', 'external'])->default('generated');
            $table->string('external_url')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->boolean('default')->default(false);
            $table->boolean('clickable')->default(true);
            $table->string('fill_color')->nullable();
            $table->string('stroke_color')->nullable();
            $table->float('stroke_width')->nullable();
            $table->text('icon')->nullable();
            $table->jsonb('configuration')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_collections');
    }
};
