<?php

use App\Enums\IssuesStatusEnum;
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
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->integer('osm2cai_status')->nullable();
            $table->date('validation_date')->nullable();
            $table->geometry('geometry_raw_data', 'MultiLineString', 4326)->nullable();
            $table->boolean('region_favorite')->default(false);
            $table->date('region_favorite_publication_date')->nullable();
            $table->string('issues_status')->default(IssuesStatusEnum::Unknown);
            $table->unsignedBigInteger('issues_user_id')->nullable();
            $table->jsonb('issues_chronology')->nullable();
            $table->text('issues_description')->nullable();
            $table->string('description_cai_it', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->dropColumn('osm2cai_status');
            $table->dropColumn('validation_date');
            $table->dropColumn('geometry_raw_data');
            $table->dropColumn('region_favorite');
            $table->dropColumn('region_favorite_publication_date');
            $table->dropColumn('issues_last_update');
            $table->dropColumn('issues_user_id');
            $table->dropColumn('issues_chronology');
            $table->dropColumn('issues_description');
            $table->dropColumn('description_cai_it');
        });
    }
};
