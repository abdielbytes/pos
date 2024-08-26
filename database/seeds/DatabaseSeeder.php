<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            ClientSeeder::class,
            CurrencySeeder::class,
            SettingSeeder::class,
            ServerSeeder::class,
            PermissionsSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            UserRoleSeeder::class,
            CategorySeeder::class,
            PermissionRoleSeeder::class,
            branch::class,
        ]);

//        $this->seedSalesAndSaleDetails();
    }

    private function seedSalesAndSaleDetails()
    {

        DB::table('products')->insert([
            ['id' => 1, 'name' => 'Product 1', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 2, 'name' => 'Product 2', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);

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
                'statut' => 'Completed',
                'notes' => 'This is a sample sale',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        // Seed the sale_details table
        DB::table('sale_details')->insert([
            [
                'date' => Carbon::now()->format('Y-m-d'),
                'sale_id' => 1,
                'product_id' => 1,
                'product_variant_id' => null,
                'price' => 100.00,
                'TaxNet' => 10.00,
                'tax_method' => '1',
                'discount' => 5.00,
                'discount_method' => '1',
                'total' => 105.00,
                'quantity' => 1,
                'payment_frequency' => 'Daily',
                'penalty' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
