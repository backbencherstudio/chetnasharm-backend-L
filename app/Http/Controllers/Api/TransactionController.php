<?php

namespace App\Http\Controllers\Api;

use App\Common\EnrollStudentFromPayment;
use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(private EnrollStudentFromPayment $enrollStudentFromPayment) {}

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $authUser = auth('api')->user();

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

        return response()->json([
            'success' => true,
            'message' => 'Payment list fetched successfully',
            'data' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * Mark a payment as paid and enroll the student.
     *
     * @return JsonResponse
     */
    public function markAsPaid(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        if ($payment->status === 'paid') {
            if (! $payment->enrollment_id) {
                try {
                    $this->enrollStudentFromPayment->handle($payment);
                } catch (\Throwable $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Something went wrong',
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction & enrollment successful',
            ], 200);
        }

        $request->validate([
            'transaction_id' => 'required|string|unique:payments,transaction_id',
        ]);

        DB::beginTransaction();

        try {
            $batch = Batch::lockForUpdate()->findOrFail($payment->batch_id);

            if ($batch->filled_seat >= $batch->total_seat) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Batch is full',
                ], 400);
            }

            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'paid') {
                $this->enrollStudentFromPayment->handle($payment, null, false);
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction & enrollment successful',
                ], 200);
            }

            $exists = Enrollment::where('user_id', $payment->user_id)
                ->where('batch_id', $payment->batch_id)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'User is already enrolled in this batch',
                ], 400);
            }

            $payment->transaction_id = $request->transaction_id;
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();

            $enrollment = $this->enrollStudentFromPayment->handle($payment, null, false);

            if (! $enrollment) {
                throw new \Exception('Enrollment could not be created');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction & enrollment successful',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
