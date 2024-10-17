<?php

use App\Enums\ValidatedStatusEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ugc_pois', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('geohub_id')->nullable();
            $table->string('name', 255)->nullable();
            $table->string('description', 255)->nullable();
            $table->geography('geometry', 'point', 4326)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->jsonb('raw_data')->nullable();
            $table->string('taxonomy_wheres', 2000)->nullable();
            $table->string('form_id', 255)->nullable();
            $table->enum('validated', array_column(ValidatedStatusEnum::cases(), 'value'))->default(ValidatedStatusEnum::NOT_VALIDATED->value);
            $table->enum('water_flow_rate_validated', array_column(ValidatedStatusEnum::cases(), 'value'))->default(ValidatedStatusEnum::NOT_VALIDATED->value);
            $table->foreignId('validator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validation_date')->nullable();
            $table->string('note', 1000)->nullable();
            $table->string('app_id', 255)->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ugc_pois');
    }
};
