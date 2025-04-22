<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Wm\WmPackage\Services\RolesAndPermissionsService;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        RolesAndPermissionsService::seedDatabase();

        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'Itinerary Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'National Referent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Regional Referent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Local Referent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Club Manager', 'guard_name' => 'web']);

        // Create permissions
        Permission::firstOrCreate(['name' => 'validate archaeological sites', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate geological sites', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate archaeological areas', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate signs', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate source surveys', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate pois', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate tracks', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'validate paths', 'guard_name' => 'web']);

        // Assign permissions to admin role
        $adminRole = Role::where('name', 'Administrator')->first();
        $adminRole->givePermissionTo('validate archaeological sites');
        $adminRole->givePermissionTo('validate geological sites');
        $adminRole->givePermissionTo('validate archaeological areas');
        $adminRole->givePermissionTo('validate signs');
        $adminRole->givePermissionTo('validate source surveys');
        $adminRole->givePermissionTo('validate pois');
        $adminRole->givePermissionTo('validate tracks');
        $adminRole->givePermissionTo('validate paths');

        // Assign role Guest to users without roles (except specific emails)
        $protectedEmails = ['team@webmapp.it', 'referenteNazionale@webmapp.it'];

        $users = User::whereDoesntHave('roles')
            ->whereNotIn('email', $protectedEmails)
            ->get();

        if ($users->count() > 0) {
            foreach ($users as $user) {
                $user->assignRole('Guest');
                $user->roles()->detach($user->roles()->where('name', '!=', 'Guest')->pluck('id'));
            }
        }

        // Make sure admin and national referent have the correct roles
        $adminUser = User::where('email', 'team@webmapp.it')->first();
        if ($adminUser && ! $adminUser->hasRole('Administrator')) {
            $adminUser->assignRole('Administrator');
        }

        $nationalReferent = User::where('email', 'referenteNazionale@webmapp.it')->first();
        if ($nationalReferent && ! $nationalReferent->hasRole('National Referent')) {
            $nationalReferent->assignRole('National Referent');
        }
    }
}
