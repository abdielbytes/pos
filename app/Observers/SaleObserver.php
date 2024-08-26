<?php

// app/Observers/SaleObserver.php
namespace App\Observers;

use App\Models\Sale;
use App\Models\Wallet;

class SaleObserver
{
    public function created(Sale $sale)
    {
        $this->updateWalletBalance($sale->user);
    }

    public function updated(Sale $sale)
    {
        $this->updateWalletBalance($sale->user);
    }

    public function deleted(Sale $sale)
    {
        $this->updateWalletBalance($sale->user);
    }

    protected function updateWalletBalance($user)
    {
        $totalSales = $user->sales()->sum('paid_amount');
//        dd($totalSales);
        $wallet = $user->wallet;
        $wallet->balance = $totalSales;
        $wallet->save();
    }
}
