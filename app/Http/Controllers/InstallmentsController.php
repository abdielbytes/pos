<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\installments;

class InstallmentsController extends Controller
{
    public function update(Request $request, $id)
    {
        // Validate the incoming request
        $request->validate([
            'installment' => 'required|numeric'
        ]);

        // Find the installment by ID
        $installment = installments::find($id);

        if (!$installment) {
            return response()->json(['message' => 'Installment not found'], 404);
        }

        // Update the installment data
        $installment->installment = $request->input('installment');
        $installment->save();

        // Return a response
        return response()->json(['message' => 'Installment updated successfully'], 200);
    }
}
