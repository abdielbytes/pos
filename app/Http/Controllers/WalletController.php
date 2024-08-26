<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function transfer(Request $request)
    {
        $request->validate([
            'username' => 'required|exists:users,username',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $sender = auth()->user();
        $receiver = User::where('username', $request->username)->first();

        $senderWallet = Wallet::where('user_id', $sender->id)->first();
        $receiverWallet = Wallet::where('user_id', $receiver->id)->first();

        // Ensure sender and receiver wallets exist
        if (!$senderWallet || !$receiverWallet) {
            return response()->json(['message' => 'Wallet not found for one of the users'], 404);
        }

        // Check if sender has enough balance
        if ($senderWallet->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        // Perform the transfer
        $senderWallet->balance -= $request->amount;
        $receiverWallet->balance += $request->amount;

        $senderWallet->save();
        $receiverWallet->save();

        return response()->json(['message' => 'Transfer successful'], 200);
    }
}
