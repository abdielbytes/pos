<?php

// app/Observers/UserObserver.php
namespace App\Observers;

use App\Models\User;
use App\Models\Wallet;

class UserObserver
{
    public function created(User $user)
    {
        // Create a wallet for the user
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    }
}
