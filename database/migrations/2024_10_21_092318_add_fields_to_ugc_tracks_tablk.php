<?php

use App\Enums\ValidatedStatusEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ugc_tracks', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->integer('geohub_id')->nullable();
            $table->geography('geometry', 'MultiLineString', 4326)->nullable();
            $table->integer('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('taxonomy_wheres', 2000)->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->text('metadata')->nullable();
            $table->string('app_id')->nullable();
            $table->enum('validated', array_column(ValidatedStatusEnum::cases(), 'value'))->default(ValidatedStatusEnum::NOT_VALIDATED->value);
            $table->foreignId('validator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validation_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ugc_tracks', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('description');
            $table->dropColumn('geohub_id');
            $table->dropColumn('geometry');
            $table->dropColumn('user_id');
            $table->dropColumn('taxonomy_wheres');
            $table->dropColumn('raw_data');
            $table->dropColumn('metadata');
            $table->dropColumn('app_id');
            $table->dropColumn('validated');
            $table->dropColumn('validator_id');
            $table->dropColumn('validation_date');
        });
    }
};
