<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BatchService
{
    /**
     * @return array{items: array<int, Batch>, pagination: array<string, int>}
     */
    public function index(Request $request): array
    {
        $perPage = Pagination::perPage($request);
        $search = $request->query('search');
        $teacher = $request->query('teacher_id');
        $classId = $request->query('class_id');
        $status = $request->query('status');

        $query = Batch::select([
            'id', 'class_id', 'teacher_id', 'name',
            'total_seat', 'filled_seat',
            'start_date', 'end_date', 'status', 'active_status',
        ])
            ->with([
                'class:id,title',
                'teacher:id,user_id',
                'teacher.user:id,name',
                'schedules:id,batch_id,day_of_week,start_time,end_time',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('class', fn ($q2) => $q2->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('teacher.user', fn ($q3) => $q3->where('name', 'like', "%{$search}%"));
            });
        }

        $query->when($teacher, fn ($q) => $q->where('teacher_id', $teacher))
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($status, fn ($q) => $q->where('status', $status));

        if ($request->start_date && $request->end_date) {
            $query->where(function ($q) use ($request) {
                $q->where('start_date', '<=', $request->end_date)
                    ->where('end_date', '>=', $request->start_date);
            });
        }

        if ($request->day_of_week !== null) {
            $query->whereHas('schedules', function ($q) use ($request) {
                $q->where('day_of_week', $request->day_of_week);
            });
        }

        $batches = $query->latest()->paginate($perPage);

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
     * @param  array<string, mixed>  $validated
     */
    public function store(array $validated): Batch
    {
        $teacherId = $validated['teacher_id'];

        $classTime = Setting::first()?->class_time;

        if (! $classTime) {
            throw new \Exception('Class time not set in settings');
        }

        $this->assertNoDuplicateSchedules($validated['schedules']);

        DB::beginTransaction();

        try {
            $batch = Batch::create([
                'class_id' => $validated['class_id'],
                'teacher_id' => $teacherId,
                'name' => $validated['name'],
                'total_seat' => $validated['total_seat'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'zoom_link' => $validated['zoom_link'] ?? null,
                'status' => $validated['status'],
            ]);

            $this->syncSchedules(
                $batch,
                $validated['schedules'],
                $teacherId,
                $validated['start_date'],
                $validated['end_date'],
                $classTime
            );

            DB::commit();

            return $batch->load('schedules');
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function findForEdit(int $id): ?Batch
    {
        return Batch::with('schedules')->find($id);
    }

    public function find(int $id): ?Batch
    {
        return Batch::find($id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Batch $batch, array $validated): Batch
    {
        $teacherId = $validated['teacher_id'];

        if ($validated['total_seat'] < $batch->filled_seat) {
            throw new \Exception('Total seats cannot be less than filled seats');
        }

        $classTime = Setting::first()?->class_time;

        if (! $classTime) {
            throw new \Exception('Class time not set in settings');
        }

        $this->assertNoDuplicateSchedules($validated['schedules']);

        DB::beginTransaction();

        try {
            $batch->update([
                'class_id' => $validated['class_id'],
                'teacher_id' => $teacherId,
                'name' => $validated['name'],
                'total_seat' => $validated['total_seat'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'zoom_link' => $validated['zoom_link'] ?? null,
                'status' => $validated['status'],
                'active_status' => $validated['active_status'] ?? $batch->active_status,
            ]);

            BatchSchedule::where('batch_id', $batch->id)->delete();

            $this->syncSchedules(
                $batch,
                $validated['schedules'],
                $teacherId,
                $validated['start_date'],
                $validated['end_date'],
                $classTime,
                true
            );

            DB::commit();

            return $batch->load('schedules');
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function delete(Batch $batch): void
    {
        if ($batch->filled_seat > 0) {
            throw new \Exception('Cannot delete batch with enrolled students');
        }

        $batch->delete();
    }

    /** @return Collection<int, ClassModel> */
    public function classList(): Collection
    {
        return ClassModel::where('is_active', 1)->select('id', 'title')->get();
    }

    /** @return Collection<int, array{id: int, name: string}> */
    public function teacherList(): Collection
    {
        return Teacher::query()
            ->active()
            ->with('user:id,name')
            ->get(['id', 'user_id'])
            ->map(fn (Teacher $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->name,
            ])
            ->values();
    }

    /** @return array{id: int, active_status: int} */
    public function toggleStatus(Batch $batch): array
    {
        $batch->active_status = $batch->active_status == 1 ? 0 : 1;
        $batch->save();

        return [
            'id' => $batch->id,
            'active_status' => $batch->active_status,
        ];
    }

    /**
     * @return array{items: array<int, Batch>, pagination: array<string, int>}
     */
    public function teacherBatch(Request $request, Teacher $teacher): array
    {
        $perPage = Pagination::perPage($request);
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Batch::select([
            'id', 'class_id', 'teacher_id', 'name',
            'total_seat', 'filled_seat',
            'start_date', 'end_date', 'status', 'active_status',
        ])
            ->with([
                'class:id,title',
                'teacher:id,user_id',
                'teacher.user:id,name,suspend_status',
                'schedules:id,batch_id,day_of_week,start_time,end_time',
            ])
            ->withCount(['assignments as active_assignments_count' => fn ($q) => $q->active()])
            ->where('teacher_id', $teacher->id)
            ->whereHas('teacher.user', function ($q) {
                $q->where('suspend_status', 0);
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('class', fn ($q2) => $q2->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('teacher.user', fn ($q3) => $q3->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($request->start_date && $request->end_date) {
            $query->where(function ($q) use ($request) {
                $q->where('start_date', '<=', $request->end_date)
                    ->where('end_date', '>=', $request->start_date);
            });
        }

        if ($request->day_of_week !== null) {
            $query->whereHas('schedules', function ($q) use ($request) {
                $q->where('day_of_week', $request->day_of_week);
            });
        }

        $batches = $query->latest()->paginate($perPage);

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

    /** @return Collection<int, Batch> */
    public function getBatchesByClass(int $classId): Collection
    {
        return Batch::where('class_id', $classId)
            ->select('id', 'name', 'total_seat', 'filled_seat')
            ->where('active_status', 1)
            ->get();
    }

    /**
     * @return array{items: array<int, Batch>, pagination: array<string, int>}|null
     */
    public function studentBatch(Request $request, int $userId): ?array
    {
        $enrollments = Enrollment::where('user_id', $userId)->pluck('batch_id');

        if ($enrollments->isEmpty()) {
            return null;
        }

        $query = Batch::select([
            'id', 'class_id', 'teacher_id', 'name',
            'total_seat', 'filled_seat', 'start_date', 'end_date', 'status', 'active_status',
        ])
            ->with([
                'class:id,title,image',
                'teacher:id,user_id',
                'teacher.user:id,name,image',
                'schedules:id,batch_id,day_of_week,start_time,end_time',
            ])
            ->withCount(['assignments as active_assignments_count' => fn ($q) => $q->active()])
            ->whereIn('id', $enrollments);

        $search = $request->query('search');
        $status = $request->query('status');
        $classId = $request->query('class_id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('class', fn ($q2) => $q2->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('teacher.user', fn ($q3) => $q3->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($classId) {
            $query->where('class_id', $classId);
        }

        $perPage = Pagination::perPage($request);
        $batches = $query->latest()->paginate($perPage);

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

    public function updateZoomLink(Batch $batch, string $zoomLink): Batch
    {
        $batch->update([
            'zoom_link' => $zoomLink,
        ]);

        return $batch;
    }

    public function singleBatch(int $batchId): ?Batch
    {
        return Batch::with([
            'class:id,title,description,image',
            'teacher:id,user_id',
            'teacher.user:id,name',
            'schedules:id,batch_id,day_of_week,start_time,end_time',
        ])
            ->withCount(['assignments as active_assignments_count' => fn ($q) => $q->active()])
            ->find($batchId);
    }

    public function isStudentEnrolled(int $userId, int $batchId): bool
    {
        return Enrollment::where('user_id', $userId)
            ->where('batch_id', $batchId)
            ->exists();
    }

    /**
     * @param  array<int, array{day_of_week: int, start_time: string}>  $schedules
     */
    private function assertNoDuplicateSchedules(array $schedules): void
    {
        $duplicates = collect($schedules)
            ->map(fn ($s) => $s['day_of_week'].'-'.$s['start_time'])
            ->duplicates();

        if ($duplicates->isNotEmpty()) {
            throw new \Exception('Duplicate schedule entries found');
        }
    }

    /**
     * @param  array<int, array{day_of_week: int, start_time: string}>  $schedules
     */
    private function syncSchedules(
        Batch $batch,
        array $schedules,
        int $teacherId,
        string $startDate,
        string $endDate,
        int $classTime,
        bool $excludeSelf = false
    ): void {
        if ($schedules === []) {
            return;
        }

        $availabilities = TeacherAvailability::query()
            ->where('teacher_id', $teacherId)
            ->get(['day_of_week', 'start_time', 'end_time'])
            ->groupBy('day_of_week');

        $existingSchedules = BatchSchedule::query()
            ->where('teacher_id', $teacherId)
            ->when($excludeSelf, fn ($query) => $query->where('batch_id', '!=', $batch->id))
            ->whereHas('batch', function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate);
            })
            ->get(['day_of_week', 'start_time', 'end_time'])
            ->groupBy('day_of_week');

        $rows = [];
        $now = now();

        foreach ($schedules as $schedule) {
            $startTime = Carbon::parse($schedule['start_time']);
            $endTime = (clone $startTime)->addMinutes($classTime);

            $startTimeStr = $startTime->format('H:i:s');
            $endTimeStr = $endTime->format('H:i:s');
            $dayOfWeek = (int) $schedule['day_of_week'];

            $isAvailable = ($availabilities[$dayOfWeek] ?? collect())->contains(
                function ($availability) use ($startTimeStr, $endTimeStr) {
                    return $availability->start_time <= $startTimeStr
                        && $availability->end_time >= $endTimeStr;
                }
            );

            if (! $isAvailable) {
                throw new \Exception(
                    "Teacher not available on day {$schedule['day_of_week']} at {$schedule['start_time']}"
                );
            }

            $hasConflict = ($existingSchedules[$dayOfWeek] ?? collect())->contains(
                function ($existing) use ($startTimeStr, $endTimeStr) {
                    return ($existing->start_time >= $startTimeStr && $existing->start_time <= $endTimeStr)
                        || ($existing->end_time >= $startTimeStr && $existing->end_time <= $endTimeStr)
                        || ($existing->start_time <= $startTimeStr && $existing->end_time >= $endTimeStr);
                }
            );

            if ($hasConflict) {
                throw new \Exception(
                    "Schedule conflict on day {$schedule['day_of_week']} at {$schedule['start_time']}"
                );
            }

            $rows[] = [
                'batch_id' => $batch->id,
                'teacher_id' => $teacherId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTimeStr,
                'end_time' => $endTimeStr,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        BatchSchedule::insert($rows);
    }
}
