<?php

declare(strict_types=1);

namespace App\Services;

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\StudentActivityNote;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            ->where(function (Builder $query): void {
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

    public function index(Teacher $teacher, Request $request): ?LengthAwarePaginator
    {
        $perPage = Pagination::perPage($request);
        $runningBatchIds = $this->runningBatchIds($teacher->id);

        if ($runningBatchIds->isEmpty()) {
            return null;
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
            $query->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function notes(Teacher $teacher, int $userId, int $batchId, Request $request): LengthAwarePaginator
    {
        return StudentActivityNote::query()
            ->where('teacher_id', $teacher->id)
            ->where('batch_id', $batchId)
            ->where('student_user_id', $userId)
            ->with(['student:id,name', 'batch:id,name'])
            ->latest()
            ->paginate(Pagination::perPage($request));
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

    public function forStudent(User $user, Request $request): LengthAwarePaginator
    {
        return StudentActivityNote::query()
            ->where('student_user_id', $user->id)
            ->with([
                'batch:id,name',
                'teacher:id,user_id',
                'teacher.user:id,name',
            ])
            ->latest()
            ->paginate(Pagination::perPage($request));
    }
}
