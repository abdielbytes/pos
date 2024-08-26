<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class branch extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert some stuff
        DB::table('branches')->insert(
            array(
                'id'      => 1,
                'name'    => 'Default branch',
                'city'    => NULL,
                'mobile'  => NULL,
                'zip'     => NULL,
                'email'   => NULL,
                'country' => NULL,
            )
        );
    }
}
