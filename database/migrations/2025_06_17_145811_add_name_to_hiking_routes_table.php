<?php

use App\Models\HikingRoute;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->string('name')->nullable();
        });

        // Use chunkById to avoid memory issues with large datasets.
        HikingRoute::query()->chunkById(200, function ($hikingRoutes) {
            foreach ($hikingRoutes as $hikingRoute) {
                $hikingRoute->name = $this->computeName($hikingRoute);
                $hikingRoute->saveQuietly();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hiking_routes', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    protected function computeName(HikingRoute $hikingRoute): string
    {
        $properties = $hikingRoute->osmfeatures_data['properties'] ?? [];

        $ref = $properties['ref'] ?? null;
        $from = $properties['from'] ?? null;
        $to = $properties['to'] ?? null;

        $nameParts = [];
        $nameParts[] = $this->validateData($ref) ? $ref : 'noRef';
        $nameParts[] = $this->validateData($from) ? $from : 'noFrom';
        $nameParts[] = $this->validateData($to) ? $to : 'noTo';

        return implode(' - ', $nameParts);
    }


    protected function validateData($data): bool
    {
        return !is_null($data) && $data !== '';
    }
};
