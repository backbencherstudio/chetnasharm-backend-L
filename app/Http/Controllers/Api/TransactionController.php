<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
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

    public function markAsPaid(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        $request->validate([
            'transaction_id' => 'required|string|unique:payments,transaction_id',
        ]);

        $batch = $payment->batch;

        $exists = Enrollment::where('user_id', $payment->user_id)
            ->where('batch_id', $batch->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'User is already enrolled in this batch'
            ], 400);
        }

        if ($batch->filled_seat >= $batch->total_seat) {
            return response()->json([
                'success' => false,
                'message' => 'Batch is full'
            ], 400);
        }

        $batch->increment('filled_seat');

        $enrollment = Enrollment::create([
            'user_id' => $payment->user_id,
            'batch_id' => $batch->id,
            'class_id' => $batch->class_id,
            'status' => 'active',
            'enrolled_at' => now(),
            'expiry_date' => $batch->end_date ? $batch->end_date : null,
        ]);

        $payment->transaction_id = $request->transaction_id;
        $payment->status = 'paid';
        $payment->paid_at = now();
        $payment->enrollment_id = $enrollment->id;
        $payment->save();

        return response()->json([
            'success' => true,
            'message' => 'Transaction & enrollment successful'
        ],200);
    }

}

