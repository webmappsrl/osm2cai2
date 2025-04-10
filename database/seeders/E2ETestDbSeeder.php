<?php

namespace Database\Seeders;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
        //if application is in production, do not run the seeder
        if (App::environment('production')) {
            return;
        }

        //wipe the database
        Artisan::call('migrate:fresh');

        $this->call(DatabaseSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(RegionSeeder::class);
    }
}
