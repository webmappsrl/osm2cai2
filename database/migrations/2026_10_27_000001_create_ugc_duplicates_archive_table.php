<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// STRATO 1 (oc:8867): cherry-pick della migration di wm-package (origin/main, file .stub).
// Codice transitorio: da cancellare quando wm-package verrà aggiornato e la pubblicherà lui.
// La guardia hasTable rende la migration innocua se la tabella esiste già.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ugc_duplicates_archive')) {
            return;
        }

        // Copie UGC duplicate (stesso properties.uuid) rimosse dal command di normalizzazione
        // (oc:8718). reference_id è il record tenuto: niente FK, l'archivio deve sopravvivere
        // anche se un giorno il padre viene cancellato.
        Schema::create('ugc_duplicates_archive', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('original_id');
            $table->unsignedBigInteger('reference_id');
            $table->string('uuid')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('app_id')->nullable();
            $table->jsonb('properties')->nullable();
            $table->jsonb('media_ids')->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('original_updated_at')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();

            $table->index(['model_type', 'reference_id']);
            $table->index('uuid');
        });

        // Geografia senza vincolo di tipo: deve contenere sia linee (tracce) sia punti (POI).
        DB::statement('ALTER TABLE ugc_duplicates_archive ADD COLUMN geometry geography');
    }

    public function down(): void
    {
        Schema::dropIfExists('ugc_duplicates_archive');
    }
};
