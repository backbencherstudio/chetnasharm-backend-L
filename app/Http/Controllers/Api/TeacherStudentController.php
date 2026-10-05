<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherStudent\ListStudentNotesRequest;
use App\Http\Requests\TeacherStudent\StoreStudentActivityNoteRequest;
use App\Http\Requests\TeacherStudent\UpdateStudentActivityNoteRequest;
use App\Models\Batch;
use App\Models\StudentActivityNote;
use App\Models\Teacher;
use App\Services\TeacherStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherStudentController extends Controller
{
    public function __construct(private readonly TeacherStudentService $teacherStudents) {}

    /** List all students from the teacher's running batches. */
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $result = $this->teacherStudents->index($teacher, $request);

        return $this->paginated(
            $result['items'],
            $result['pagination'],
            'Students fetched successfully'
        );
    }

    /** List activity notes for a student in a batch. */
    public function notes(ListStudentNotesRequest $request, int $userId): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $validated = $request->validated();

        $batch = $this->teacherStudents->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch instanceof Batch) {
            return $this->forbidden('Unauthorized: Invalid batch access');
        }

        if (! $this->teacherStudents->studentEnrolledInBatch($userId, $batch->id)) {
            return $this->error('Student is not enrolled in this batch', 422);
        }

        $result = $this->teacherStudents->notes($teacher, $userId, $batch->id, $request);

        return $this->paginated(
            $result['items'],
            $result['pagination'],
            'Student notes fetched successfully'
        );
    }

    /** Create a student activity note. */
    public function storeNote(StoreStudentActivityNoteRequest $request): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $validated = $request->validated();

        $batch = $this->teacherStudents->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch instanceof Batch) {
            return $this->forbidden('Unauthorized: Invalid batch access');
        }

        if (! $this->teacherStudents->studentEnrolledInBatch((int) $validated['student_user_id'], $batch->id)) {
            return $this->error('Student is not enrolled in this batch', 422);
        }

        $note = $this->teacherStudents->storeNote($teacher, $batch, $validated);

        return $this->created(
            $this->teacherStudents->formatCreatedNote($note),
            'Student note created successfully'
        );
    }

    /** Update a student activity note. */
    public function updateNote(UpdateStudentActivityNoteRequest $request, int $id): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $note = $this->teacherStudents->findNoteForTeacher($teacher->id, $id);

        if (! $note instanceof StudentActivityNote) {
            return $this->notFound('Note not found');
        }

        $note = $this->teacherStudents->updateNote($note, $request->validated());

        return $this->success(
            $this->teacherStudents->formatUpdatedNote($note),
            'Student note updated successfully'
        );
    }

    /** Delete a student activity note. */
    public function destroyNote(int $id): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $note = $this->teacherStudents->findNoteForTeacher($teacher->id, $id);

        if (! $note instanceof StudentActivityNote) {
            return $this->notFound('Note not found');
        }

        $this->teacherStudents->destroyNote($note);

        return $this->success(message: 'Student note deleted successfully');
    }

    /** List activity notes for the authenticated student. */
    public function forStudent(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->teacherStudents->forStudent($user, $request);

        return $this->paginated(
            $result['items'],
            $result['pagination'],
            'Activity notes fetched successfully'
        );
    }
}
