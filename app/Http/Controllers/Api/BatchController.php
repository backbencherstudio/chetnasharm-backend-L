<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBatchAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Batch\StoreBatchRequest;
use App\Http\Requests\Batch\UpdateBatchRequest;
use App\Http\Requests\Batch\UpdateZoomLinkRequest;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Models\Teacher;
use App\Services\BatchService;
use Exception;
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

        return $this->paginated(
            BatchResource::collection($result['items']),
            $result['pagination'],
            'Batch list fetched successfully'
        );
    }

    /** Store a newly created batch with schedules. */
    public function store(StoreBatchRequest $request): JsonResponse
    {
        try {
            $batch = $this->batches->store($request->validated());

            return $this->created(new BatchResource($batch), 'Batch created successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /** Get a batch for editing. */
    public function edit(int $id): JsonResponse
    {
        $batch = $this->batches->findForEdit($id);

        if (! $batch instanceof Batch) {
            return $this->notFound('Batch not found');
        }

        return $this->success(new BatchResource($batch), 'Batch retrieved successfully');
    }

    /** Update the specified batch and its schedules. */
    public function update(UpdateBatchRequest $request, int $id): JsonResponse
    {
        $batch = $this->batches->find($id);

        if (! $batch instanceof Batch) {
            return $this->notFound('Batch not found');
        }

        try {
            $batch = $this->batches->update($batch, $request->validated());

            return $this->success(new BatchResource($batch), 'Batch updated successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /** Delete the specified batch. */
    public function destroy(int $id): JsonResponse
    {
        $batch = $this->batches->find($id);

        if (! $batch instanceof Batch) {
            return $this->notFound('Batch not found');
        }

        try {
            $this->batches->delete($batch);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(message: 'Batch deleted successfully');
    }

    /** List active classes for batch forms. */
    public function classList(): JsonResponse
    {
        $classes = $this->batches->classList();

        return $this->success($classes, 'Class list retrieved successfully');
    }

    /** List active teachers for batch forms. */
    public function teacherList(): JsonResponse
    {
        $teachers = $this->batches->teacherList();

        return $this->success($teachers, 'Teacher list retrieved successfully');
    }

    /** Toggle the active status of a batch. */
    public function status(int $id): JsonResponse
    {
        $batch = $this->batches->find($id);

        if (! $batch instanceof Batch) {
            return $this->notFound('Batch not found');
        }

        $data = $this->batches->toggleStatus($batch);

        return $this->success($data, 'Batch status updated successfully');
    }

    /** List batches assigned to the authenticated teacher. */
    public function teacherBatch(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (! $teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $result = $this->batches->teacherBatch($request, $teacher);

        return $this->paginated(
            BatchResource::collection($result['items']),
            $result['pagination'],
            'Batch list fetched successfully'
        );
    }

    /** List active batches for a class. */
    public function getBatchesByClass(int $classId): JsonResponse
    {
        $batches = $this->batches->getBatchesByClass($classId);

        return $this->success(BatchResource::collection($batches), 'Batches fetched successfully');
    }

    /** List batches for the authenticated student. */
    public function studentBatch(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $result = $this->batches->studentBatch($request, $user->id);

        if ($result === null) {
            return $this->notFound('You are not enrolled in any batches');
        }

        return $this->paginated(
            BatchResource::collection($result['items']),
            $result['pagination'],
            'Batches fetched successfully'
        );
    }

    /** Update the Zoom link for a batch. */
    public function updateZoomLink(UpdateZoomLinkRequest $request, int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        $batch = $this->batches->find($batchId);

        if (! $batch instanceof Batch) {
            return $this->notFound('Batch not found');
        }

        $validated = $request->validated();

        if ($user->hasRole('admin')) {
            $batch = $this->batches->updateZoomLink($batch, $validated['zoom_link']);

            return $this->success([
                'id' => $batch->id,
                'zoom_link' => $batch->zoom_link,
            ], 'Zoom link updated successfully');
        }

        if ($user->hasRole('teacher')) {
            if (! $user->teacher) {
                return $this->forbidden('Teacher profile not found');
            }

            if (! $this->canManageBatch($user, (int) $batch->id)) {
                return $this->forbidden('You are not allowed to update this batch');
            }

            $batch = $this->batches->updateZoomLink($batch, $validated['zoom_link']);

            return $this->success([
                'id' => $batch->id,
                'zoom_link' => $batch->zoom_link,
            ], 'Zoom link updated successfully');
        }

        return $this->forbidden('Unauthorized access');
    }

    /** Get details for a single batch for authenticated users. */
    public function singleBatch(int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        $batch = $this->batches->singleBatch($batchId);

        if (! $batch instanceof Batch) {
            return $this->notFound('Batch not found');
        }

        if ($user->hasRole('teacher')) {
            if (($user->teacher->id ?? 0) !== $batch->teacher_id) {
                return $this->forbidden('Unauthorized');
            }
        }

        if ($user->hasRole('student')) {
            if (! $this->batches->isStudentEnrolled($user->id, $batch->id)) {
                return $this->forbidden('Unauthorized');
            }
        }

        return $this->success(new BatchResource($batch), 'Batch details fetched successfully');
    }
}
