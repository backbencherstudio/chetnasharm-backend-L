<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\StudentActivityNote;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TeacherStudentController extends Controller
{
    /**
     * List all students from the teacher's running batches.
     */
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $search = $request->query('search');
        $perPage = Pagination::perPage($request);
        $runningBatchIds = $this->runningBatchIds($teacher->id);

        if ($runningBatchIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Students fetched successfully',
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

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

        $studentUserIds = collect($enrollments->items())->pluck('user_id')->filter()->unique()->values();

        $latestNoteIds = StudentActivityNote::query()
            ->selectRaw('MAX(id) as id')
            ->where('teacher_id', $teacher->id)
            ->whereIn('batch_id', $runningBatchIds)
            ->whereIn('student_user_id', $studentUserIds)
            ->groupBy('batch_id', 'student_user_id')
            ->pluck('id');

        $latestNotes = StudentActivityNote::query()
            ->whereIn('id', $latestNoteIds)
            ->get()
            ->keyBy(fn (StudentActivityNote $note) => $note->batch_id.'-'.$note->student_user_id);

        $data = collect($enrollments->items())->map(function (Enrollment $enrollment) use ($latestNotes) {
            $user = $enrollment->user;
            $noteKey = $enrollment->batch_id.'-'.$enrollment->user_id;
            $latestNote = $latestNotes->get($noteKey);

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
                'latest_note' => $latestNote ? [
                    'id' => $latestNote->id,
                    'status' => $latestNote->status,
                    'comment' => $latestNote->comment,
                    'created_at' => $latestNote->created_at,
                ] : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Students fetched successfully',
            'data' => $data,
            'pagination' => [
                'current_page' => $enrollments->currentPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
                'last_page' => $enrollments->lastPage(),
            ],
        ]);
    }

    /**
     * List activity notes for a student in a batch.
     */
    public function notes(Request $request, int $userId): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $validated = $request->validate([
            'batch_id' => 'required|exists:batches,id',
        ]);

        $batch = $this->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access',
            ], 403);
        }

        if (! $this->studentEnrolledInBatch($userId, $batch->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Student is not enrolled in this batch',
            ], 422);
        }

        $notes = StudentActivityNote::query()
            ->where('teacher_id', $teacher->id)
            ->where('batch_id', $batch->id)
            ->where('student_user_id', $userId)
            ->latest()
            ->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Student notes fetched successfully',
            'data' => collect($notes->items())->map(fn (StudentActivityNote $note) => [
                'id' => $note->id,
                'batch_id' => $note->batch_id,
                'student_user_id' => $note->student_user_id,
                'comment' => $note->comment,
                'status' => $note->status,
                'created_at' => $note->created_at,
                'updated_at' => $note->updated_at,
            ])->values(),
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ],
        ]);
    }

    /**
     * Create a student activity note.
     */
    public function storeNote(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $validated = $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'student_user_id' => 'required|exists:users,id',
            'comment' => 'required|string',
            'status' => 'required|in:'.implode(',', StudentActivityNote::STATUSES),
        ]);

        $batch = $this->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access',
            ], 403);
        }

        if (! $this->studentEnrolledInBatch((int) $validated['student_user_id'], $batch->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Student is not enrolled in this batch',
            ], 422);
        }

        $note = StudentActivityNote::create([
            'teacher_id' => $teacher->id,
            'batch_id' => $batch->id,
            'student_user_id' => $validated['student_user_id'],
            'comment' => $validated['comment'],
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Student note created successfully',
            'data' => [
                'id' => $note->id,
                'batch_id' => $note->batch_id,
                'student_user_id' => $note->student_user_id,
                'comment' => $note->comment,
                'status' => $note->status,
                'created_at' => $note->created_at,
            ],
        ], 201);
    }

    /**
     * Update a student activity note.
     */
    public function updateNote(Request $request, int $id): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $note = StudentActivityNote::query()
            ->where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (! $note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found',
            ], 404);
        }

        $validated = $request->validate([
            'comment' => 'required|string',
            'status' => 'required|in:'.implode(',', StudentActivityNote::STATUSES),
        ]);

        $note->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Student note updated successfully',
            'data' => [
                'id' => $note->id,
                'batch_id' => $note->batch_id,
                'student_user_id' => $note->student_user_id,
                'comment' => $note->comment,
                'status' => $note->status,
                'updated_at' => $note->updated_at,
            ],
        ]);
    }

    /**
     * Delete a student activity note.
     */
    public function destroyNote(int $id): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $note = StudentActivityNote::query()
            ->where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (! $note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found',
            ], 404);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Student note deleted successfully',
        ]);
    }

    /**
     * List activity notes for the authenticated student.
     */
    public function forStudent(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $notes = StudentActivityNote::query()
            ->where('student_user_id', $user->id)
            ->with([
                'batch:id,name',
                'teacher:id,user_id',
                'teacher.user:id,name',
            ])
            ->latest()
            ->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Activity notes fetched successfully',
            'data' => collect($notes->items())->map(fn (StudentActivityNote $note) => [
                'id' => $note->id,
                'batch_id' => $note->batch_id,
                'batch_name' => $note->batch?->name,
                'teacher_id' => $note->teacher_id,
                'teacher_name' => $note->teacher?->name,
                'comment' => $note->comment,
                'status' => $note->status,
                'created_at' => $note->created_at,
                'updated_at' => $note->updated_at,
            ])->values(),
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ],
        ]);
    }

    private function currentTeacher(): ?Teacher
    {
        $user = auth('api')->user();

        if (! $user) {
            return null;
        }

        $user->loadMissing('teacher:id,user_id');

        return $user->teacher;
    }

    /**
     * @return Collection<int, int>
     */
    private function runningBatchIds(int $teacherId)
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

    private function teacherBatch(int $teacherId, int $batchId): ?Batch
    {
        return Batch::query()
            ->where('id', $batchId)
            ->where('teacher_id', $teacherId)
            ->first();
    }

    private function studentEnrolledInBatch(int $studentUserId, int $batchId): bool
    {
        return Enrollment::query()
            ->where('user_id', $studentUserId)
            ->where('batch_id', $batchId)
            ->where('status', 'active')
            ->exists();
    }
}
