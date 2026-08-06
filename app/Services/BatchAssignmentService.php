<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\BatchAssignment;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BatchAssignmentService
{
    private const FILE_DIRECTORY = 'assignments';

    private const SUBMISSION_DIRECTORY = 'assignment-submissions';

    /** Get the authenticated teacher record. */
    public function currentTeacher(): ?Teacher
    {
        $user = auth('api')->user();

        if (! $user) {
            return null;
        }

        $user->loadMissing('teacher:id,user_id');

        return $user->teacher;
    }

    /** Find a batch owned by the given teacher. */
    public function teacherBatch(int $teacherId, int $batchId): ?Batch
    {
        return Batch::query()
            ->where('id', $batchId)
            ->where('teacher_id', $teacherId)
            ->first();
    }

    /** Check whether a student is actively enrolled in a batch. */
    public function studentEnrolledInBatch(int $studentUserId, int $batchId): bool
    {
        return Enrollment::query()
            ->where('user_id', $studentUserId)
            ->where('batch_id', $batchId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function indexForTeacher(int $batchId, Request $request): array
    {
        $assignments = BatchAssignment::query()
            ->where('batch_id', $batchId)
            ->withCount('submissions')
            ->latest()
            ->paginate(Pagination::perPage($request));

        return [
            'items' => collect($assignments->items())
                ->map(fn (BatchAssignment $assignment) => $this->formatAssignment($assignment))
                ->values()
                ->all(),
            'pagination' => $this->paginationMeta($assignments),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(Teacher $teacher, Batch $batch, array $validated, ?UploadedFile $attachment): BatchAssignment
    {
        $attachmentPath = null;

        if ($attachment) {
            $attachmentPath = $attachment->store(self::FILE_DIRECTORY, 'public');
        }

        return BatchAssignment::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'attachment' => $attachmentPath,
            'starts_at' => $validated['starts_at'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'total_marks' => $validated['total_marks'],
        ]);
    }

    public function findForTeacher(int $teacherId, int $id): ?BatchAssignment
    {
        return BatchAssignment::query()
            ->withCount('submissions')
            ->where('id', $id)
            ->where('teacher_id', $teacherId)
            ->first();
    }

    public function findOwnedByTeacher(int $teacherId, int $id): ?BatchAssignment
    {
        return BatchAssignment::query()
            ->where('id', $id)
            ->where('teacher_id', $teacherId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(BatchAssignment $assignment, array $validated, ?UploadedFile $attachment): BatchAssignment
    {
        $attachmentPath = $assignment->attachment;

        if ($attachment) {
            if ($assignment->attachment && Storage::disk('public')->exists($assignment->attachment)) {
                Storage::disk('public')->delete($assignment->attachment);
            }

            $attachmentPath = $attachment->store(self::FILE_DIRECTORY, 'public');
        }

        $assignment->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'attachment' => $attachmentPath,
            'starts_at' => array_key_exists('starts_at', $validated) ? $validated['starts_at'] : $assignment->starts_at,
            'due_at' => array_key_exists('due_at', $validated) ? $validated['due_at'] : $assignment->due_at,
            'total_marks' => $validated['total_marks'],
        ]);

        return $assignment->fresh()->loadCount('submissions');
    }

    public function destroy(BatchAssignment $assignment): void
    {
        $assignment->load('submissions');

        foreach ($assignment->submissions as $submission) {
            if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
                Storage::disk('public')->delete($submission->file_path);
            }
        }

        if ($assignment->attachment && Storage::disk('public')->exists($assignment->attachment)) {
            Storage::disk('public')->delete($assignment->attachment);
        }

        $assignment->delete();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function submissions(BatchAssignment $assignment, Request $request): array
    {
        $submissions = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->with('student:id,name,email')
            ->latest()
            ->paginate(Pagination::perPage($request));

        return [
            'items' => collect($submissions->items())->map(fn (AssignmentSubmission $submission) => [
                'id' => $submission->id,
                'assignment_id' => $submission->assignment_id,
                'student_user_id' => $submission->student_user_id,
                'student_name' => $submission->student?->name,
                'student_email' => $submission->student?->email,
                'file_url' => $submission->file_path
                    ? asset('storage/'.$submission->file_path)
                    : null,
                'total_marks' => $assignment->total_marks,
                'obtained_marks' => $submission->obtained_marks,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at,
                'submitted_at' => $submission->updated_at,
                'created_at' => $submission->created_at,
            ])->values()->all(),
            'pagination' => $this->paginationMeta($submissions),
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function activeForStudent(User $user, Request $request): array
    {
        $batchIds = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('batch_id');

        if ($batchIds->isEmpty()) {
            return [
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => Pagination::perPage($request),
                    'total' => 0,
                    'last_page' => 1,
                ],
            ];
        }

        $search = $request->query('search');

        $query = BatchAssignment::query()
            ->active()
            ->whereIn('batch_id', $batchIds)
            ->with([
                'batch:id,name,class_id',
                'batch.class:id,title',
                'submissions' => function ($submissionQuery) use ($user) {
                    $submissionQuery->where('student_user_id', $user->id);
                },
            ])
            ->latest('due_at')
            ->latest();

        if ($search) {
            $query->where(function ($assignmentQuery) use ($search) {
                $assignmentQuery->where('title', 'like', "%{$search}%")
                    ->orWhereHas('batch', function ($batchQuery) use ($search) {
                        $batchQuery->where('name', 'like', "%{$search}%")
                            ->orWhereHas('class', fn ($classQuery) => $classQuery->where('title', 'like', "%{$search}%"));
                    });
            });
        }

        if ($request->boolean('pending_only')) {
            $query->whereDoesntHave('submissions', function ($submissionQuery) use ($user) {
                $submissionQuery->where('student_user_id', $user->id);
            });
        }

        $assignments = $query->paginate(Pagination::perPage($request));

        return [
            'items' => collect($assignments->items())->map(function (BatchAssignment $assignment) {
                $submission = $assignment->submissions->first();

                return [
                    ...$this->formatAssignment($assignment),
                    'batch_name' => $assignment->batch?->name,
                    'class_title' => $assignment->batch?->class?->title,
                    'is_open' => true,
                    'has_submitted' => $submission !== null,
                    'my_submission' => $this->formatMySubmission($submission),
                ];
            })->values()->all(),
            'pagination' => $this->paginationMeta($assignments),
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function forStudent(User $user, int $batchId, Request $request): array
    {
        $assignments = BatchAssignment::query()
            ->started()
            ->where('batch_id', $batchId)
            ->with(['submissions' => function ($query) use ($user) {
                $query->where('student_user_id', $user->id);
            }])
            ->latest()
            ->paginate(Pagination::perPage($request));

        return [
            'items' => collect($assignments->items())->map(function (BatchAssignment $assignment) {
                $submission = $assignment->submissions->first();

                return [
                    ...$this->formatAssignment($assignment),
                    'is_open' => $assignment->isOpenForSubmission(),
                    'my_submission' => $this->formatMySubmission($submission),
                ];
            })->values()->all(),
            'pagination' => $this->paginationMeta($assignments),
        ];
    }

    public function findAssignment(int $assignmentId): ?BatchAssignment
    {
        return BatchAssignment::query()->find($assignmentId);
    }

    /**
     * @return array{submission: AssignmentSubmission, assignment: BatchAssignment}
     */
    public function submit(User $user, BatchAssignment $assignment, UploadedFile $file): array
    {
        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_user_id', $user->id)
            ->first();

        $filePath = $file->store(self::SUBMISSION_DIRECTORY, 'public');

        if ($submission) {
            if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
                Storage::disk('public')->delete($submission->file_path);
            }

            $submission->update([
                'file_path' => $filePath,
                'obtained_marks' => null,
                'feedback' => null,
                'graded_at' => null,
            ]);
        } else {
            $submission = AssignmentSubmission::create([
                'assignment_id' => $assignment->id,
                'student_user_id' => $user->id,
                'file_path' => $filePath,
            ]);
        }

        return [
            'submission' => $submission,
            'assignment' => $assignment,
        ];
    }

    public function findSubmission(int $submissionId): ?AssignmentSubmission
    {
        return AssignmentSubmission::query()
            ->with('assignment')
            ->where('id', $submissionId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function grade(AssignmentSubmission $submission, array $validated): AssignmentSubmission
    {
        $submission->update([
            'obtained_marks' => $validated['obtained_marks'],
            'feedback' => $validated['feedback'] ?? null,
            'graded_at' => now(),
        ]);

        return $submission;
    }

    /** Format an assignment for API responses. */
    public function formatAssignment(BatchAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'batch_id' => $assignment->batch_id,
            'teacher_id' => $assignment->teacher_id,
            'title' => $assignment->title,
            'description' => $assignment->description,
            'attachment_url' => $assignment->attachment
                ? asset('storage/'.$assignment->attachment)
                : null,
            'starts_at' => $assignment->starts_at,
            'due_at' => $assignment->due_at,
            'total_marks' => $assignment->total_marks,
            'submissions_count' => $assignment->submissions_count ?? null,
            'created_at' => $assignment->created_at,
            'updated_at' => $assignment->updated_at,
        ];
    }

    /** Format the authenticated student's submission for API responses. */
    public function formatMySubmission(?AssignmentSubmission $submission): ?array
    {
        if (! $submission) {
            return null;
        }

        return [
            'id' => $submission->id,
            'file_url' => asset('storage/'.$submission->file_path),
            'obtained_marks' => $submission->obtained_marks,
            'feedback' => $submission->feedback,
            'graded_at' => $submission->graded_at,
            'submitted_at' => $submission->updated_at,
        ];
    }

    /** Format a student submission response after submit. */
    public function formatSubmitResponse(AssignmentSubmission $submission, BatchAssignment $assignment): array
    {
        return [
            'id' => $submission->id,
            'assignment_id' => $submission->assignment_id,
            'student_user_id' => $submission->student_user_id,
            'file_url' => asset('storage/'.$submission->file_path),
            'total_marks' => $assignment->total_marks,
            'obtained_marks' => $submission->obtained_marks,
            'feedback' => $submission->feedback,
            'graded_at' => $submission->graded_at,
            'submitted_at' => $submission->updated_at,
        ];
    }

    /** Format a graded submission response. */
    public function formatGradeResponse(AssignmentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'assignment_id' => $submission->assignment_id,
            'student_user_id' => $submission->student_user_id,
            'total_marks' => $submission->assignment->total_marks,
            'obtained_marks' => $submission->obtained_marks,
            'feedback' => $submission->feedback,
            'graded_at' => $submission->graded_at,
        ];
    }

    /**
     * @param  LengthAwarePaginator<mixed>  $paginator
     * @return array<string, int>
     */
    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
