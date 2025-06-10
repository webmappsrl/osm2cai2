<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('taxonomy_activityables', function (Blueprint $table) {
            $table->foreign(['taxonomy_activity_id'])->references(['id'])->on('taxonomy_activities');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('taxonomy_activityables', function (Blueprint $table) {
            $table->dropForeign('taxonomy_activityables_taxonomy_activity_id_foreign');
        });
    }
};
