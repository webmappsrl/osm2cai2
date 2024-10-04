<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

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
        $nationalReferent = User::where('email', 'referenteNazionale@webmapp.it')->first();

        if (! $admin) {
            $user = User::factory()->create();
            $user->roles()->attach(Role::where('name', 'Administrator')->first());
        }
        if (!$nationalReferent) {
            $user = User::create(['email' => 'referenteNazionale@webmapp.it', 'password' => bcrypt(env('REFERENT_PASSWORD')), 'name' => 'Referente Nazionale']);
            $user->roles()->attach(Role::where('name', 'National Referent')->first());
        }
    }
}
