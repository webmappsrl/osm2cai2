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
        Schema::create('mountain_group_cai_hut', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mountain_group_id')->constrained('mountain_groups')->onDelete('set null');
            $table->foreignId('cai_hut_id')->constrained('cai_huts')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('mountain_group_ec_poi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mountain_group_id')->constrained('mountain_groups')->onDelete('set null');
            $table->foreignId('ec_poi_id')->constrained('ec_pois')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('mountain_group_club', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mountain_group_id')->constrained('mountain_groups')->onDelete('set null');
            $table->foreignId('club_id')->constrained('clubs')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('mountain_group_hiking_route', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mountain_group_id')->constrained('mountain_groups')->onDelete('set null');
            $table->foreignId('hiking_route_id')->constrained('hiking_routes')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mountain_group_cai_hut');
        Schema::dropIfExists('mountain_group_ec_poi');
        Schema::dropIfExists('mountain_group_club');
        Schema::dropIfExists('mountain_group_hiking_route');
    }
};
