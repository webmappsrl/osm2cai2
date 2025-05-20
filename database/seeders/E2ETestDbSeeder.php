<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

/**
 * This seeder is used to seed the database for the e2e tests.
 * It is used to create the database structure and the data needed for the e2e tests.
 * USE IT ONLY FOR E2E TESTS.
 */
class E2ETestDbSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // if application is in production, do not run the seeder
        if (App::environment('production')) {
            return;
        }

        // wipe the database
        Artisan::call('migrate:fresh');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create users with roles
        $this->call(DatabaseSeeder::class);

        // Add regions
        $this->call(RegionSeeder::class);
    }
}
