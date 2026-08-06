<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherStudent\ListStudentNotesRequest;
use App\Http\Requests\TeacherStudent\StoreStudentActivityNoteRequest;
use App\Http\Requests\TeacherStudent\UpdateStudentActivityNoteRequest;
use App\Services\TeacherStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherStudentController extends Controller
{
    public function __construct(private TeacherStudentService $teacherStudents) {}

    /** List all students from the teacher's running batches. */
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $result = $this->teacherStudents->index($teacher, $request);

        return response()->json([
            'success' => true,
            'message' => 'Students fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** List activity notes for a student in a batch. */
    public function notes(ListStudentNotesRequest $request, int $userId): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $validated = $request->validated();

        $batch = $this->teacherStudents->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access',
            ], 403);
        }

        if (! $this->teacherStudents->studentEnrolledInBatch($userId, $batch->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Student is not enrolled in this batch',
            ], 422);
        }

        $result = $this->teacherStudents->notes($teacher, $userId, $batch->id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Student notes fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Create a student activity note. */
    public function storeNote(StoreStudentActivityNoteRequest $request): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $validated = $request->validated();

        $batch = $this->teacherStudents->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access',
            ], 403);
        }

        if (! $this->teacherStudents->studentEnrolledInBatch((int) $validated['student_user_id'], $batch->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Student is not enrolled in this batch',
            ], 422);
        }

        $note = $this->teacherStudents->storeNote($teacher, $batch, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Student note created successfully',
            'data' => $this->teacherStudents->formatCreatedNote($note),
        ], 201);
    }

    /** Update a student activity note. */
    public function updateNote(UpdateStudentActivityNoteRequest $request, int $id): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $note = $this->teacherStudents->findNoteForTeacher($teacher->id, $id);

        if (! $note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found',
            ], 404);
        }

        $note = $this->teacherStudents->updateNote($note, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Student note updated successfully',
            'data' => $this->teacherStudents->formatUpdatedNote($note),
        ]);
    }

    /** Delete a student activity note. */
    public function destroyNote(int $id): JsonResponse
    {
        $teacher = $this->teacherStudents->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $note = $this->teacherStudents->findNoteForTeacher($teacher->id, $id);

        if (! $note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found',
            ], 404);
        }

        $this->teacherStudents->destroyNote($note);

        return response()->json([
            'success' => true,
            'message' => 'Student note deleted successfully',
        ]);
    }

    /** List activity notes for the authenticated student. */
    public function forStudent(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->teacherStudents->forStudent($user, $request);

        return response()->json([
            'success' => true,
            'message' => 'Activity notes fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }
}
