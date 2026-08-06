<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\MarkAsPaidRequest;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $transaction) {}

    /** List payments for the authenticated user or all payments for admins. */
    public function index(): JsonResponse
    {
        $data = $this->transaction->listPayments(auth('api')->user(), request());

        return response()->json([
            'success' => true,
            'message' => 'Payment list fetched successfully',
            'data' => $data['items'],
            'pagination' => $data['pagination'],
        ]);
    }

    /** Mark a payment as paid and enroll the student. */
    public function markAsPaid(MarkAsPaidRequest $request, int $id): JsonResponse
    {
        $result = $this->transaction->markAsPaid($id, $request->validated());

        if ($result['type'] === 'not_found') {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        if ($result['type'] === 'batch_full') {
            return response()->json([
                'success' => false,
                'message' => 'Batch is full',
            ], 400);
        }

        if ($result['type'] === 'already_enrolled') {
            return response()->json([
                'success' => false,
                'message' => 'User is already enrolled in this batch',
            ], 400);
        }

        if ($result['type'] === 'error') {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $result['error'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction & enrollment successful',
        ], 200);
    }
}
