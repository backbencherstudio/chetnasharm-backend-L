<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\StudentActivityNote;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TeacherStudentService
{
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

    /** Get IDs of the teacher's currently running batches. */
    public function runningBatchIds(int $teacherId): Collection
    {
        return Batch::query()
            ->where('teacher_id', $teacherId)
            ->where('active_status', 1)
            ->where('status', 'ongoing')
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now()->toDateString());
            })
            ->pluck('id');
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
    public function index(Teacher $teacher, Request $request): array
    {
        $perPage = Pagination::perPage($request);
        $runningBatchIds = $this->runningBatchIds($teacher->id);

        if ($runningBatchIds->isEmpty()) {
            return [
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ];
        }

        $search = $request->query('search');

        $query = Enrollment::query()
            ->whereIn('batch_id', $runningBatchIds)
            ->where('status', 'active')
            ->with([
                'user:id,name,email,image',
                'batch:id,name,class_id,teacher_id,status,active_status,end_date',
                'class:id,title',
            ])
            ->latest();

        if ($search) {
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $enrollments = $query->paginate($perPage);

        $items = collect($enrollments->items())->map(function (Enrollment $enrollment) {
            $user = $enrollment->user;

            return [
                'user_id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'image' => $user?->image,
                'image_url' => $user?->image_url,
                'batch_id' => $enrollment->batch_id,
                'batch_name' => $enrollment->batch?->name,
                'class_title' => $enrollment->class?->title,
                'enrollment_status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at,
            ];
        })->values()->all();

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $enrollments->currentPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
                'last_page' => $enrollments->lastPage(),
            ],
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function notes(Teacher $teacher, int $userId, int $batchId, Request $request): array
    {
        $notes = StudentActivityNote::query()
            ->where('teacher_id', $teacher->id)
            ->where('batch_id', $batchId)
            ->where('student_user_id', $userId)
            ->latest()
            ->paginate(Pagination::perPage($request));

        return [
            'items' => collect($notes->items())->map(fn (StudentActivityNote $note) => [
                'id' => $note->id,
                'batch_id' => $note->batch_id,
                'student_user_id' => $note->student_user_id,
                'comment' => $note->comment,
                'status' => $note->status,
                'created_at' => $note->created_at,
                'updated_at' => $note->updated_at,
            ])->values()->all(),
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function storeNote(Teacher $teacher, Batch $batch, array $validated): StudentActivityNote
    {
        return StudentActivityNote::create([
            'teacher_id' => $teacher->id,
            'batch_id' => $batch->id,
            'student_user_id' => $validated['student_user_id'],
            'comment' => $validated['comment'],
            'status' => $validated['status'],
        ]);
    }

    public function findNoteForTeacher(int $teacherId, int $id): ?StudentActivityNote
    {
        return StudentActivityNote::query()
            ->where('id', $id)
            ->where('teacher_id', $teacherId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateNote(StudentActivityNote $note, array $validated): StudentActivityNote
    {
        $note->update($validated);

        return $note;
    }

    public function destroyNote(StudentActivityNote $note): void
    {
        $note->delete();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function forStudent(User $user, Request $request): array
    {
        $notes = StudentActivityNote::query()
            ->where('student_user_id', $user->id)
            ->with([
                'batch:id,name',
                'teacher:id,user_id',
                'teacher.user:id,name',
            ])
            ->latest()
            ->paginate(Pagination::perPage($request));

        return [
            'items' => collect($notes->items())->map(fn (StudentActivityNote $note) => [
                'id' => $note->id,
                'batch_id' => $note->batch_id,
                'batch_name' => $note->batch?->name,
                'teacher_id' => $note->teacher_id,
                'teacher_name' => $note->teacher?->name,
                'comment' => $note->comment,
                'status' => $note->status,
                'created_at' => $note->created_at,
                'updated_at' => $note->updated_at,
            ])->values()->all(),
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ],
        ];
    }

    /** Format a created note for API response. */
    public function formatCreatedNote(StudentActivityNote $note): array
    {
        return [
            'id' => $note->id,
            'batch_id' => $note->batch_id,
            'student_user_id' => $note->student_user_id,
            'comment' => $note->comment,
            'status' => $note->status,
            'created_at' => $note->created_at,
        ];
    }

    /** Format an updated note for API response. */
    public function formatUpdatedNote(StudentActivityNote $note): array
    {
        return [
            'id' => $note->id,
            'batch_id' => $note->batch_id,
            'student_user_id' => $note->student_user_id,
            'comment' => $note->comment,
            'status' => $note->status,
            'updated_at' => $note->updated_at,
        ];
    }
}
