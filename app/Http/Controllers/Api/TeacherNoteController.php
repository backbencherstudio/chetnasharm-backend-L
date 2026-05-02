<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\TeacherNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeacherNoteController extends Controller
{
    public function index(Request $request, $batch_id)
    {
        $user = auth('api')->user();

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher'
            ], 403);
        }

        $batch = Batch::where('id', $batch_id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access'
            ], 403);
        }

        $notes = TeacherNote::where('batch_id', $batch->id)
            ->with('batch:id,name,teacher_id')
            ->latest()
            ->paginate($request->get('per_page', 10));

        $formattedNotes = collect($notes->items())->map(function ($note) {
            return [
                'id' => $note->id,
                'title' => $note->title,
                'batch_id' => $note->batch_id,

                'note' => $note->note,
                'note_link' => $note->note_link,

                'note_file' => $note->note_file
                    ? asset('storage/' . $note->note_file)
                    : null,

                'created_at' => $note->created_at,
                'batch' => $note->batch,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved successfully',
            'data' => $formattedNotes,
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();

        $request->validate([
            'title'     => 'required|string',
            'batch_id'  => 'required|exists:batches,id',
            'note'      => 'nullable|string',
            'note_link' => 'nullable|url',
            'note_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if (
            !$request->filled('note') &&
            !$request->filled('note_link') &&
            !$request->hasFile('note_file')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide note, file, or link'
            ], 422);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher'
            ], 403);
        }

        $batch = Batch::findOrFail($request->batch_id);

        if ($batch->teacher_id != $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not assigned to this batch'
            ], 403);
        }

        $filePath = null;

        if ($request->hasFile('note_file')) {
            $filePath = $request->file('note_file')
                ->store('teacher-notes', 'public');
        }

        $note = TeacherNote::create([
            'title' => $request->title,
            'user_id' => $user->id,
            'batch_id' => $request->batch_id,
            'note' => $request->note,
            'note_link' => $request->note_link,
            'note_file' => $filePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully',
            'data' => [
                'id' => $note->id,
                'title' => $note->title,
                'batch_id' => $note->batch_id,
                'note' => $note->note,
                'note_link' => $note->note_link,
                'note_file' => $note->note_file
                    ? asset('storage/' . $note->note_file)
                    : null,
                'created_at' => $note->created_at,
            ]
        ]);
    }


    public function show($id)
    {
        $user = auth('api')->user();

        $note = TeacherNote::with('batch:id,name,teacher_id')
            ->findOrFail($id);

        $teacher = Teacher::where('user_id', $user->id)->first();

        if ($teacher) {

            if ($note->batch->teacher_id != $teacher->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

        } else {

            $isEnrolled = $note->batch
                ->enrollments()
                ->where('user_id', $user->id)
                ->exists();

            if (!$isEnrolled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Note retrieved successfully',
            'data' => [
                'id' => $note->id,
                'title' => $note->title,
                'user_id' => $note->user_id,
                'batch_id' => $note->batch_id,

                'note' => $note->note,

                'note_link' => $note->note_link,

                'note_file' => $note->note_file
                    ? asset('storage/' . $note->note_file)
                    : null,

                'created_at' => $note->created_at,

                'batch' => $note->batch
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth('api')->user();

        $request->validate([
            'title'     => 'required|string',
            'note'      => 'nullable|string',
            'note_link' => 'nullable|url',
            'note_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if (
            !$request->filled('note') &&
            !$request->filled('note_link') &&
            !$request->hasFile('note_file')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide note, file, or link'
            ], 422);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $note = TeacherNote::with('batch')
            ->findOrFail($id);

        if ($note->batch->teacher_id != $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $filePath = $note->note_file;

        if ($request->hasFile('note_file')) {

            if ($note->note_file && Storage::disk('public')->exists($note->note_file)) {
                Storage::disk('public')->delete($note->note_file);
            }

            $filePath = $request->file('note_file')
                ->store('teacher-notes', 'public');
        }

        $note->update([
            'title' => $note->title,
            'note' => $request->note,
            'note_link' => $request->note_link,
            'note_file' => $filePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully',
            'data' => [
                'id' => $note->id,
                'title' => $note->title,
                'note' => $note->note,
                'note_link' => $note->note_link,
                'note_file' => $note->note_file
                    ? asset('storage/' . $note->note_file)
                    : null,
                'updated_at' => $note->updated_at,
            ]
        ]);
    }

    public function destroy($id)
    {
        $user = auth('api')->user();

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $note = TeacherNote::with('batch')
            ->findOrFail($id);

        if ($note->batch->teacher_id != $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if (
            $note->note_file &&
            Storage::disk('public')->exists($note->note_file)
        ) {
            Storage::disk('public')->delete($note->note_file);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully'
        ]);
    }

    public function forStudent(Request $request, $batch_id)
    {
        $user = auth('api')->user();

        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('batch_id', $batch_id)
            ->exists();

        if (!$isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $perPage = $request->get('per_page', 10);

        $notes = TeacherNote::with('batch:id,name')
            ->where('batch_id', $batch_id)
            ->latest()
            ->paginate($perPage);

        $formattedNotes = collect($notes->items())->map(function ($note) {

            return [
                'id' => $note->id,
                'title' => $note->title,
                'batch_id' => $note->batch_id,

                'note' => $note->note,

                'note_link' => $note->note_link,

                'note_file' => $note->note_file
                    ? asset('storage/' . $note->note_file)
                    : null,

                'created_at' => $note->created_at,

                'batch' => $note->batch
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved successfully',
            'data' => $formattedNotes,
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ]
        ]);
    }

}
