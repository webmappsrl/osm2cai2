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
        if (!Schema::hasTable('poles')) {
            Schema::create('poles', function (Blueprint $table) {
                $table->char('osm_type', 1)->nullable(); // bpchar(1)
                $table->bigInteger('osm_id')->nullable(); // int8
                $table->increments('id'); // int4 with auto-increment
                $table->text('name')->nullable(); // text with nullable
                $table->json('tags')->nullable(); // jsonb with nullable
                $table->geography('geometry', 'point', 4326)->nullable(); // geometry(Point, 3857) with nullable
                $table->text('ref')->nullable(); // text with nullable
                $table->text('ele')->nullable(); // text with nullable
                $table->text('destination')->nullable(); // text with nullable
                $table->text('support')->nullable(); // text with nullable
                $table->integer('elevation')->nullable(); // int4 with nullable
                $table->integer('score')->nullable(); // int4 with nullable
                $table->timestamps(); // includes created_at and updated_at columns
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poles');
    }
};
