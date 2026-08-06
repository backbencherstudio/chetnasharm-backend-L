<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherNote\StoreTeacherNoteRequest;
use App\Http\Requests\TeacherNote\UpdateTeacherNoteRequest;
use App\Models\Batch;
use App\Services\TeacherNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherNoteController extends Controller
{
    public function __construct(private TeacherNoteService $notes) {}

    /** List teacher notes for a batch. */
    public function index(Request $request, int $batch_id): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->notes->findTeacherForUser($user);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $batch = $this->notes->teacherBatch($teacher->id, $batch_id);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access',
            ], 403);
        }

        $result = $this->notes->index($batch->id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Create a teacher note for a batch. */
    public function store(StoreTeacherNoteRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->notes->findTeacherForUser($user);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $validated = $request->validated();
        $batch = Batch::findOrFail($validated['batch_id']);

        if ($batch->teacher_id != $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not assigned to this batch',
            ], 403);
        }

        $note = $this->notes->store($user, $batch, $validated, $request->file('note_file'));

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully',
            'data' => $this->notes->formatCreatedNote($note),
        ]);
    }

    /** Show a single teacher note. */
    public function show(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $note = $this->notes->findWithBatch($id);

        $teacher = $this->notes->findTeacherForUser($user);

        if ($teacher) {

            if ($note->batch->teacher_id != $teacher->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

        } else {

            if (! $this->notes->isStudentEnrolled($user, $note->batch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Note retrieved successfully',
            'data' => $this->notes->formatShowNote($note),
        ]);
    }

    /** Update a teacher note. */
    public function update(UpdateTeacherNoteRequest $request, int $id): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->notes->findTeacherForUser($user);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $note = $this->notes->findWithFullBatch($id);

        if ($note->batch->teacher_id != $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $note = $this->notes->update($note, $request->validated(), $request->file('note_file'));

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully',
            'data' => $this->notes->formatUpdatedNote($note),
        ]);
    }

    /** Delete a teacher note. */
    public function destroy(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->notes->findTeacherForUser($user);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $note = $this->notes->findWithFullBatch($id);

        if ($note->batch->teacher_id != $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->notes->destroy($note);

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully',
        ]);
    }

    /** List teacher notes for an enrolled student in a batch. */
    public function forStudent(Request $request, int $batch_id): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->notes->isStudentEnrolled($user, $batch_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $result = $this->notes->forStudent($batch_id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }
}
