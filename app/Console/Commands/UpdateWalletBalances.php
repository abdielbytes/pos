<?php

// app/Console/Commands/UpdateWalletBalances.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class UpdateWalletBalances extends Command
{
    protected $signature = 'wallets:update-balances';
    protected $description = 'Update wallet balances based on sales totals';

    public function handle()
    {
        $users = User::all();

        foreach ($users as $user) {
            $totalSales = $user->sales()->sum('GrandTotal');
            $wallet = $user->wallet;
            if ($wallet) {
                $wallet->balance = $totalSales;
                $wallet->save();
            }
        }

        $this->info('Wallet balances updated successfully.');
    }
}
