<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth('api')->user();

        $query = Payment::with([
            'user:id,name',
            'batch:id,name'
        ]);

        if (!$authUser->hasRole('admin')) {
            $query->where('user_id', $authUser->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('payment_id', 'like', "%$search%")
                ->orWhere('transaction_id', 'like', "%$search%")
                ->orWhere('payment_method', 'like', "%$search%")
                ->orWhere('status', 'like', "%$search%")
                ->orWhereHas('user', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%");
                });
            });
        }

        $perPage = $request->get('per_page', 10);

        $payments = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Payment list fetched successfully',
            'data' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page'     => $payments->perPage(),
                'total'        => $payments->total(),
                'last_page'    => $payments->lastPage(),
            ]
        ]);
    }

    public function setTransactionId(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        $request->validate([
            'transaction_id' => 'required|string|unique:payments,transaction_id',
        ]);

        $payment->transaction_id = $request->transaction_id;
        $payment->save();

        return response()->json(['success' => true, 'message' => 'Transaction ID updated successfully']);
    }

    // public function verifyPayment(Request $request)
    // {
    //     $request->validate([
    //         'payment_id' => 'required|string|exists:payments,payment_id',
    //         'transaction_id' => 'required|string',
    //     ]);

    //     $payment = Payment::where('payment_id', $request->payment_id)->first();

    //     if (!$payment) {
    //         return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
    //     }

    //     if ($payment->transaction_id === $request->transaction_id) {
    //         return response()->json(['success' => true, 'message' => 'Payment is valid and already paid']);
    //     }
    //     else {
    //         return response()->json(['success' => false, 'message' => 'Payment is not valid or not paid']);
    //     }
    // }
}

