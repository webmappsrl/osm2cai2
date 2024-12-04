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

        //remove nearby_cai_hut and nearby_natural_spring columns from hiking_routes table
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->dropColumn('nearby_cai_huts');
            $table->dropColumn('nearby_natural_springs');
        });

        Schema::create('hiking_route_cai_hut', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('hiking_route_id')->constrained('hiking_routes')->onDelete('set null');
            $table->foreignId('cai_hut_id')->constrained('cai_huts')->onDelete('set null');
            $table->integer('buffer')->default(250);
        });

        Schema::create('hiking_route_natural_spring', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('hiking_route_id')->constrained('hiking_routes')->onDelete('set null');
            $table->foreignId('natural_spring_id')->constrained('natural_springs')->onDelete('set null');
            $table->integer('buffer')->default(250);
        });

        //add timestamps to hiking_route relationships tables
        Schema::table('area_hiking_route', function (Blueprint $table) {
            $table->timestamps();
        });
        Schema::table('hiking_route_province', function (Blueprint $table) {
            $table->timestamps();
        });
        Schema::table('hiking_route_region', function (Blueprint $table) {
            $table->timestamps();
        });
        Schema::table('hiking_route_sector', function (Blueprint $table) {
            $table->timestamps();
        });
        Schema::table('hiking_route_club', function (Blueprint $table) {
            $table->timestamps();
        });
        Schema::table('hiking_route_itinerary', function (Blueprint $table) {
            $table->timestamps();
        });

        Schema::table('mountain_group_region', function (Blueprint $table) {
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //add nearby_cai_hut and nearby_natural_spring columns to hiking_routes table
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->string('nearby_cai_huts')->nullable();
            $table->string('nearby_natural_springs')->nullable();
        });
        Schema::dropIfExists('hiking_route_cai_hut');
        Schema::dropIfExists('hiking_route_natural_spring');
        Schema::dropColumns('area_hiking_route', ['created_at', 'updated_at']);
        Schema::dropColumns('hiking_route_province', ['created_at', 'updated_at']);
        Schema::dropColumns('hiking_route_region', ['created_at', 'updated_at']);
        Schema::dropColumns('hiking_route_sector', ['created_at', 'updated_at']);
        Schema::dropColumns('hiking_route_club', ['created_at', 'updated_at']);
        Schema::dropColumns('hiking_route_itinerary', ['created_at', 'updated_at']);
        Schema::dropColumns('mountain_group_region', ['created_at', 'updated_at']);
    }
};
