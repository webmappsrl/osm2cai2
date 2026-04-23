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
        Schema::create('taxonomy_wheres', function (Blueprint $table) {
            $table->id('id');

            $table->text('name');

            $table->timestamps();
            $table->jsonb('properties')->nullable();
        });

        if (Schema::hasTable('taxonomy_wheres') && ! Schema::hasColumn('taxonomy_wheres', 'geometry')) {
            Schema::table('taxonomy_wheres', function (Blueprint $table) {
                $table->geography('geometry', 'multipolygon')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('taxonomy_wheres');
    }
};
