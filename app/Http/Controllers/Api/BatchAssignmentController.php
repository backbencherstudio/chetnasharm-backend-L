<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchAssignment\GradeBatchAssignmentRequest;
use App\Http\Requests\BatchAssignment\StoreBatchAssignmentRequest;
use App\Http\Requests\BatchAssignment\SubmitBatchAssignmentRequest;
use App\Http\Requests\BatchAssignment\UpdateBatchAssignmentRequest;
use App\Services\BatchAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchAssignmentController extends Controller
{
    public function __construct(private BatchAssignmentService $assignments) {}

    /** List assignments for a batch (teacher). */
    public function index(Request $request, int $batchId): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $batch = $this->assignments->teacherBatch($teacher->id, $batchId);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access',
            ], 403);
        }

        $result = $this->assignments->indexForTeacher($batch->id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Assignments fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Create an assignment on a batch. */
    public function store(StoreBatchAssignmentRequest $request): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $validated = $request->validated();

        $batch = $this->assignments->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access',
            ], 403);
        }

        $assignment = $this->assignments->store(
            $teacher,
            $batch,
            $validated,
            $request->file('attachment')
        );

        return response()->json([
            'success' => true,
            'message' => 'Assignment created successfully',
            'data' => $this->assignments->formatAssignment($assignment),
        ], 201);
    }

    /** Show a single assignment (teacher). */
    public function show(int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $assignment = $this->assignments->findForTeacher($teacher->id, $id);

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment fetched successfully',
            'data' => $this->assignments->formatAssignment($assignment),
        ]);
    }

    /** Update an assignment. */
    public function update(UpdateBatchAssignmentRequest $request, int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $assignment = $this->assignments->findOwnedByTeacher($teacher->id, $id);

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        $assignment = $this->assignments->update(
            $assignment,
            $request->validated(),
            $request->file('attachment')
        );

        return response()->json([
            'success' => true,
            'message' => 'Assignment updated successfully',
            'data' => $this->assignments->formatAssignment($assignment),
        ]);
    }

    /** Delete an assignment and related submission files. */
    public function destroy(int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $assignment = $this->assignments->findOwnedByTeacher($teacher->id, $id);

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        $this->assignments->destroy($assignment);

        return response()->json([
            'success' => true,
            'message' => 'Assignment deleted successfully',
        ]);
    }

    /** List submissions for an assignment (teacher). */
    public function submissions(Request $request, int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $assignment = $this->assignments->findOwnedByTeacher($teacher->id, $id);

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        $result = $this->assignments->submissions($assignment, $request);

        return response()->json([
            'success' => true,
            'message' => 'Submissions fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Student Assignment tab: active assignments across enrolled batches. */
    public function activeForStudent(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->assignments->activeForStudent($user, $request);

        return response()->json([
            'success' => true,
            'message' => 'Active assignments fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** List assignments for a batch (student). */
    public function forStudent(Request $request, int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->assignments->studentEnrolledInBatch($user->id, $batchId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not enrolled in this batch',
            ], 403);
        }

        $result = $this->assignments->forStudent($user, $batchId, $request);

        return response()->json([
            'success' => true,
            'message' => 'Assignments fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Submit or replace a student assignment file. */
    public function submit(SubmitBatchAssignmentRequest $request, int $assignmentId): JsonResponse
    {
        $user = auth('api')->user();

        $assignment = $this->assignments->findAssignment($assignmentId);

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        if (! $this->assignments->studentEnrolledInBatch($user->id, $assignment->batch_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not enrolled in this batch',
            ], 403);
        }

        if (! $assignment->isOpenForSubmission()) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment submission is closed',
            ], 422);
        }

        $result = $this->assignments->submit($user, $assignment, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Assignment submitted successfully',
            'data' => $this->assignments->formatSubmitResponse($result['submission'], $result['assignment']),
        ]);
    }

    /** Grade a student submission (teacher). */
    public function grade(GradeBatchAssignmentRequest $request, int $submissionId): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $submission = $this->assignments->findSubmission($submissionId);

        if (! $submission || ! $submission->assignment || $submission->assignment->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found',
            ], 404);
        }

        $submission = $this->assignments->grade($submission, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Submission graded successfully',
            'data' => $this->assignments->formatGradeResponse($submission),
        ]);
    }
}
