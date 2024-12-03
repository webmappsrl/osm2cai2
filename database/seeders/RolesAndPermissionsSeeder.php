<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        Role::firstOrCreate(['name' => 'Itinerary Manager']);
        Role::firstOrCreate(['name' => 'National Referent']);
        Role::firstOrCreate(['name' => 'Regional Referent']);
        Role::firstOrCreate(['name' => 'Local Referent']);
        Role::firstOrCreate(['name' => 'Club Manager']);
        Role::firstOrCreate(['name' => 'Validator']);
        Role::firstOrCreate(['name' => 'Guest']); //can login but no permissions

        Permission::firstOrCreate(['name' => 'validate source surveys']);
        Permission::firstOrCreate(['name' => 'validate archaeological sites']);
        Permission::firstOrCreate(['name' => 'validate geological sites']);
        Permission::firstOrCreate(['name' => 'validate archaeological areas']);
        Permission::firstOrCreate(['name' => 'validate signs']);
        Permission::firstOrCreate(['name' => 'validate pois']);
        Permission::firstOrCreate(['name' => 'validate tracks']);
        Permission::firstOrCreate(['name' => 'manage roles and permissions']);

        $adminRole = Role::where('name', 'Administrator')->first();
        $adminRole->givePermissionTo('validate source surveys');
        $adminRole->givePermissionTo('validate archaeological sites');
        $adminRole->givePermissionTo('validate geological sites');
        $adminRole->givePermissionTo('validate archaeological areas');
        $adminRole->givePermissionTo('validate signs');
        $adminRole->givePermissionTo('validate pois');
        $adminRole->givePermissionTo('validate tracks');
        $adminRole->givePermissionTo('manage roles and permissions');

        //get all users except team@webmapp.it and assign to them the guest role
        $users = User::whereDoesntHave('roles')->where('email', '!=', ['team@webmapp.it', 'referenteNazionale@webmapp.it'])->get();
        if ($users->count() > 0) {
            foreach ($users as $user) {
                $user->assignRole('Guest');
                $user->roles()->detach($user->roles()->where('name', '!=', 'Guest')->pluck('id'));
            }
            User::where('email', 'team@webmapp.it')->first()->assignRole('Administrator');
        }
    }
}
