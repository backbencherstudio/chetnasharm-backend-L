<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\MarkAsPaidRequest;
use App\Http\Resources\PaymentResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transaction) {}

    /** List payments for the authenticated user or all payments for admins. */
    public function index(): JsonResponse
    {
        $data = $this->transaction->listPayments(auth('api')->user(), request());

        return $this->paginated(
            PaymentResource::collection($data['items']),
            $data['pagination'],
            'Payment list fetched successfully'
        );
    }

    /** Mark a payment as paid and enroll the student. */
    public function markAsPaid(MarkAsPaidRequest $request, int $id): JsonResponse
    {
        $result = $this->transaction->markAsPaid($id, $request->validated());

        if ($result['type'] === 'not_found') {
            return $this->notFound('Payment not found');
        }

        if ($result['type'] === 'batch_full') {
            return $this->error('Batch is full', 400);
        }

        if ($result['type'] === 'already_enrolled') {
            return $this->error('User is already enrolled in this batch', 400);
        }

        if ($result['type'] === 'error') {
            return $this->error('Something went wrong', 500, ['error' => $result['error']]);
        }

        return $this->success(message: 'Transaction & enrollment successful');
    }
}
