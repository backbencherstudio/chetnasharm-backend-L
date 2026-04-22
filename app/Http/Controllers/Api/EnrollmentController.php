<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{

    public function getEnrollmentsByBatch(Request $request, $batchId)
    {
        $user   = auth('api')->user();
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);

        $query = Enrollment::with([
            'user:id,name,email',
            'batch:id,name,teacher_id',
            'class:id,title'
        ])->where('batch_id', $batchId);

        if (!$user->hasRole('admin')) {
            $query->whereHas('batch', function ($q) use ($user) {
                $q->where('teacher_id', $user->teacher->id ?? 0);
            });
        }

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $enrollments = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Enrollments fetched successfully',
            'data' => $enrollments->items(),
            'pagination' => [
                'current_page' => $enrollments->currentPage(),
                'per_page'     => $enrollments->perPage(),
                'total'        => $enrollments->total(),
                'last_page'    => $enrollments->lastPage(),
            ]
        ]);
    }

    public function changeBatch(Request $request)
    {
        $request->validate([
            'user_id'       => 'required|exists:users,id',
            'from_batch_id' => 'required|exists:batches,id',
            'to_batch_id'   => 'required|exists:batches,id',
        ]);

        $enrollment = Enrollment::where('user_id', $request->user_id)
            ->where('batch_id', $request->from_batch_id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'Student not enrolled in this batch'
            ], 422);
        }

        $fromBatch = Batch::findOrFail($request->from_batch_id);
        $toBatch   = Batch::findOrFail($request->to_batch_id);

        if ($fromBatch->class_id !== $toBatch->class_id) {
            return response()->json([
                'success' => false,
                'message' => 'Batches must belong to same class'
            ], 422);
        }

        if ($toBatch->filled_seat >= $toBatch->total_seat) {
            return response()->json([
                'success' => false,
                'message' => 'Target batch is full'
            ], 422);
        }

        $enrollment->update([
            'batch_id' => $toBatch->id
        ]);

        $fromBatch->decrement('filled_seat');
        $toBatch->increment('filled_seat');

        return response()->json([
            'success' => true,
            'message' => 'Student batch changed successfully'
        ]);
    }

}
