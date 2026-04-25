<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use App\Models\ClassModel;
use Illuminate\Support\Facades\Storage;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search  = $request->search;

        $classes = ClassModel::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Classes retrieved successfully',
            'data' => $classes->items(),
            'pagination' => [
                'current_page' => $classes->currentPage(),
                'per_page'     => $classes->perPage(),
                'total'        => $classes->total(),
                'last_page'    => $classes->lastPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_in_days' => 'required|integer|min:1',
            'total_classes' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('classes', 'public');
        }

        $class = ClassModel::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Class created successfully',
            'data' => $class
        ], 201);
    }

    public function edit($id)
    {
        $class = ClassModel::find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Class retrieved successfully',
            'data' => $class
        ]);
    }

    public function update(Request $request, $id)
    {
        $class = ClassModel::find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found'
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'duration_in_days' => 'sometimes|integer|min:1',
            'total_classes' => 'sometimes|integer|min:1',
            'is_active' => 'nullable|in:0,1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {

            if ($class->image && Storage::disk('public')->exists($class->image)) {
                Storage::disk('public')->delete($class->image);
            }

            $validated['image'] = $request->file('image')->store('classes', 'public');
        }

        $class->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Class updated successfully',
            'data' => $class
        ]);
    }

    public function status($id)
    {
        $class = ClassModel::find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found'
            ], 404);
        }
        $class->is_active = $class->is_active == 1 ? 0 : 1;
        $class->save();

        return response()->json([
            'success' => true,
            'message' => 'Class status updated successfully',
            'status' => $class->is_active
        ]);
    }

    public function landClass(Request $request)
    {
        $query = ClassModel::where('is_active', 1);

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $classes = $query
            ->select(
                'id',
                'title',
                'description',
                'price',
                'duration_in_days',
                'total_classes',
                'image'
            )
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Classes fetched successfully',
            'data' => $classes
        ]);
    }

    public function landBatch(Request $request, $classId)
    {
        $batches = Batch::where('class_id', $classId)
            ->where('active_status', 1)
            ->select(
                'id',
                'class_id',
                'teacher_id',
                'name',
                'total_seat',
                'filled_seat',
                'start_date',
                'end_date'
            )
            ->with([
                'teacher:id,name,image,intro_video',
                'class:id,title,image',
                'schedules:id,batch_id,day_of_week,start_time,end_time'
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Batches fetched successfully',
            'data' => $batches
        ]);
    }
}
