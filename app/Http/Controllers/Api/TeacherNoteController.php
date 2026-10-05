<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherNote\StoreTeacherNoteRequest;
use App\Http\Requests\TeacherNote\UpdateTeacherNoteRequest;
use App\Http\Resources\TeacherNoteResource;
use App\Models\Batch;
use App\Models\Teacher;
use App\Services\TeacherNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherNoteController extends Controller
{
    public function __construct(private readonly TeacherNoteService $notes) {}

    /** List teacher notes for a batch. */
    public function index(Request $request, int $batch_id): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->notes->findTeacherForUser($user);

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $batch = $this->notes->teacherBatch($teacher->id, $batch_id);

        if (! $batch instanceof Batch) {
            return $this->forbidden('Unauthorized: Invalid batch access');
        }

        $notes = $this->notes->index($batch->id, $request);

        return $this->paginate(
            $notes,
            TeacherNoteResource::collection($notes->items()),
            'Notes retrieved successfully'
        );
    }

    /** Create a teacher note for a batch. */
    public function store(StoreTeacherNoteRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->notes->findTeacherForUser($user);

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        $validated = $request->validated();
        $batch = Batch::findOrFail($validated['batch_id']);

        if ($batch->teacher_id != $teacher->id) {
            return $this->forbidden('Unauthorized: You are not assigned to this batch');
        }

        $note = $this->notes->store($user, $batch, $validated, $request->file('note_file'));

        return $this->created(
            new TeacherNoteResource($note),
            'Note created successfully'
        );
    }

    /** Show a single teacher note. */
    public function show(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $note = $this->notes->findWithBatch($id);

        $teacher = $this->notes->findTeacherForUser($user);

        if ($teacher instanceof Teacher) {
            if ($note->batch->teacher_id != $teacher->id) {
                return $this->forbidden('Unauthorized');
            }
        } else {
            if (! $this->notes->isStudentEnrolled($user, $note->batch_id)) {
                return $this->forbidden('Unauthorized');
            }
        }

        return $this->success(
            new TeacherNoteResource($note),
            'Note retrieved successfully'
        );
    }

    /** Update a teacher note. */
    public function update(UpdateTeacherNoteRequest $request, int $id): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->notes->findTeacherForUser($user);

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized');
        }

        $note = $this->notes->findWithFullBatch($id);

        if ($note->batch->teacher_id != $teacher->id) {
            return $this->forbidden('Unauthorized');
        }

        $note = $this->notes->update($note, $request->validated(), $request->file('note_file'));

        return $this->success(
            new TeacherNoteResource($note),
            'Note updated successfully'
        );
    }

    /** Delete a teacher note. */
    public function destroy(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $teacher = $this->notes->findTeacherForUser($user);

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized');
        }

        $note = $this->notes->findWithFullBatch($id);

        if ($note->batch->teacher_id != $teacher->id) {
            return $this->forbidden('Unauthorized');
        }

        $this->notes->destroy($note);

        return $this->success(message: 'Note deleted successfully');
    }

    /** List teacher notes for an enrolled student in a batch. */
    public function forStudent(Request $request, int $batch_id): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->notes->isStudentEnrolled($user, $batch_id)) {
            return $this->forbidden('Unauthorized');
        }

        $notes = $this->notes->forStudent($batch_id, $request);

        return $this->paginate(
            $notes,
            TeacherNoteResource::collection($notes->items()),
            'Notes retrieved successfully'
        );
    }
}
