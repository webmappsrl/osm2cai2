<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::firstOrCreate(['name' => 'Administrator']);
        Role::firstOrCreate(['name' => 'National Referent']);
        Role::firstOrCreate(['name' => 'Regional Referent']);
        Role::firstOrCreate(['name' => 'Local Referent']);
        Role::firstOrCreate(['name' => 'Sectional Referent']);
        Role::firstOrCreate(['name' => 'Validator']);
        Role::firstOrCreate(['name' => 'Guest']); //can login but no permissions
        Role::firstOrCreate(['name' => 'No login user']); //can't login

        Permission::firstOrCreate(['name' => 'validate source surveys']);
        Permission::firstOrCreate(['name' => 'validate archaeological sites']);
        Permission::firstOrCreate(['name' => 'validate geological sites']);
        Permission::firstOrCreate(['name' => 'validate archaeological areas']);
        Permission::firstOrCreate(['name' => 'validate signs']);
        Permission::firstOrCreate(['name' => 'manage roles and permissions']);

        $adminRole = Role::where('name', 'Administrator')->first();
        $adminRole->givePermissionTo('validate source surveys');
        $adminRole->givePermissionTo('validate archaeological sites');
        $adminRole->givePermissionTo('validate geological sites');
        $adminRole->givePermissionTo('validate archaeological areas');
        $adminRole->givePermissionTo('validate signs');
        $adminRole->givePermissionTo('manage roles and permissions');

        //get all users except team@webmapp.it and assign to them the guest role
        $users = User::whereDoesntHave('roles')->where('email', '!=', 'team@webmapp.it')->get();
        foreach ($users as $user) {
            $user->assignRole('Guest');
        }
        User::where('email', 'team@webmapp.it')->first()->assignRole('Administrator');
    }
}
