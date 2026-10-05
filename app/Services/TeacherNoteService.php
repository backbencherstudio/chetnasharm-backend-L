<?php

declare(strict_types=1);

namespace App\Services;

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\TeacherNote;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TeacherNoteService
{
    private const string FILE_DIRECTORY = 'teacher-notes';

    public function findTeacherForUser(User $user): ?Teacher
    {
        return Teacher::where('user_id', $user->id)->first();
    }

    public function teacherBatch(int $teacherId, int $batchId): ?Batch
    {
        return Batch::where('id', $batchId)
            ->where('teacher_id', $teacherId)
            ->first();
    }

    public function index(int $batchId, Request $request): LengthAwarePaginator
    {
        return TeacherNote::where('batch_id', $batchId)
            ->with('batch:id,name,teacher_id')
            ->latest()
            ->paginate(Pagination::perPage($request));
    }

    public function store(User $user, Batch $batch, array $validated, ?UploadedFile $noteFile): TeacherNote
    {
        $filePath = null;

        if ($noteFile instanceof UploadedFile) {
            $filePath = $noteFile->store(self::FILE_DIRECTORY, 'public');
        }

        return TeacherNote::create([
            'title' => $validated['title'],
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'note' => $validated['note'] ?? null,
            'note_link' => $validated['note_link'] ?? null,
            'note_file' => $filePath,
        ]);
    }

    public function findWithBatch(int $id): TeacherNote
    {
        return TeacherNote::with('batch:id,name,teacher_id')->findOrFail($id);
    }

    public function findWithFullBatch(int $id): TeacherNote
    {
        return TeacherNote::with('batch')->findOrFail($id);
    }

    public function isStudentEnrolled(User $user, int $batchId): bool
    {
        return Enrollment::where('user_id', $user->id)
            ->where('batch_id', $batchId)
            ->exists();
    }

    public function update(TeacherNote $note, array $validated, ?UploadedFile $noteFile): TeacherNote
    {
        $filePath = $note->note_file;

        if ($noteFile instanceof UploadedFile) {
            if ($note->note_file && Storage::disk('public')->exists($note->note_file)) {
                Storage::disk('public')->delete($note->note_file);
            }

            $filePath = $noteFile->store(self::FILE_DIRECTORY, 'public');
        }

        $note->update([
            'title' => $validated['title'],
            'note' => $validated['note'] ?? null,
            'note_link' => $validated['note_link'] ?? null,
            'note_file' => $filePath,
        ]);

        return $note;
    }

    public function destroy(TeacherNote $note): void
    {
        if (
            $note->note_file &&
            Storage::disk('public')->exists($note->note_file)
        ) {
            Storage::disk('public')->delete($note->note_file);
        }

        $note->delete();
    }

    public function forStudent(int $batchId, Request $request): LengthAwarePaginator
    {
        return TeacherNote::with('batch:id,name')
            ->where('batch_id', $batchId)
            ->latest()
            ->paginate(Pagination::perPage($request));
    }
}
