<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\ClassRecording;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;

class ClassRecordingService
{
    public function canAccessBatch(User $user, int $batchId): bool
    {
        $user->loadMissing('teacher:id,user_id');
        $teacher = $user->teacher;

        return $teacher
            ? Batch::where('id', $batchId)->where('teacher_id', $teacher->id)->exists()
            : Enrollment::where('batch_id', $batchId)->where('user_id', $user->id)->exists();
    }

    public function findTeacherForUser(User $user): ?Teacher
    {
        return Teacher::where('user_id', $user->id)->first();
    }

    /**
     * @return array{items: array<int, ClassRecording>, pagination: array<string, int>, accessible: bool}
     */
    public function index(User $user, int $batchId, Request $request): array
    {
        $perPage = Pagination::perPage($request);

        if (! $this->canAccessBatch($user, $batchId)) {
            return [
                'accessible' => false,
                'items' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ];
        }

        $recordings = ClassRecording::with('batch:id,name,teacher_id')
            ->where('batch_id', $batchId)
            ->latest()
            ->paginate($perPage);

        return [
            'accessible' => true,
            'items' => $recordings->items(),
            'pagination' => [
                'current_page' => $recordings->currentPage(),
                'per_page' => $recordings->perPage(),
                'total' => $recordings->total(),
                'last_page' => $recordings->lastPage(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(array $validated): ClassRecording
    {
        return ClassRecording::create([
            'batch_id' => $validated['batch_id'],
            'class_date' => $validated['class_date'],
            'recording_url' => $validated['recording_url'],
        ]);
    }

    public function findWithBatch(int $id): ClassRecording
    {
        return ClassRecording::with('batch:id,name,teacher_id')->findOrFail($id);
    }

    public function find(int $id): ClassRecording
    {
        return ClassRecording::findOrFail($id);
    }

    public function isStudentEnrolled(User $user, int $batchId): bool
    {
        return Enrollment::where('user_id', $user->id)
            ->where('batch_id', $batchId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(ClassRecording $recording, array $validated, int $batchId): ClassRecording
    {
        $recording->update([
            'batch_id' => $batchId,
            'class_date' => $validated['class_date'] ?? $recording->class_date,
            'recording_url' => $validated['recording_url'] ?? $recording->recording_url,
        ]);

        return $recording;
    }

    public function destroy(ClassRecording $recording): void
    {
        $recording->delete();
    }

    /**
     * @return array{items: array<int, ClassRecording>, pagination: array<string, int>}
     */
    public function forStudent(int $batchId, Request $request): array
    {
        $perPage = Pagination::perPage($request);

        $recordings = ClassRecording::with('batch:id,name')
            ->where('batch_id', $batchId)
            ->latest()
            ->paginate($perPage);

        return [
            'items' => $recordings->items(),
            'pagination' => [
                'current_page' => $recordings->currentPage(),
                'per_page' => $recordings->perPage(),
                'total' => $recordings->total(),
                'last_page' => $recordings->lastPage(),
            ],
        ];
    }
}
