<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBatchAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\ChangeBatchRequest;
use App\Http\Resources\EnrollmentResource;
use App\Services\EnrollmentService;
use Exception;
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
            return $this->forbidden('Unauthorized');
        }

        $result = $this->enrollments->getEnrollmentsByBatch($request, $batchId);

        return $this->paginated(
            EnrollmentResource::collection($result['items']),
            $result['pagination'],
            'Enrollments fetched successfully'
        );
    }

    /** Move a student enrollment to another batch. */
    public function changeBatch(ChangeBatchRequest $request): JsonResponse
    {
        try {
            $this->enrollments->changeBatch($request->validated());
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(message: 'Student batch changed successfully');
    }
}
