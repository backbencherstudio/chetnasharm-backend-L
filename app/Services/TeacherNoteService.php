<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\TeacherNote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class TeacherNoteService
{
    private const FILE_DIRECTORY = 'teacher-notes';

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

    /**
     * @return array{items: Collection<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function index(int $batchId, Request $request): array
    {
        $notes = TeacherNote::where('batch_id', $batchId)
            ->with('batch:id,name,teacher_id')
            ->latest()
            ->paginate(Pagination::perPage($request));

        return [
            'items' => collect($notes->items())->map(fn ($note) => $this->formatListItem($note)),
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ],
        ];
    }

    public function store(User $user, Batch $batch, array $validated, ?UploadedFile $noteFile): TeacherNote
    {
        $filePath = null;

        if ($noteFile) {
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

        if ($noteFile) {
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

    /**
     * @return array{items: Collection<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function forStudent(int $batchId, Request $request): array
    {
        $perPage = Pagination::perPage($request);

        $notes = TeacherNote::with('batch:id,name')
            ->where('batch_id', $batchId)
            ->latest()
            ->paginate($perPage);

        return [
            'items' => collect($notes->items())->map(fn ($note) => $this->formatStudentListItem($note)),
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function formatListItem(TeacherNote $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'batch_id' => $note->batch_id,
            'note' => $note->note,
            'note_link' => $note->note_link,
            'note_file' => $note->note_file
                ? asset('storage/'.$note->note_file)
                : null,
            'created_at' => $note->created_at,
            'batch' => $note->batch,
        ];
    }

    /** @return array<string, mixed> */
    public function formatStudentListItem(TeacherNote $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'batch_id' => $note->batch_id,
            'note' => $note->note,
            'note_link' => $note->note_link,
            'note_file' => $note->note_file
                ? asset('storage/'.$note->note_file)
                : null,
            'created_at' => $note->created_at,
            'batch' => $note->batch,
        ];
    }

    /** @return array<string, mixed> */
    public function formatCreatedNote(TeacherNote $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'batch_id' => $note->batch_id,
            'note' => $note->note,
            'note_link' => $note->note_link,
            'note_file' => $note->note_file
                ? asset('storage/'.$note->note_file)
                : null,
            'created_at' => $note->created_at,
        ];
    }

    /** @return array<string, mixed> */
    public function formatShowNote(TeacherNote $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'user_id' => $note->user_id,
            'batch_id' => $note->batch_id,
            'note' => $note->note,
            'note_link' => $note->note_link,
            'note_file' => $note->note_file
                ? asset('storage/'.$note->note_file)
                : null,
            'created_at' => $note->created_at,
            'batch' => $note->batch,
        ];
    }

    /** @return array<string, mixed> */
    public function formatUpdatedNote(TeacherNote $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'note' => $note->note,
            'note_link' => $note->note_link,
            'note_file' => $note->note_file
                ? asset('storage/'.$note->note_file)
                : null,
            'updated_at' => $note->updated_at,
        ];
    }
}
