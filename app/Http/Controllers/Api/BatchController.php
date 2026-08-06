<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBatchAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Batch\StoreBatchRequest;
use App\Http\Requests\Batch\UpdateBatchRequest;
use App\Http\Requests\Batch\UpdateZoomLinkRequest;
use App\Models\Teacher;
use App\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    use AuthorizesBatchAccess;

    public function __construct(private BatchService $batches) {}

    /** Display a paginated listing of batches. */
    public function index(Request $request): JsonResponse
    {
        $result = $this->batches->index($request);

        return response()->json([
            'success' => true,
            'message' => 'Batch list fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Store a newly created batch with schedules. */
    public function store(StoreBatchRequest $request): JsonResponse
    {
        try {
            $batch = $this->batches->store($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Batch created successfully',
                'data' => $batch,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /** Get a batch for editing. */
    public function edit(int $id): JsonResponse
    {
        $batch = $this->batches->findForEdit($id);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    /** Update the specified batch and its schedules. */
    public function update(UpdateBatchRequest $request, int $id): JsonResponse
    {
        $batch = $this->batches->find($id);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        try {
            $batch = $this->batches->update($batch, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Batch updated successfully',
                'data' => $batch,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /** Delete the specified batch. */
    public function destroy(int $id): JsonResponse
    {
        $batch = $this->batches->find($id);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        try {
            $this->batches->delete($batch);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Batch deleted successfully',
        ]);
    }

    /** List active classes for batch forms. */
    public function classList(): JsonResponse
    {
        $classes = $this->batches->classList();

        return response()->json([
            'success' => true,
            'message' => 'Class list retrieved successfully',
            'data' => $classes,
        ]);
    }

    /** List active teachers for batch forms. */
    public function teacherList(): JsonResponse
    {
        $teachers = $this->batches->teacherList();

        return response()->json([
            'success' => true,
            'message' => 'Teacher list retrieved successfully',
            'data' => $teachers,
        ]);
    }

    /** Toggle the active status of a batch. */
    public function status(int $id): JsonResponse
    {
        $batch = $this->batches->find($id);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        $data = $this->batches->toggleStatus($batch);

        return response()->json([
            'success' => true,
            'message' => 'Batch status updated successfully',
            'data' => $data,
        ]);
    }

    /** List batches assigned to the authenticated teacher. */
    public function teacherBatch(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $result = $this->batches->teacherBatch($request, $teacher);

        return response()->json([
            'success' => true,
            'message' => 'Batch list fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** List active batches for a class. */
    public function getBatchesByClass(int $classId): JsonResponse
    {
        $batches = $this->batches->getBatchesByClass($classId);

        return response()->json([
            'success' => true,
            'message' => 'Batches fetched successfully',
            'data' => $batches,
        ]);
    }

    /** List batches for the authenticated student. */
    public function studentBatch(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $result = $this->batches->studentBatch($request, $user->id);

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'You are not enrolled in any batches',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Batches fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Update the Zoom link for a batch. */
    public function updateZoomLink(UpdateZoomLinkRequest $request, int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        $batch = $this->batches->find($batchId);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        $validated = $request->validated();

        if ($user->hasRole('admin')) {
            $batch = $this->batches->updateZoomLink($batch, $validated['zoom_link']);

            return response()->json([
                'success' => true,
                'message' => 'Zoom link updated successfully',
                'data' => [
                    'id' => $batch->id,
                    'zoom_link' => $batch->zoom_link,
                ],
            ]);
        }

        if ($user->hasRole('teacher')) {
            if (! $user->teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher profile not found',
                ], 403);
            }

            if (! $this->canManageBatch($user, (int) $batch->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to update this batch',
                ], 403);
            }

            $batch = $this->batches->updateZoomLink($batch, $validated['zoom_link']);

            return response()->json([
                'success' => true,
                'message' => 'Zoom link updated successfully',
                'data' => [
                    'id' => $batch->id,
                    'zoom_link' => $batch->zoom_link,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized access',
        ], 403);
    }

    /** Get details for a single batch for authenticated users. */
    public function singleBatch(int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        $batch = $this->batches->singleBatch($batchId);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        if ($user->hasRole('teacher')) {
            if (($user->teacher->id ?? 0) !== $batch->teacher_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        if ($user->hasRole('student')) {
            if (! $this->batches->isStudentEnrolled($user->id, $batch->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Batch details fetched successfully',
            'data' => $batch,
        ]);
    }
}
