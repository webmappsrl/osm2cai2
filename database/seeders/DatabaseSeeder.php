<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        //create admin user
        $admin = User::where('email', 'team@webmapp.it')->first();

        if (!$admin) {
            User::factory()->create();
        }
    }
}
