<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiles', function (Blueprint $table) {
            $table->id();
            $table->string('attribution')->unique();
            $table->json('label');
            $table->string('icon')->nullable();
            $table->string('server_xyz');
            $table->string('link')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('tiles')->updateOrInsert(
            ['attribution' => 'webmapp'],
            [
                'label' => json_encode(['it' => 'Webmapp', 'en' => 'Webmapp'], JSON_UNESCAPED_UNICODE),
                'icon' => "tile-default",
                'server_xyz' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'link' => "https://webmapp.it/",
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('tiles')->updateOrInsert(
            ['attribution' => 'mute'],
            [
                'label' => json_encode(['it' => 'Mute', 'en' => 'Mute'], JSON_UNESCAPED_UNICODE),
                'icon' => "tile-mute",
                'server_xyz' => 'http://tiles.webmapp.it/blankmap/{z}/{x}/{y}.png',
                'link' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('tiles')->updateOrInsert(
            ['attribution' => 'satellite'],
            [
                'label' => json_encode(['it' => 'Satellite', 'en' => 'Satellite'], JSON_UNESCAPED_UNICODE),
                'icon' => "tile-satellite",
                'server_xyz' => 'https://api.maptiler.com/tiles/satellite/{z}/{x}/{y}.jpg?key=0Z7ou7nfFFXipdDXHChf',
                'link' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tiles');
    }
};

