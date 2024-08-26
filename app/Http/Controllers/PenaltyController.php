<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class PenaltyController extends Controller
{
    public function store(Request $request)
    {
        // Validate the request
        $validatedData = $request->validate([
            'sale_id' => 'required|exists:sales,id', // Ensure the sale exists
            'penalty_amount' => 'required|numeric|min:0',
        ]);

        // Retrieve the sale record
        $sale = Sale::find($validatedData['sale_id']);

        if ($sale) {
            // Start a database transaction
            DB::beginTransaction();
            try {
                // Add the penalty amount to the existing penalty value in the sales table
                $sale->penalty += $validatedData['penalty_amount'];
                $sale->save();

                // Commit the transaction
                DB::commit();

                // Return a success response
                return response()->json(['message' => 'Penalty added successfully', 'sale' => $sale], 201);
            } catch (\Exception $e) {
                // Rollback the transaction in case of an error
                DB::rollback();
                return response()->json(['message' => 'Error adding penalty', 'error' => $e->getMessage()], 500);
            }
        } else {
            return response()->json(['message' => 'Sale not found'], 404);
        }
    }
}
