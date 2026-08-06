<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(private EnrollmentService $enrollmentService) {}

    /**
     * @return array{items: array<int, Payment>, pagination: array<string, int>}
     */
    public function listPayments(User $authUser, Request $request): array
    {
        $query = Payment::with([
            'user:id,name',
            'batch:id,name',
        ]);

        if (! $authUser->hasRole('admin')) {
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

        $perPage = Pagination::perPage($request);

        $payments = $query->latest()->paginate($perPage);

        return [
            'items' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ];
    }

    /**
     * @param  array{transaction_id?: string}  $validated
     * @return array{
     *     type: 'not_found'|'success'|'batch_full'|'already_enrolled'|'error',
     *     message?: string,
     *     error?: string
     * }
     */
    public function markAsPaid(int $id, array $validated): array
    {
        $payment = Payment::find($id);

        if (! $payment) {
            return ['type' => 'not_found'];
        }

        if ($payment->status === 'paid') {
            if (! $payment->enrollment_id) {
                try {
                    $this->enrollmentService->enrollFromPayment($payment);
                } catch (\Throwable $e) {
                    return [
                        'type' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return ['type' => 'success'];
        }

        DB::beginTransaction();

        try {
            $batch = Batch::lockForUpdate()->findOrFail($payment->batch_id);

            if ($batch->filled_seat >= $batch->total_seat) {
                DB::rollBack();

                return ['type' => 'batch_full'];
            }

            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'paid') {
                $this->enrollmentService->enrollFromPayment($payment, null, false);
                DB::commit();

                return ['type' => 'success'];
            }

            $exists = Enrollment::where('user_id', $payment->user_id)
                ->where('batch_id', $payment->batch_id)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                DB::rollBack();

                return ['type' => 'already_enrolled'];
            }

            $payment->transaction_id = $validated['transaction_id'];
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();

            $enrollment = $this->enrollmentService->enrollFromPayment($payment, null, false);

            if (! $enrollment) {
                throw new \Exception('Enrollment could not be created');
            }

            DB::commit();

            return ['type' => 'success'];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'type' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
}
