<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ClassRecording;
use App\Models\Teacher;
use Illuminate\Http\Request;

class ClassRecordingController extends Controller
{
    public function index(Request $request, $batch_id)
    {
        $user = auth('api')->user();

        $query = ClassRecording::with('batch:id,name,teacher_id');

        $query->where('batch_id', $batch_id);

        $teacher = Teacher::where('user_id', $user->id)->first();

        if ($teacher) {
            $query->whereHas('batch', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            });
        } else {
            $query->whereHas('batch.enrollments', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $perPage = $request->get('per_page', 10);

        $recordings = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Recordings retrieved successfully',
            'data' => $recordings->items(),
            'pagination' => [
                'current_page' => $recordings->currentPage(),
                'per_page'     => $recordings->perPage(),
                'total'        => $recordings->total(),
                'last_page'    => $recordings->lastPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();

        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'class_date' => 'required|date',
            'recording_url' => 'required|url',
        ]);

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher'
            ], 403);
        }

        $batch = Batch::findOrFail($request->batch_id);

        if ($batch->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not assigned to this batch'
            ], 403);
        }

        $recording = ClassRecording::create([
            'batch_id' => $request->batch_id,
            'class_date' => $request->class_date,
            'recording_url' => $request->recording_url,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recording created successfully',
            'data' => $recording
        ]);
    }

    public function show($id)
    {
        $user = auth('api')->user();

        $recording = ClassRecording::with('batch:id,name,teacher_id')->findOrFail($id);

        $teacher = Teacher::where('user_id', $user->id)->first();

        if ($teacher) {
            if ($recording->batch->teacher_id !== $teacher->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to view this recording'
                ], 403);
            }
        } else {
            $enrolled = $recording->batch->enrollments()->where('user_id', $user->id)->exists();

            if (!$enrolled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not enrolled in this batch'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Recording fetched successfully',
            'data' => $recording
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth('api')->user();

        $recording = ClassRecording::findOrFail($id);

        $request->validate([
            'batch_id' => 'sometimes|exists:batches,id',
            'class_date' => 'sometimes|date',
            'recording_url' => 'sometimes|url',
        ]);

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher'
            ], 403);
        }

        $batchId = $request->batch_id ?? $recording->batch_id;

        $batch = Batch::findOrFail($batchId);

        if ($batch->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not assigned to this batch'
            ], 403);
        }

        $recording->update([
            'batch_id' => $batchId,
            'class_date' => $request->class_date ?? $recording->class_date,
            'recording_url' => $request->recording_url ?? $recording->recording_url,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recording updated successfully',
            'data' => $recording
        ]);
    }

    public function destroy($id)
    {
        $user = auth('api')->user();

        $recording = ClassRecording::with('batch:id,teacher_id')->findOrFail($id);

        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher'
            ], 403);
        }

        if ($recording->batch->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You cannot delete this recording'
            ], 403);
        }

        $recording->delete();

        return response()->json([
            'success' => true,
            'message' => 'Recording deleted successfully'
        ]);
    }
}
