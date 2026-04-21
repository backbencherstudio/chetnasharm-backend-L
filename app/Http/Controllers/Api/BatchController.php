<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassModel;
use App\Models\Setting;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\TeacherAvailability;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $perPage  = $request->query('limit', $request->query('per_page', 10));
        $search   = $request->query('search');
        $teacher  = $request->query('teacher_id');
        $classId  = $request->query('class_id');
        $status   = $request->query('status');

        $query = Batch::select([
                'id', 'class_id', 'teacher_id', 'name',
                'total_seat', 'filled_seat',
                'start_date', 'end_date', 'status', 'active_status'
            ])
            ->with([
                'class:id,title',
                'teacher:id,name',
                'schedules:id,batch_id,day_of_week,start_time,end_time'
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('class', fn($q2) => $q2->where('title', 'like', "%{$search}%"))
                ->orWhereHas('teacher', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
            });
        }

        $query->when($teacher, fn($q) => $q->where('teacher_id', $teacher))
            ->when($classId, fn($q) => $q->where('class_id', $classId))
            ->when($status, fn($q) => $q->where('status', $status));

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

        return response()->json([
            'success' => true,
            'message' => 'Batch list fetched successfully',
            'data' => $batches->items(),
            'pagination' => [
                'current_page' => $batches->currentPage(),
                'per_page'     => $batches->perPage(),
                'total'        => $batches->total(),
                'last_page'    => $batches->lastPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id'     => 'required|exists:classes,id',
            'teacher_id'   => 'required|exists:teachers,id',
            'name'         => 'required|string|max:255',
            'total_seat'   => 'required|integer|min:1',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'zoom_link'    => 'nullable|url',
            'status'       => 'required|in:upcoming,ongoing,completed',
            'schedules'    => 'required|array|min:1',
            'schedules.*.day_of_week' => 'required|integer|between:0,6',
            'schedules.*.start_time'  => 'required|date_format:H:i',
        ]);

        $teacherId = $validated['teacher_id'];

        $class_time = Setting::first()?->class_time;

        if (!$class_time) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not set in settings'
            ], 422);
        }

        $duplicates = collect($validated['schedules'])
            ->map(fn($s) => $s['day_of_week'].'-'.$s['start_time'])
            ->duplicates();

        if ($duplicates->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate schedule entries found'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $batch = Batch::create([
                'class_id'     => $validated['class_id'],
                'teacher_id'   => $teacherId,
                'name'         => $validated['name'],
                'total_seat'   => $validated['total_seat'],
                'start_date'   => $validated['start_date'],
                'end_date'     => $validated['end_date'],
                'zoom_link'    => $validated['zoom_link'] ?? null,
                'status'       => $validated['status'],
            ]);

            $startDate = $validated['start_date'];
            $endDate   = $validated['end_date'];

            foreach ($validated['schedules'] as $schedule) {

                $startTime = Carbon::parse($schedule['start_time']);
                $endTime   = (clone $startTime)->addMinutes($class_time);

                $startTimeStr = $startTime->format('H:i:s');
                $endTimeStr   = $endTime->format('H:i:s');

                $availability = TeacherAvailability::where('teacher_id', $teacherId)
                    ->where('day_of_week', $schedule['day_of_week'])
                    ->where('start_time', '<=', $startTimeStr)
                    ->where('end_time', '>=', $endTimeStr)
                    ->exists();

                if (!$availability) {
                    throw new \Exception(
                        "Teacher not available on day {$schedule['day_of_week']} at {$schedule['start_time']}"
                    );
                }

                $conflict = BatchSchedule::where('teacher_id', $teacherId)
                    ->where('day_of_week', $schedule['day_of_week'])
                    ->whereHas('batch', function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                    })
                    ->where(function ($q) use ($startTimeStr, $endTimeStr) {
                        $q->whereBetween('start_time', [$startTimeStr, $endTimeStr])
                        ->orWhereBetween('end_time', [$startTimeStr, $endTimeStr])
                        ->orWhere(function ($q2) use ($startTimeStr, $endTimeStr) {
                            $q2->where('start_time', '<=', $startTimeStr)
                                ->where('end_time', '>=', $endTimeStr);
                        });
                    })
                    ->exists();

                if ($conflict) {
                    throw new \Exception(
                        "Schedule conflict on day {$schedule['day_of_week']} at {$schedule['start_time']}"
                    );
                }

                BatchSchedule::create([
                    'batch_id'    => $batch->id,
                    'teacher_id'  => $teacherId,
                    'day_of_week' => $schedule['day_of_week'],
                    'start_time'  => $startTimeStr,
                    'end_time'    => $endTimeStr,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Batch created successfully',
                'data'    => $batch->load('schedules'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function edit($id)
    {
        $batch = Batch::with('schedules')->find($id);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
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
            'class_id'     => 'required|exists:classes,id',
            'teacher_id'   => 'required|exists:teachers,id',
            'name'         => 'required|string|max:255',
            'total_seat'   => 'required|integer|min:1',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'zoom_link'    => 'nullable|url',
            'status'       => 'required|in:upcoming,ongoing,completed',
            'active_status'=> 'nullable|in:0,1',
            'schedules'    => 'required|array|min:1',
            'schedules.*.day_of_week' => 'required|integer|between:0,6',
            'schedules.*.start_time'  => 'required|date_format:H:i',
        ]);

        $teacherId = $validated['teacher_id'];

        $class_time = Setting::first()?->class_time;

        if (!$class_time) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not set in settings'
            ], 422);
        }

        $duplicates = collect($validated['schedules'])
            ->map(fn($s) => $s['day_of_week'].'-'.$s['start_time'])
            ->duplicates();

        if ($duplicates->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate schedule entries found'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $batch->update([
                'class_id'     => $validated['class_id'],
                'teacher_id'   => $teacherId,
                'name'         => $validated['name'],
                'total_seat'   => $validated['total_seat'],
                'start_date'   => $validated['start_date'],
                'end_date'     => $validated['end_date'],
                'zoom_link'    => $validated['zoom_link'] ?? null,
                'status'       => $validated['status'],
                'active_status'=> $validated['active_status'] ?? $batch->active_status,
            ]);

            $startDate = $validated['start_date'];
            $endDate   = $validated['end_date'];

            BatchSchedule::where('batch_id', $batch->id)->delete();

            foreach ($validated['schedules'] as $schedule) {

                $startTime = Carbon::parse($schedule['start_time']);
                $endTime   = (clone $startTime)->addMinutes($class_time);

                $startTimeStr = $startTime->format('H:i:s');
                $endTimeStr   = $endTime->format('H:i:s');

                $availability = TeacherAvailability::where('teacher_id', $teacherId)
                    ->where('day_of_week', $schedule['day_of_week'])
                    ->where('start_time', '<=', $startTimeStr)
                    ->where('end_time', '>=', $endTimeStr)
                    ->exists();

                if (!$availability) {
                    throw new \Exception(
                        "Teacher not available on day {$schedule['day_of_week']} at {$schedule['start_time']}"
                    );
                }

                $conflict = BatchSchedule::where('teacher_id', $teacherId)
                    ->where('day_of_week', $schedule['day_of_week'])
                    ->where('batch_id', '!=', $batch->id)
                    ->whereHas('batch', function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                    })
                    ->where(function ($q) use ($startTimeStr, $endTimeStr) {
                        $q->whereBetween('start_time', [$startTimeStr, $endTimeStr])
                        ->orWhereBetween('end_time', [$startTimeStr, $endTimeStr])
                        ->orWhere(function ($q2) use ($startTimeStr, $endTimeStr) {
                            $q2->where('start_time', '<=', $startTimeStr)
                                ->where('end_time', '>=', $endTimeStr);
                        });
                    })
                    ->exists();

                if ($conflict) {
                    throw new \Exception(
                        "Schedule conflict on day {$schedule['day_of_week']} at {$schedule['start_time']}"
                    );
                }

                BatchSchedule::create([
                    'batch_id'    => $batch->id,
                    'teacher_id'  => $teacherId,
                    'day_of_week' => $schedule['day_of_week'],
                    'start_time'  => $startTimeStr,
                    'end_time'    => $endTimeStr,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Batch updated successfully',
                'data'    => $batch->load('schedules'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
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
        if ($batch->filled_seat > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete batch with enrolled students'
            ], 422);
        }

        $batch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Batch deleted successfully'
        ]);
    }

    public function classList()
    {
        $classes = ClassModel::where('is_active', 1)->select('id', 'title')->get();

        return response()->json([
            'success' => true,
            'message' => 'Class list retrieved successfully',
            'data' => $classes
        ]);
    }

    public function teacherList()
    {
        $teachers = Teacher::where('suspend_status', 0)->select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Teacher list retrieved successfully',
            'data' => $teachers
        ]);
    }

    public function status($id)
    {
        $batch = Batch::find($id);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found'
            ], 404);
        }
        $batch->active_status = $batch->active_status == 1 ? 0 : 1;
        $batch->save();

        return response()->json([
            'success' => true,
            'message' => 'Batch status updated successfully',
            'data' => [
                'id' => $batch->id,
                'active_status' => $batch->active_status
            ]
        ]);
    }

    public function teacherBatch(Request $request)
    {
        $perPage  = $request->query('limit', $request->query('per_page', 10));
        $search   = $request->query('search');
        $teacher  = $request->query('teacher_id');
        $classId  = $request->query('class_id');
        $status   = $request->query('status');

        $query = Batch::select([
                'id', 'class_id', 'teacher_id', 'name',
                'total_seat', 'filled_seat',
                'start_date', 'end_date', 'status', 'active_status'
            ])
            ->with([
                'class:id,title',
                'teacher:id,user_id',
                'teacher.user:id,name,suspend_status',
                'schedules:id,batch_id,day_of_week,start_time,end_time'
            ])

            ->whereHas('teacher.user', function ($q) {
                $q->where('suspend_status', 0);
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('class', fn($q2) => $q2->where('title', 'like', "%{$search}%"))
                ->orWhereHas('teacher.user', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
            });
        }

        $query->when($teacher, fn($q) => $q->where('teacher_id', $teacher))
            ->when($classId, fn($q) => $q->where('class_id', $classId))
            ->when($status, fn($q) => $q->where('status', $status));

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

        return response()->json([
            'success' => true,
            'message' => 'Batch list fetched successfully',
            'data' => $batches->items(),
            'pagination' => [
                'current_page' => $batches->currentPage(),
                'per_page'     => $batches->perPage(),
                'total'        => $batches->total(),
                'last_page'    => $batches->lastPage(),
            ]
        ]);
    }

}
