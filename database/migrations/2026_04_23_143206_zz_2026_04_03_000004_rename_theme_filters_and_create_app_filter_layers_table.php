<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            if (Schema::hasColumn('apps', 'filter_theme')) {
                $table->renameColumn('filter_theme', 'filter_layer');
            }
            if (Schema::hasColumn('apps', 'filter_theme_label')) {
                $table->renameColumn('filter_theme_label', 'filter_layer_label');
            }
            if (Schema::hasColumn('apps', 'filter_theme_exclude')) {
                $table->renameColumn('filter_theme_exclude', 'filter_layer_exclude');
            }
        });

        if (Schema::hasTable('app_filter_layers')) {
            return;
        }

        Schema::create('app_filter_layers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_id');
            $table->unsignedBigInteger('layer_id');
            $table->timestamps();

            $table->foreign('app_id')
                ->references('id')
                ->on('apps')
                ->onDelete('cascade');

            $table->foreign('layer_id')
                ->references('id')
                ->on('layers')
                ->onDelete('cascade');

            $table->unique(['app_id', 'layer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_filter_layers');

        Schema::table('apps', function (Blueprint $table) {
            if (Schema::hasColumn('apps', 'filter_layer')) {
                $table->renameColumn('filter_layer', 'filter_theme');
            }
            if (Schema::hasColumn('apps', 'filter_layer_label')) {
                $table->renameColumn('filter_layer_label', 'filter_theme_label');
            }
            if (Schema::hasColumn('apps', 'filter_layer_exclude')) {
                $table->renameColumn('filter_layer_exclude', 'filter_theme_exclude');
            }
        });
    }
};
