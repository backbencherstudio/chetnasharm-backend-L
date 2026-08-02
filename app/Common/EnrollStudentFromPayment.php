<?php

namespace App\Common;

use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\EnrollmentNotification;
use Illuminate\Support\Facades\DB;

class EnrollStudentFromPayment
{
    /**
     * Execute the primary class operation.
     */
    public function handle(Payment $payment, ?int $batchId = null, bool $wrapInTransaction = true): ?Enrollment
    {
        $callback = function () use ($payment, $batchId): ?Enrollment {
            if ($payment->status !== 'paid') {
                throw new \Exception('Payment is not paid');
            }

            if (! $payment->batch_id) {
                throw new \Exception('Payment has no batch');
            }

            $resolvedBatchId = (int) $payment->batch_id;

            if ($batchId !== null && (int) $batchId !== $resolvedBatchId) {
                throw new \Exception('Batch mismatch');
            }

            $batch = Batch::lockForUpdate()->findOrFail($resolvedBatchId);

            $existing = Enrollment::where('user_id', $payment->user_id)
                ->where('batch_id', $resolvedBatchId)
                ->first();

            if ($existing) {
                if ((int) $payment->enrollment_id !== (int) $existing->id) {
                    $payment->update([
                        'enrollment_id' => $existing->id,
                    ]);
                }

                return $existing;
            }

            if ($batch->filled_seat >= $batch->total_seat) {
                throw new \Exception('Batch is full');
            }

            $enrollment = Enrollment::create([
                'user_id' => $payment->user_id,
                'batch_id' => $batch->id,
                'class_id' => $batch->class_id,
                'status' => 'active',
                'enrolled_at' => now(),
                'expiry_date' => $batch->end_date ? $batch->end_date : null,
            ]);

            $batch->increment('filled_seat');

            $payment->update([
                'enrollment_id' => $enrollment->id,
            ]);

            $user = User::find($payment->user_id);

            if ($user) {
                $user->notify(new EnrollmentNotification($enrollment));
            }

            return $enrollment;
        };

        if ($wrapInTransaction) {
            return DB::transaction($callback);
        }

        return $callback();
    }
}
