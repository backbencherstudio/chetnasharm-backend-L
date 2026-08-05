<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ClassController extends Controller
{
    /** Display a paginated listing of classes. */
    public function index(Request $request): JsonResponse
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

        $this->withAssignedTeachers($classes);

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

    /** Store a newly created class. */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeCurriculumInput($request);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'who_is_for' => 'nullable|string',
            'curriculum' => 'nullable|array',
            'curriculum.*.title' => 'required|string|max:255',
            'curriculum.*.keypoints' => 'required|array|min:1',
            'curriculum.*.keypoints.*' => 'required|string|max:500',
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

        $this->withAssignedTeachers(collect([$class]));

        return response()->json([
            'success' => true,
            'message' => 'Class created successfully',
            'data' => $class,
        ], 201);
    }

    /** Get a class for editing. */
    public function edit(int $id): JsonResponse
    {
        $class = ClassModel::find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        $this->withAssignedTeachers(collect([$class]));

        return response()->json([
            'success' => true,
            'message' => 'Class retrieved successfully',
            'data' => $class,
        ]);
    }

    /** Update the specified class. */
    public function update(Request $request, int $id): JsonResponse
    {
        $class = ClassModel::find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        $this->normalizeCurriculumInput($request);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'who_is_for' => 'nullable|string',
            'curriculum' => 'nullable|array',
            'curriculum.*.title' => 'required|string|max:255',
            'curriculum.*.keypoints' => 'required|array|min:1',
            'curriculum.*.keypoints.*' => 'required|string|max:500',
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

        $this->withAssignedTeachers(collect([$class]));

        return response()->json([
            'success' => true,
            'message' => 'Class updated successfully',
            'data' => $class,
        ]);
    }

    /** Toggle the active status of a class. */
    public function status(int $id): JsonResponse
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

    /** List classes for the public landing page. */
    public function landClass(Request $request): JsonResponse
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
                'price',
                'duration_in_days',
                'total_classes',
                'image',
                'is_class_recording'
            )
            ->latest()
            ->paginate($perPage);

        $this->withAssignedTeachers($classes);

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

    /** List batches for a class on the landing page. */
    public function landBatch(Request $request, int $classId): JsonResponse
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
                'teacher:id,user_id,intro_video,country,timezone,about,specializations,languages_spoken,courses_can_teach,interests',
                'teacher.user:id,name,email,mobile,image,suspend_status',
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

    /** Get details for a single batch on the landing page. */
    public function singleBatch(int $batchId): JsonResponse
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
                'teacher:id,user_id,intro_video,country,timezone,about,specializations,languages_spoken,courses_can_teach,interests',
                'teacher.user:id,name,email,mobile,image,suspend_status',
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

    /** List teachers linked to a class. */
    public function classTeachers(int $classId): JsonResponse
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
            'data' => $this->assignedTeachersForClass((int) $classId),
        ]);
    }

    /** Get public details for a class. */
    public function singleClass(int $classId): JsonResponse
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

        $this->withAssignedTeachers(collect([$class]));

        return response()->json([
            'success' => true,
            'message' => 'Class fetched successfully',
            'data' => $class,
        ]);
    }

    /** Accept curriculum as an array or JSON string. */
    private function normalizeCurriculumInput(Request $request): void
    {
        $curriculum = $request->input('curriculum');

        if (! is_string($curriculum)) {
            return;
        }

        $decoded = json_decode($curriculum, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $request->merge(['curriculum' => $decoded]);
        }
    }

    /** Attach teachers and batches summary derived from batch assignments. */
    private function withAssignedTeachers(LengthAwarePaginator|Collection $classes): void
    {
        $items = $classes instanceof LengthAwarePaginator
            ? collect($classes->items())
            : collect($classes);

        if ($items->isEmpty()) {
            return;
        }

        $batchesByClass = Batch::query()
            ->whereIn('class_id', $items->pluck('id'))
            ->whereNotNull('teacher_id')
            ->with([
                'teacher:id,user_id,about,specializations,languages_spoken,courses_can_teach,interests,country,timezone',
                'teacher.user:id,name,email,mobile,image,suspend_status',
            ])
            ->get(['id', 'class_id', 'teacher_id', 'name', 'active_status', 'status'])
            ->groupBy('class_id');

        foreach ($items as $class) {
            $classBatches = $batchesByClass->get($class->id, collect());

            $teachers = $classBatches
                ->groupBy('teacher_id')
                ->map(function ($teacherBatches) {
                    $teacher = $teacherBatches->first()?->teacher;

                    if (! $teacher) {
                        return null;
                    }

                    return [
                        'id' => $teacher->id,
                        'name' => $teacher->name,
                        'image' => $teacher->image,
                        'image_url' => $teacher->image_url,
                        'about' => $teacher->about,
                        'specializations' => $teacher->specializations ?? [],
                        'languages_spoken' => $teacher->languages_spoken ?? [],
                        'courses_can_teach' => $teacher->courses_can_teach ?? [],
                        'interests' => $teacher->interests ?? [],
                        'country' => $teacher->country,
                        'timezone' => $teacher->timezone,
                        'batches_count' => $teacherBatches->count(),
                        'batches' => $teacherBatches->map(fn (Batch $batch) => [
                            'id' => $batch->id,
                            'name' => $batch->name,
                            'status' => $batch->status,
                            'active_status' => $batch->active_status,
                        ])->values(),
                    ];
                })
                ->filter()
                ->values();

            $class->setAttribute('teachers_count', $teachers->count());
            $class->setAttribute('batches_count', $classBatches->count());
            $class->setAttribute('teachers', $teachers);
        }
    }

    /** Get assigned teachers for a class. */
    private function assignedTeachersForClass(int $classId): Collection
    {
        $class = new ClassModel;
        $class->id = $classId;
        $this->withAssignedTeachers(collect([$class]));

        return collect($class->getAttribute('teachers') ?? []);
    }
}
