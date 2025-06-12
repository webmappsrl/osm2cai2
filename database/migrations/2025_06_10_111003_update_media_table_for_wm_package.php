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
        Schema::table('media', function (Blueprint $table) {
            $table->geography('geometry', 'pointz')->default('POINT(0 0 0)');
            $table->integer('app_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->foreign(['app_id'])->references(['id'])->on('apps')->onDelete('CASCADE');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onDelete('CASCADE');

            $table->spatialIndex('geometry');
            $table->index('app_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['app_id']);
            $table->dropSpatialIndex(['geometry']);

            $table->dropColumn(['user_id', 'app_id', 'geometry']);
        });
    }
};
