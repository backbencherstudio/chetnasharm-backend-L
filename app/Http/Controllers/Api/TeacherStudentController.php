<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherStudent\ListStudentNotesRequest;
use App\Http\Requests\TeacherStudent\StoreStudentActivityNoteRequest;
use App\Http\Requests\TeacherStudent\UpdateStudentActivityNoteRequest;
use App\Http\Resources\BatchStudentResource;
use App\Http\Resources\StudentActivityNoteResource;
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

        $enrollments = $this->teacherStudents->index($teacher, $request);

        if (! $enrollments) {
            return $this->paginated(
                [],
                Pagination::empty(Pagination::perPage($request))['pagination'],
                'Students fetched successfully'
            );
        }

        return $this->paginate(
            $enrollments,
            BatchStudentResource::collection($enrollments->items()),
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

        $notes = $this->teacherStudents->notes($teacher, $userId, $batch->id, $request);

        return $this->paginate(
            $notes,
            StudentActivityNoteResource::collection($notes->items()),
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
            new StudentActivityNoteResource($note),
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
            new StudentActivityNoteResource($note),
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
        $notes = $this->teacherStudents->forStudent($user, $request);

        return $this->paginate(
            $notes,
            StudentActivityNoteResource::collection($notes->items()),
            'Activity notes fetched successfully'
        );
    }
}
