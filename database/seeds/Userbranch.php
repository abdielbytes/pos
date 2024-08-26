<?php
//namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Userbranch extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert some stuff
        DB::table('branch_user')->insert(
            array(
                'user_id'      => 1,
                'branch_id' => 1,
            )
        );
    }
}
