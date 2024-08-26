<?php

//namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SalesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sales')->insert([
            [
                'user_id' => 1,
                'date' => Carbon::now()->format('Y-m-d'),
                'Ref' => Str::random(10),
                'is_pos' => 1,
                'client_id' => 1,
                'branch_id' => 1,
                'tax_rate' => 10.0,
                'TaxNet' => 5.0,
                'discount' => 2.0,
                'shipping' => 3.0,
                'GrandTotal' => 100.0,
                'paid_amount' => 50.0,
                'payment_statut' => 'Pending',
                'penalty' => 0,
                'payment_frequency' => 'Daily',
                'statut' => 'Completed',
                'notes' => 'This is a sample sale',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // Add more sales records as needed
        ]);
    }
}
