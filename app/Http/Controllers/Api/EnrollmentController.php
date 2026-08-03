<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Concerns\AuthorizesBatchAccess;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    use AuthorizesBatchAccess;

    /**
     * List enrollments for a batch.
     *
     * @return JsonResponse
     */
    public function getEnrollmentsByBatch(Request $request, $batchId)
    {
        $user = auth('api')->user();
        $search = $request->query('search');
        $perPage = Pagination::perPage($request);

        if (! $this->canManageBatch($user, (int) $batchId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = Enrollment::query()->where('batch_id', $batchId);

        if ($search) {
            $query->withWhereHas('user', function ($q) use ($search) {
                $q->select('id', 'name', 'email')
                    ->where(function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        } else {
            $query->with('user:id,name,email');
        }

        $query->with([
            'batch:id,name,teacher_id',
            'class:id,title',
        ]);

        $enrollments = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Enrollments fetched successfully',
            'data' => $enrollments->items(),
            'pagination' => [
                'current_page' => $enrollments->currentPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
                'last_page' => $enrollments->lastPage(),
            ],
        ]);
    }

    /**
     * Move a student enrollment to another batch.
     *
     * @return JsonResponse
     */
    public function changeBatch(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'from_batch_id' => 'required|exists:batches,id',
            'to_batch_id' => 'required|exists:batches,id',
        ]);

        return DB::transaction(function () use ($request) {
            $fromBatch = Batch::lockForUpdate()->findOrFail($request->from_batch_id);
            $toBatch = Batch::lockForUpdate()->findOrFail($request->to_batch_id);

            $enrollment = Enrollment::where('user_id', $request->user_id)
                ->where('batch_id', $fromBatch->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not enrolled in this batch',
                ], 422);
            }

            $alreadyInTarget = $fromBatch->id === $toBatch->id
                || Enrollment::where('user_id', $request->user_id)
                    ->where('batch_id', $toBatch->id)
                    ->exists();

            if ($alreadyInTarget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student already enrolled in target batch',
                ], 422);
            }

            if ($fromBatch->class_id !== $toBatch->class_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batches must belong to same class',
                ], 422);
            }

            if ($toBatch->filled_seat >= $toBatch->total_seat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Target batch is full',
                ], 422);
            }

            $enrollment->update([
                'batch_id' => $toBatch->id,
            ]);

            if ($fromBatch->filled_seat > 0) {
                $fromBatch->decrement('filled_seat');
            }

            $toBatch->increment('filled_seat');

            return response()->json([
                'success' => true,
                'message' => 'Student batch changed successfully',
            ]);
        });
    }
}
