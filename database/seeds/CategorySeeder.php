<?php

//namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert categories
        DB::table('categories')->insert([
            [
                'code' => 'TRI',
                'name' => 'Tricycle',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'MOT',
                'name' => 'Motorcycle',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'MB',
                'name' => 'Minibus',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
