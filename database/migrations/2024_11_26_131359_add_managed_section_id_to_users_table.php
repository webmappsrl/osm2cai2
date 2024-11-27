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
        Schema::table('users', function (Blueprint $table) {
            ///drop column region_name and club_cai_code and add region_id and club_id
            $table->dropColumn('region_name');
            $table->dropColumn('club_cai_code');
            $table->unsignedBigInteger('region_id')->nullable();
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
            $table->unsignedBigInteger('club_id')->nullable();
            $table->foreign('club_id')->references('id')->on('clubs')->onDelete('set null');

            //create columns for managed section
            $table->unsignedBigInteger('managed_club_id')->nullable();
            $table->foreign('managed_club_id')->references('id')->on('clubs')->onDelete('set null');
            $table->dateTime('section_manager_expire_date')->nullable();

            $table->date('regional_referent_expire_date')->nullable();

            $table->string('default_overpass_query', 10000)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropForeign(['club_id']);
            $table->dropForeign(['managed_club_id']);

            $table->dropColumn('region_id');
            $table->dropColumn('club_id');
            $table->dropColumn('managed_club_id');
            $table->dropColumn('section_manager_expire_date');
            $table->dropColumn('regional_referent_expire_date');
            $table->dropColumn('default_overpass_query');

            $table->string('region_name')->nullable();
            $table->string('club_cai_code')->nullable();
        });
    }
};
