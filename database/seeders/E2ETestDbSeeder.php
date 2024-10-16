<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

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

        $this->call(DatabaseSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(RegionSeeder::class);
    }
}
