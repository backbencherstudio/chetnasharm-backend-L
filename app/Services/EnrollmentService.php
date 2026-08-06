<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\EnrollmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    /**
     * @return array{items: array<int, Enrollment>, pagination: array<string, int>}
     */
    public function getEnrollmentsByBatch(Request $request, int $batchId): array
    {
        $search = $request->query('search');
        $perPage = Pagination::perPage($request);

        $query = Enrollment::query()->where('batch_id', $batchId);

        if ($search) {
            $query->withWhereHas('user', function ($q) use ($search) {
                $q->select('id', 'name', 'email', 'image')
                    ->where(function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        } else {
            $query->with('user:id,name,email,image');
        }

        $query->with([
            'batch:id,name,teacher_id',
            'class:id,title',
        ]);

        $enrollments = $query->latest()->paginate($perPage);

        return [
            'items' => $enrollments->items(),
            'pagination' => [
                'current_page' => $enrollments->currentPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
                'last_page' => $enrollments->lastPage(),
            ],
        ];
    }

    /**
     * @param  array{user_id: int, from_batch_id: int, to_batch_id: int}  $validated
     */
    public function changeBatch(array $validated): void
    {
        DB::transaction(function () use ($validated) {
            $fromBatch = Batch::lockForUpdate()->findOrFail($validated['from_batch_id']);
            $toBatch = Batch::lockForUpdate()->findOrFail($validated['to_batch_id']);

            $enrollment = Enrollment::where('user_id', $validated['user_id'])
                ->where('batch_id', $fromBatch->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw new \Exception('Student not enrolled in this batch');
            }

            $alreadyInTarget = $fromBatch->id === $toBatch->id
                || Enrollment::where('user_id', $validated['user_id'])
                    ->where('batch_id', $toBatch->id)
                    ->exists();

            if ($alreadyInTarget) {
                throw new \Exception('Student already enrolled in target batch');
            }

            if ($fromBatch->class_id !== $toBatch->class_id) {
                throw new \Exception('Batches must belong to same class');
            }

            if ($toBatch->filled_seat >= $toBatch->total_seat) {
                throw new \Exception('Target batch is full');
            }

            $enrollment->update([
                'batch_id' => $toBatch->id,
            ]);

            if ($fromBatch->filled_seat > 0) {
                $fromBatch->decrement('filled_seat');
            }

            $toBatch->increment('filled_seat');
        });
    }

    public function enrollFromPayment(Payment $payment, ?int $batchId = null, bool $wrapInTransaction = true): ?Enrollment
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
