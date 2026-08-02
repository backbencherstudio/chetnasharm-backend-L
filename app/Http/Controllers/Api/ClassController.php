<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClassController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = Pagination::perPage($request);
        $search = $request->search;

        $classes = ClassModel::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);

        $this->withTeachers($classes);

        return response()->json([
            'success' => true,
            'message' => 'Classes retrieved successfully',
            'data' => $classes->items(),
            'pagination' => [
                'current_page' => $classes->currentPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
                'last_page' => $classes->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'who_is_for' => 'nullable|string',
            'curriculum' => 'nullable|string',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => 'exists:teachers,id',
            'is_class_recording' => 'nullable|in:0,1',
            'price' => 'required|numeric|min:0',
            'duration_in_days' => 'required|integer|min:1',
            'total_classes' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('classes', 'public');
        }

        $class = ClassModel::create($validated);

        $this->withTeachers(collect([$class]));

        return response()->json([
            'success' => true,
            'message' => 'Class created successfully',
            'data' => $class,
        ], 201);
    }

    /**
     * Get data for editing the specified resource.
     *
     * @return JsonResponse
     */
    public function edit($id)
    {
        $class = ClassModel::find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Class retrieved successfully',
            'data' => $class,
        ]);
    }

    /**
     * Update the specified resource.
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $class = ClassModel::find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'who_is_for' => 'nullable|string',
            'curriculum' => 'nullable|string',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => 'exists:teachers,id',
            'is_class_recording' => 'nullable|in:0,1',
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

        $this->withTeachers(collect([$class]));

        return response()->json([
            'success' => true,
            'message' => 'Class updated successfully',
            'data' => $class,
        ]);
    }

    /**
     * Toggle the active status of the resource.
     *
     * @return JsonResponse
     */
    public function status($id)
    {
        $class = ClassModel::find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }
        $class->is_active = $class->is_active == 1 ? 0 : 1;
        $class->save();

        return response()->json([
            'success' => true,
            'message' => 'Class status updated successfully',
            'status' => $class->is_active,
        ]);
    }

    /**
     * List classes for the public landing page.
     *
     * @return JsonResponse
     */
    public function landClass(Request $request)
    {
        $query = ClassModel::where('is_active', 1);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('title', 'like', "%{$search}%");
        }

        $perPage = Pagination::perPage($request);

        $classes = $query
            ->select(
                'id',
                'title',
                'description',
                'short_description',
                'who_is_for',
                'curriculum',
                'teacher_ids',
                'price',
                'duration_in_days',
                'total_classes',
                'image',
                'is_class_recording'
            )
            ->latest()
            ->paginate($perPage);

        $this->withTeachers($classes);

        return response()->json([
            'success' => true,
            'message' => 'Classes fetched successfully',

            'data' => $classes->items(),

            'pagination' => [
                'current_page' => $classes->currentPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
                'last_page' => $classes->lastPage(),
            ],
        ]);
    }

    /**
     * List batches for a class on the landing page.
     *
     * @return JsonResponse
     */
    public function landBatch(Request $request, $classId)
    {
        $query = Batch::where('class_id', $classId)
            ->where('active_status', 1);

        $perPage = Pagination::perPage($request);

        $batches = $query
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
                'schedules:id,batch_id,day_of_week,start_time,end_time',
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Batches fetched successfully',

            'data' => $batches->items(),

            'pagination' => [
                'current_page' => $batches->currentPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
                'last_page' => $batches->lastPage(),
            ],
        ]);
    }

    /**
     * Get details for a single batch.
     *
     * @return JsonResponse
     */
    public function singleBatch($batchId)
    {
        $user = auth('api')->user();

        $batch = Batch::where('id', $batchId)
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
                'class:id,title,image,description,price',
                'schedules:id,batch_id,day_of_week,start_time,end_time',
            ])
            ->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        $enrolled = false;

        if ($user) {
            $enrolled = Enrollment::where('user_id', $user->id)
                ->where('batch_id', $batchId)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'message' => 'Batch fetched successfully',
            'data' => $batch,
            'enrolled_status' => $enrolled,
        ]);
    }

    /**
     * List teachers linked to a class.
     *
     * @return JsonResponse
     */
    public function classTeachers($classId)
    {
        $class = ClassModel::find($classId);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Class teachers retrieved successfully',
            'data' => $class->teachers(),
        ]);
    }

    /**
     * Get public details for a class.
     *
     * @return JsonResponse
     */
    public function singleClass($classId)
    {
        $class = ClassModel::where('id', $classId)
            ->where('is_active', 1)
            ->select(
                'id',
                'title',
                'description',
                'short_description',
                'who_is_for',
                'curriculum',
                'teacher_ids',
                'price',
                'duration_in_days',
                'total_classes',
                'image',
                'is_class_recording'
            )
            ->first();

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        $class->setAttribute('teachers', $this->loadTeachers([$class]));
        unset($class->teacher_ids);

        return response()->json([
            'success' => true,
            'message' => 'Class fetched successfully',
            'data' => $class,
        ]);
    }

    /**
     * Eager-load teachers onto class collections.
     */
    private function withTeachers($classes): void
    {
        $teachers = $this->loadTeachers($classes);

        foreach ($classes as $class) {
            $class->setAttribute('teachers', collect($class->teacher_ids ?? [])
                ->map(fn ($id) => $teachers->get($id))
                ->filter()
                ->values());

            unset($class->teacher_ids);
        }
    }

    /**
     * Load teachers.
     *
     * @return mixed
     */
    private function loadTeachers($classes)
    {
        if ($classes instanceof LengthAwarePaginator) {
            $classes = $classes->items();
        }

        $teacherIds = collect($classes)
            ->flatMap(fn ($class) => $class->teacher_ids ?? [])
            ->unique()
            ->values();

        return Teacher::whereIn('id', $teacherIds)
            ->get(['id', 'name', 'image'])
            ->keyBy('id')
            ->map(fn ($teacher) => $teacher->setHidden(['image_url', 'intro_video_url']));
    }
}
