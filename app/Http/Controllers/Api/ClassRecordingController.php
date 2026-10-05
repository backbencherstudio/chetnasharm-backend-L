<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassRecording\StoreClassRecordingRequest;
use App\Http\Requests\ClassRecording\UpdateClassRecordingRequest;
use App\Http\Resources\ClassRecordingResource;
use App\Models\Batch;
use App\Models\Teacher;
use App\Services\ClassRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassRecordingController extends Controller
{
    public function __construct(private readonly ClassRecordingService $recordings) {}

    /** List class recordings for a batch. */
    public function index(Request $request, int $batch_id): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->recordings->index($user, $batch_id, $request);

        return $this->paginated(
            ClassRecordingResource::collection($result['items']),
            $result['pagination'],
            'Recordings retrieved successfully'
        );
    }

    /** Create a class recording for a batch. */
    public function store(StoreClassRecordingRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->recordings->findTeacherForUser($user);

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $validated = $request->validated();
        $batch = Batch::findOrFail($validated['batch_id']);

        if ($batch->teacher_id !== $teacher->id) {
            return $this->forbidden('Unauthorized: You are not assigned to this batch');
        }

        $recording = $this->recordings->store($validated);

        return $this->created(
            new ClassRecordingResource($recording),
            'Recording created successfully'
        );
    }

    /** Show a single class recording. */
    public function show(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $recording = $this->recordings->findWithBatch($id);

        $teacher = $this->recordings->findTeacherForUser($user);

        if ($teacher instanceof Teacher) {
            if ($recording->batch->teacher_id !== $teacher->id) {
                return $this->forbidden('Unauthorized: You do not have permission to view this recording');
            }
        } else {
            if (! $recording->batch->enrollments()->where('user_id', $user->id)->exists()) {
                return $this->forbidden('Unauthorized: You are not enrolled in this batch');
            }
        }

        return $this->success(
            new ClassRecordingResource($recording),
            'Recording fetched successfully'
        );
    }

    /** Update a class recording. */
    public function update(UpdateClassRecordingRequest $request, int $id): JsonResponse
    {
        $user = auth('api')->user();

        $recording = $this->recordings->find($id);

        $teacher = $this->recordings->findTeacherForUser($user);

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $recording->loadMissing('batch:id,teacher_id');

        if (! $recording->batch || $recording->batch->teacher_id !== $teacher->id) {
            return $this->forbidden('Unauthorized: You are not assigned to this batch');
        }

        $validated = $request->validated();
        $batchId = $validated['batch_id'] ?? $recording->batch_id;

        if ((int) $batchId !== (int) $recording->batch_id) {
            $destinationBatch = Batch::findOrFail($batchId);

            if ($destinationBatch->teacher_id !== $teacher->id) {
                return $this->forbidden('Unauthorized: You are not assigned to this batch');
            }
        }

        $recording = $this->recordings->update($recording, $validated, (int) $batchId);

        return $this->success(
            new ClassRecordingResource($recording),
            'Recording updated successfully'
        );
    }

    /** Delete a class recording. */
    public function destroy(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $recording = $this->recordings->findWithBatch($id);

        $teacher = $this->recordings->findTeacherForUser($user);

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        if ($recording->batch->teacher_id !== $teacher->id) {
            return $this->forbidden('Unauthorized: You cannot delete this recording');
        }

        $this->recordings->destroy($recording);

        return $this->success(message: 'Recording deleted successfully');
    }

    /** List class recordings for an enrolled student in a batch. */
    public function forStudent(Request $request, int $batch_id): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->recordings->isStudentEnrolled($user, $batch_id)) {
            return $this->forbidden('Unauthorized: You are not enrolled in this batch');
        }

        $result = $this->recordings->forStudent($batch_id, $request);

        return $this->paginated(
            ClassRecordingResource::collection($result['items']),
            $result['pagination'],
            'Recordings retrieved successfully'
        );
    }
}
