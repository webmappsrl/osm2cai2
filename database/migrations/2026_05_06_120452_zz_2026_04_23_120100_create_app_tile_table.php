<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_tile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')
                ->constrained('apps')
                ->cascadeOnDelete();
            $table->foreignId('tile_id')
                ->constrained('tiles')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['app_id', 'tile_id']);
            $table->index(['app_id', 'sort_order']);
        });

        // Backfill dalla colonna JSON `apps.tiles` mantenendo l'ordine originale.
        // Format atteso per ogni elemento JSON-string: {"attribution": "server_xyz_url"}
        $tilesByAttribution = DB::table('tiles')->pluck('id', 'attribution');
        $now = now();

        $apps = DB::table('apps')->select('id', 'tiles')->get();
        foreach ($apps as $app) {
            $raw = $app->tiles;
            if (empty($raw)) {
                continue;
            }

            $list = json_decode($raw, true);
            if (! is_array($list)) {
                continue;
            }

            $order = 0;
            foreach ($list as $entry) {
                $decoded = is_string($entry) ? json_decode($entry, true) : $entry;
                if (! is_array($decoded) || empty($decoded)) {
                    continue;
                }

                $attribution = (string) array_key_first($decoded);
                $tileId = $tilesByAttribution[$attribution] ?? null;
                if (! $tileId) {
                    continue;
                }

                DB::table('app_tile')->updateOrInsert(
                    ['app_id' => $app->id, 'tile_id' => $tileId],
                    ['sort_order' => $order, 'created_at' => $now, 'updated_at' => $now]
                );

                $order++;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_tile');
    }
};

