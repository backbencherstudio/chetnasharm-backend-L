<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBatchAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\ChangeBatchRequest;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    use AuthorizesBatchAccess;

    public function __construct(private EnrollmentService $enrollments) {}

    /** List enrollments for a batch. */
    public function getEnrollmentsByBatch(Request $request, int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->canManageBatch($user, (int) $batchId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $result = $this->enrollments->getEnrollmentsByBatch($request, $batchId);

        return response()->json([
            'success' => true,
            'message' => 'Enrollments fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Move a student enrollment to another batch. */
    public function changeBatch(ChangeBatchRequest $request): JsonResponse
    {
        try {
            $this->enrollments->changeBatch($request->validated());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Student batch changed successfully',
        ]);
    }
}
