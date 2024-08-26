<?php

namespace App\Http\Controllers;

use App\Models\PenaltyPayment;
use Illuminate\Http\Request;

class PenaltyPaymentsController extends Controller
{
    public function index()
    {
        // List all penalty payments
        return PenaltyPayment::all();
    }

    public function create()
    {
        // Show form to create a new penalty payment
    }

    public function store(Request $request)
    {
        // Store a new penalty payment
        $penaltyPayment = PenaltyPayment::create($request->all());
        return response()->json($penaltyPayment, 201);
    }

    public function show($id)
    {
        // Show a specific penalty payment
        return PenaltyPayment::findOrFail($id);
    }

    public function edit($id)
    {
        // Show form to edit an existing penalty payment
    }

    public function update(Request $request, $id)
    {
        // Update an existing penalty payment
        $penaltyPayment = PenaltyPayment::findOrFail($id);
        $penaltyPayment->update($request->all());
        return response()->json($penaltyPayment, 200);
    }

    public function destroy($id)
    {
        // Delete an existing penalty payment
        PenaltyPayment::destroy($id);
        return response()->json(null, 204);
    }

    public function getNumberOrder()
    {
        // Logic to get the next order number
    }

    public function SendEmail(Request $request)
    {
        // Logic to send an email related to a penalty payment
    }

    public function Send_SMS(Request $request)
    {
        // Logic to send an SMS related to a penalty payment
    }
}
