<?php

declare(strict_types=1);

namespace App\Services;

use App\Common\Pagination;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\BatchAssignment;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BatchAssignmentService
{
    private const string FILE_DIRECTORY = 'assignments';

    private const string SUBMISSION_DIRECTORY = 'assignment-submissions';

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

    public function indexForTeacher(int $batchId, Request $request): LengthAwarePaginator
    {
        return BatchAssignment::query()
            ->where('batch_id', $batchId)
            ->withCount('submissions')
            ->latest()
            ->paginate(Pagination::perPage($request));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(Teacher $teacher, Batch $batch, array $validated, ?UploadedFile $attachment): BatchAssignment
    {
        $attachmentPath = null;

        if ($attachment instanceof UploadedFile) {
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

        if ($attachment instanceof UploadedFile) {
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

    public function submissions(BatchAssignment $assignment, Request $request): LengthAwarePaginator
    {
        return AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->with(['student:id,name,email', 'assignment:id,total_marks'])
            ->latest()
            ->paginate(Pagination::perPage($request));
    }

    public function activeForStudent(User $user, Request $request): ?LengthAwarePaginator
    {
        $batchIds = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('batch', function (Builder $query): void {
                $query->where('active_status', 1)
                    ->where('status', '!=', 'completed');
            })
            ->pluck('batch_id');

        if ($batchIds->isEmpty()) {
            return null;
        }

        $search = $request->query('search');

        $query = BatchAssignment::query()
            ->started()
            ->whereIn('batch_id', $batchIds)
            ->with([
                'batch:id,name,class_id',
                'batch.class:id,title',
                'submissions' => function ($submissionQuery) use ($user): void {
                    $submissionQuery->where('student_user_id', $user->id);
                },
            ])
            ->latest('due_at')
            ->latest();

        if ($search) {
            $query->where(function ($assignmentQuery) use ($search): void {
                $assignmentQuery->where('title', 'like', "%{$search}%")
                    ->orWhereHas('batch', function ($batchQuery) use ($search): void {
                        $batchQuery->where('name', 'like', "%{$search}%")
                            ->orWhereHas('class', fn ($classQuery) => $classQuery->where('title', 'like', "%{$search}%"));
                    });
            });
        }

        if ($request->boolean('pending_only')) {
            $query->whereDoesntHave('submissions', function ($submissionQuery) use ($user): void {
                $submissionQuery->where('student_user_id', $user->id);
            });
        }

        return $query->paginate(Pagination::perPage($request));
    }

    public function forStudent(User $user, int $batchId, Request $request): LengthAwarePaginator
    {
        return BatchAssignment::query()
            ->started()
            ->where('batch_id', $batchId)
            ->with(['submissions' => function ($query) use ($user): void {
                $query->where('student_user_id', $user->id);
            }])
            ->latest()
            ->paginate(Pagination::perPage($request));
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
}
