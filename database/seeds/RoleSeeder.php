<?php
//namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = [
            'CEO',
            'Supervisor',
            'Manager',
            'Ride Officer',
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insert([
                'name' => $role,
                'label' => ucfirst($role),
                'description' => "The $role role",
            ]);
        }
    }
}

