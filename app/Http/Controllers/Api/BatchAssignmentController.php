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
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $batch = $this->assignments->teacherBatch($teacher->id, $batchId);

        if (! $batch) {
            return $this->forbidden('Unauthorized: Invalid batch access');
        }

        $result = $this->assignments->indexForTeacher($batch->id, $request);

        return $this->paginated(
            $result['items'],
            $result['pagination'],
            'Assignments fetched successfully'
        );
    }

    /** Create an assignment on a batch. */
    public function store(StoreBatchAssignmentRequest $request): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $validated = $request->validated();

        $batch = $this->assignments->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch) {
            return $this->forbidden('Unauthorized: Invalid batch access');
        }

        $assignment = $this->assignments->store(
            $teacher,
            $batch,
            $validated,
            $request->file('attachment')
        );

        return $this->created(
            $this->assignments->formatAssignment($assignment),
            'Assignment created successfully'
        );
    }

    /** Show a single assignment (teacher). */
    public function show(int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $assignment = $this->assignments->findForTeacher($teacher->id, $id);

        if (! $assignment) {
            return $this->notFound('Assignment not found');
        }

        return $this->success(
            $this->assignments->formatAssignment($assignment),
            'Assignment fetched successfully'
        );
    }

    /** Update an assignment. */
    public function update(UpdateBatchAssignmentRequest $request, int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $assignment = $this->assignments->findOwnedByTeacher($teacher->id, $id);

        if (! $assignment) {
            return $this->notFound('Assignment not found');
        }

        $assignment = $this->assignments->update(
            $assignment,
            $request->validated(),
            $request->file('attachment')
        );

        return $this->success(
            $this->assignments->formatAssignment($assignment),
            'Assignment updated successfully'
        );
    }

    /** Delete an assignment and related submission files. */
    public function destroy(int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $assignment = $this->assignments->findOwnedByTeacher($teacher->id, $id);

        if (! $assignment) {
            return $this->notFound('Assignment not found');
        }

        $this->assignments->destroy($assignment);

        return $this->success(message: 'Assignment deleted successfully');
    }

    /** List submissions for an assignment (teacher). */
    public function submissions(Request $request, int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $assignment = $this->assignments->findOwnedByTeacher($teacher->id, $id);

        if (! $assignment) {
            return $this->notFound('Assignment not found');
        }

        $result = $this->assignments->submissions($assignment, $request);

        return $this->paginated(
            $result['items'],
            $result['pagination'],
            'Submissions fetched successfully'
        );
    }

    /** Student Assignment tab: active assignments across enrolled batches. */
    public function activeForStudent(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->assignments->activeForStudent($user, $request);

        return $this->paginated(
            $result['items'],
            $result['pagination'],
            'Active assignments fetched successfully'
        );
    }

    /** List assignments for a batch (student). */
    public function forStudent(Request $request, int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->assignments->studentEnrolledInBatch($user->id, $batchId)) {
            return $this->forbidden('Unauthorized: You are not enrolled in this batch');
        }

        $result = $this->assignments->forStudent($user, $batchId, $request);

        return $this->paginated(
            $result['items'],
            $result['pagination'],
            'Assignments fetched successfully'
        );
    }

    /** Submit or replace a student assignment file. */
    public function submit(SubmitBatchAssignmentRequest $request, int $assignmentId): JsonResponse
    {
        $user = auth('api')->user();

        $assignment = $this->assignments->findAssignment($assignmentId);

        if (! $assignment) {
            return $this->notFound('Assignment not found');
        }

        if (! $this->assignments->studentEnrolledInBatch($user->id, $assignment->batch_id)) {
            return $this->forbidden('Unauthorized: You are not enrolled in this batch');
        }

        if (! $assignment->isOpenForSubmission()) {
            return $this->error('Assignment submission is closed', 422);
        }

        $result = $this->assignments->submit($user, $assignment, $request->file('file'));

        return $this->success(
            $this->assignments->formatSubmitResponse($result['submission'], $result['assignment']),
            'Assignment submitted successfully'
        );
    }

    /** Grade a student submission (teacher). */
    public function grade(GradeBatchAssignmentRequest $request, int $submissionId): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $submission = $this->assignments->findSubmission($submissionId);

        if (! $submission || ! $submission->assignment || $submission->assignment->teacher_id !== $teacher->id) {
            return $this->notFound('Submission not found');
        }

        $submission = $this->assignments->grade($submission, $request->validated());

        return $this->success(
            $this->assignments->formatGradeResponse($submission),
            'Submission graded successfully'
        );
    }
}
