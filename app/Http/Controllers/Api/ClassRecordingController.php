<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassRecording\StoreClassRecordingRequest;
use App\Http\Requests\ClassRecording\UpdateClassRecordingRequest;
use App\Models\Batch;
use App\Services\ClassRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassRecordingController extends Controller
{
    public function __construct(private ClassRecordingService $recordings) {}

    /** List class recordings for a batch. */
    public function index(Request $request, int $batch_id): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->recordings->index($user, $batch_id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Recordings retrieved successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Create a class recording for a batch. */
    public function store(StoreClassRecordingRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->recordings->findTeacherForUser($user);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $validated = $request->validated();
        $batch = Batch::findOrFail($validated['batch_id']);

        if ($batch->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not assigned to this batch',
            ], 403);
        }

        $recording = $this->recordings->store($validated);

        return response()->json([
            'success' => true,
            'message' => 'Recording created successfully',
            'data' => $recording,
        ]);
    }

    /** Show a single class recording. */
    public function show(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $recording = $this->recordings->findWithBatch($id);

        $teacher = $this->recordings->findTeacherForUser($user);

        if ($teacher) {
            if ($recording->batch->teacher_id !== $teacher->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to view this recording',
                ], 403);
            }
        } else {
            if (! $recording->batch->enrollments()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not enrolled in this batch',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Recording fetched successfully',
            'data' => $recording,
        ]);
    }

    /** Update a class recording. */
    public function update(UpdateClassRecordingRequest $request, int $id): JsonResponse
    {
        $user = auth('api')->user();

        $recording = $this->recordings->find($id);

        $teacher = $this->recordings->findTeacherForUser($user);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $recording->loadMissing('batch:id,teacher_id');

        if (! $recording->batch || $recording->batch->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not assigned to this batch',
            ], 403);
        }

        $validated = $request->validated();
        $batchId = $validated['batch_id'] ?? $recording->batch_id;

        if ((int) $batchId !== (int) $recording->batch_id) {
            $destinationBatch = Batch::findOrFail($batchId);

            if ($destinationBatch->teacher_id !== $teacher->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not assigned to this batch',
                ], 403);
            }
        }

        $recording = $this->recordings->update($recording, $validated, (int) $batchId);

        return response()->json([
            'success' => true,
            'message' => 'Recording updated successfully',
            'data' => $recording,
        ]);
    }

    /** Delete a class recording. */
    public function destroy(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $recording = $this->recordings->findWithBatch($id);

        $teacher = $this->recordings->findTeacherForUser($user);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        if ($recording->batch->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You cannot delete this recording',
            ], 403);
        }

        $this->recordings->destroy($recording);

        return response()->json([
            'success' => true,
            'message' => 'Recording deleted successfully',
        ]);
    }

    /** List class recordings for an enrolled student in a batch. */
    public function forStudent(Request $request, int $batch_id): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->recordings->isStudentEnrolled($user, $batch_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not enrolled in this batch',
            ], 403);
        }

        $result = $this->recordings->forStudent($batch_id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Recordings retrieved successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }
}
