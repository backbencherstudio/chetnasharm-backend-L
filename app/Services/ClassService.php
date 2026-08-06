<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ClassService
{
    /**
     * @return array{items: array<int, ClassModel>, pagination: array<string, int>}
     */
    public function index(Request $request): array
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

        return [
            'items' => $classes->items(),
            'pagination' => [
                'current_page' => $classes->currentPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
                'last_page' => $classes->lastPage(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(array $validated, ?UploadedFile $image = null): ClassModel
    {
        if ($image) {
            $validated['image'] = $image->store('classes', 'public');
        }

        $class = ClassModel::create($validated);

        $this->withAssignedTeachers(collect([$class]));

        return $class;
    }

    public function find(int $id): ?ClassModel
    {
        $class = ClassModel::find($id);

        if ($class) {
            $this->withAssignedTeachers(collect([$class]));
        }

        return $class;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(ClassModel $class, array $validated, ?UploadedFile $image = null): ClassModel
    {
        if ($image) {
            if ($class->image && Storage::disk('public')->exists($class->image)) {
                Storage::disk('public')->delete($class->image);
            }

            $validated['image'] = $image->store('classes', 'public');
        }

        $class->update($validated);

        $this->withAssignedTeachers(collect([$class]));

        return $class;
    }

    public function toggleStatus(ClassModel $class): ClassModel
    {
        $class->is_active = $class->is_active == 1 ? 0 : 1;
        $class->save();

        return $class;
    }

    /**
     * @return array{items: array<int, ClassModel>, pagination: array<string, int>}
     */
    public function landClasses(Request $request): array
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

        return [
            'items' => $classes->items(),
            'pagination' => [
                'current_page' => $classes->currentPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
                'last_page' => $classes->lastPage(),
            ],
        ];
    }

    /**
     * @return array{items: array<int, Batch>, pagination: array<string, int>}
     */
    public function landBatches(Request $request, int $classId): array
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

        return [
            'items' => $batches->items(),
            'pagination' => [
                'current_page' => $batches->currentPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
                'last_page' => $batches->lastPage(),
            ],
        ];
    }

    /**
     * @return array{batch: Batch, enrolled: bool}|null
     */
    public function singleBatch(int $batchId, ?int $userId = null): ?array
    {
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
            return null;
        }

        $enrolled = false;

        if ($userId) {
            $enrolled = Enrollment::where('user_id', $userId)
                ->where('batch_id', $batchId)
                ->exists();
        }

        return [
            'batch' => $batch,
            'enrolled' => $enrolled,
        ];
    }

    public function classTeachers(int $classId): ?Collection
    {
        $class = ClassModel::find($classId);

        if (! $class) {
            return null;
        }

        return $this->assignedTeachersForClass((int) $classId);
    }

    public function singleClass(int $classId): ?ClassModel
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

        if ($class) {
            $this->withAssignedTeachers(collect([$class]));
        }

        return $class;
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
