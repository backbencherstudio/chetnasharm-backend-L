<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

}
