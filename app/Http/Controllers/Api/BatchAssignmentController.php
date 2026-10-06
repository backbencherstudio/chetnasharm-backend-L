<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatchAssignment\GradeBatchAssignmentRequest;
use App\Http\Requests\BatchAssignment\StoreBatchAssignmentRequest;
use App\Http\Requests\BatchAssignment\SubmitBatchAssignmentRequest;
use App\Http\Requests\BatchAssignment\UpdateBatchAssignmentRequest;
use App\Http\Resources\AssignmentSubmissionResource;
use App\Http\Resources\BatchAssignmentResource;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\BatchAssignment;
use App\Models\Teacher;
use App\Services\BatchAssignmentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchAssignmentController extends Controller
{
    public function __construct(private readonly BatchAssignmentService $assignments) {}

    /** List assignments for a batch (teacher). */
    public function index(Request $request, int $batchId): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $batch = $this->assignments->teacherBatch($teacher->id, $batchId);

        if (! $batch instanceof Batch) {
            return $this->forbidden('Unauthorized: Invalid batch access');
        }

        $assignments = $this->assignments->indexForTeacher($batch->id, $request);

        return $this->paginate(
            $assignments,
            BatchAssignmentResource::collection($assignments->items()),
            'Assignments fetched successfully'
        );
    }

    /** Create an assignment on a batch. */
    public function store(StoreBatchAssignmentRequest $request): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $validated = $request->validated();

        $batch = $this->assignments->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch instanceof Batch) {
            return $this->forbidden('Unauthorized: Invalid batch access');
        }

        $assignment = $this->assignments->store(
            $teacher,
            $batch,
            $validated,
            $request->file('attachment')
        );

        return $this->created(
            new BatchAssignmentResource($assignment),
            'Assignment created successfully'
        );
    }

    /** Show a single assignment (teacher). */
    public function show(int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $assignment = $this->assignments->findForTeacher($teacher->id, $id);

        if (! $assignment instanceof BatchAssignment) {
            return $this->notFound('Assignment not found');
        }

        return $this->success(
            new BatchAssignmentResource($assignment),
            'Assignment fetched successfully'
        );
    }

    /** Update an assignment. */
    public function update(UpdateBatchAssignmentRequest $request, int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $assignment = $this->assignments->findOwnedByTeacher($teacher->id, $id);

        if (! $assignment instanceof BatchAssignment) {
            return $this->notFound('Assignment not found');
        }

        $assignment = $this->assignments->update(
            $assignment,
            $request->validated(),
            $request->file('attachment')
        );

        return $this->success(
            new BatchAssignmentResource($assignment),
            'Assignment updated successfully'
        );
    }

    /** Delete an assignment and related submission files. */
    public function destroy(int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $assignment = $this->assignments->findOwnedByTeacher($teacher->id, $id);

        if (! $assignment instanceof BatchAssignment) {
            return $this->notFound('Assignment not found');
        }

        $this->assignments->destroy($assignment);

        return $this->success(message: 'Assignment deleted successfully');
    }

    /** List submissions for an assignment (teacher). */
    public function submissions(Request $request, int $id): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $assignment = $this->assignments->findOwnedByTeacher($teacher->id, $id);

        if (! $assignment instanceof BatchAssignment) {
            return $this->notFound('Assignment not found');
        }

        $submissions = $this->assignments->submissions($assignment, $request);

        return $this->paginate(
            $submissions,
            AssignmentSubmissionResource::collection($submissions->items()),
            'Submissions fetched successfully'
        );
    }

    /** Student Assignment tab: active assignments across enrolled batches. */
    public function activeForStudent(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $assignments = $this->assignments->activeForStudent($user, $request);

        if (! $assignments instanceof LengthAwarePaginator) {
            return $this->paginated(
                [],
                Pagination::empty(Pagination::perPage($request))['pagination'],
                'Active assignments fetched successfully'
            );
        }

        return $this->paginate(
            $assignments,
            BatchAssignmentResource::collection($assignments->items()),
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

        $assignments = $this->assignments->forStudent($user, $batchId, $request);

        return $this->paginate(
            $assignments,
            BatchAssignmentResource::collection($assignments->items()),
            'Assignments fetched successfully'
        );
    }

    /** Submit or replace a student assignment file. */
    public function submit(SubmitBatchAssignmentRequest $request, int $assignmentId): JsonResponse
    {
        $user = auth('api')->user();

        $assignment = $this->assignments->findAssignment($assignmentId);

        if (! $assignment instanceof BatchAssignment) {
            return $this->notFound('Assignment not found');
        }

        if (! $this->assignments->studentEnrolledInBatch($user->id, $assignment->batch_id)) {
            return $this->forbidden('Unauthorized: You are not enrolled in this batch');
        }

        if (! $assignment->isOpenForSubmission()) {
            return $this->error('Assignment submission is closed', 422);
        }

        $result = $this->assignments->submit($user, $assignment, $request->file('file'));
        $result['submission']->loadMissing('assignment');

        return $this->success(
            new AssignmentSubmissionResource($result['submission']),
            'Assignment submitted successfully'
        );
    }

    /** Grade a student submission (teacher). */
    public function grade(GradeBatchAssignmentRequest $request, int $submissionId): JsonResponse
    {
        $teacher = $this->assignments->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $submission = $this->assignments->findSubmission($submissionId);

        if (! $submission instanceof AssignmentSubmission || ! $submission->assignment || $submission->assignment->teacher_id !== $teacher->id) {
            return $this->notFound('Submission not found');
        }

        $submission = $this->assignments->grade($submission, $request->validated());
        $submission->loadMissing('assignment');

        return $this->success(
            new AssignmentSubmissionResource($submission),
            'Submission graded successfully'
        );
    }
}
