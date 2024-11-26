<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
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
        if (! $nationalReferent) {
            $user = User::factory()->create(['email' => 'referenteNazionale@webmapp.it', 'password' => bcrypt('webmapp123'), 'name' => 'Referente Nazionale']);
            $user->roles()->attach(Role::where('name', 'National Referent')->first());
        }

        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }
}
