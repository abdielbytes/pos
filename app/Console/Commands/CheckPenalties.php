<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sale;
use Illuminate\Support\Facades\Log;

class CheckPenalties extends Command
{
    protected $signature = 'penalties:check';
    protected $description = 'Check and apply penalties for sales';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $sales = Sale::all();

        foreach ($sales as $sale) {
            $frequency = $sale->payment_frequency; // Retrieve the payment_frequency from the sale record

            // Log the frequency for debugging
            Log::info('Sale ID: ' . $sale->id . ' Payment Frequency: ' . $frequency);

            // Check and apply penalty based on the retrieved frequency
            $sale->checkAndApplyPenalty($frequency);
        }

        $this->info('Penalties checked and applied where necessary.');
    }
}
