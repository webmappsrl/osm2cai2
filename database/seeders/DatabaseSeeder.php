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
        // Ensure roles exist before assigning them
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        // create admin user
        $admin = User::where('email', 'team@webmapp.it')->first();
        $nationalReferent = User::where('email', 'referenteNazionale@webmapp.it')->first();

        if (! $admin) {
            $user = User::factory()->create(['email' => 'team@webmapp.it', 'password' => bcrypt('webmapp123'), 'name' => 'Webmapp Team']);

            // if the user already have the role, skip
            if (! $user->hasRole('Administrator')) {
                $user->assignRole('Administrator');
            }
        } else {
            // Ensure existing admin has the role
            if (! $admin->hasRole('Administrator')) {
                $admin->assignRole('Administrator');
            }
        }

        if (! $nationalReferent) {
            $user = User::factory()->create(['email' => 'referenteNazionale@webmapp.it', 'password' => bcrypt('webmapp123'), 'name' => 'Referente Nazionale']);

            // if the user already have the role, skip
            if (! $user->hasRole('National Referent')) {
                $user->assignRole('National Referent');
            }
        } else {
            // Ensure existing national referent has the role
            if (! $nationalReferent->hasRole('National Referent')) {
                $nationalReferent->assignRole('National Referent');
            }
        }
    }
}
