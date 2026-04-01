<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Batch;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search  = $request->search;

        $batches = Batch::with([
            'class:id,title',
            'teacher:id,name'
        ])
        ->when($search, function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhereHas('class', function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
                })
                ->orWhereHas('teacher', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
        })
        ->latest()
        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Batches retrieved successfully',
            'data' => $batches
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'teacher_id' => 'required|exists:teachers,id',
            'name' => 'required|string|max:255',
            'total_seat' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'zoom_link' => 'nullable|url',
            'status' => 'nullable|in:upcoming,ongoing,completed',
        ]);

        $batch = Batch::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Batch created successfully',
            'data' => $batch
        ], 201);
    }

    public function edit($id)
    {
        $batch = Batch::with([
            'class:id,title',
            'teacher:id,name'])->find($id);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Batch retrieved successfully',
            'data' => $batch
        ]);
    }

    public function update(Request $request, $id)
    {
        $batch = Batch::find($id);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found'
            ], 404);
        }

        $validated = $request->validate([
            'class_id' => 'sometimes|exists:classes,id',
            'teacher_id' => 'sometimes|exists:teachers,id',
            'name' => 'sometimes|string|max:255',
            'total_seat' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'zoom_link' => 'nullable|url',
            'status' => 'nullable|in:upcoming,ongoing,completed',
        ]);

        $batch->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Batch updated successfully',
            'data' => $batch
        ]);
    }

    public function destroy($id)
    {
        $batch = Batch::find($id);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found'
            ], 404);
        }

        $batch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Batch deleted successfully'
        ]);
    }
}
