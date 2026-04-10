<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassModel;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('limit', $request->query('per_page', 10));
        $search  = $request->query('search');
        $teacher = $request->query('teacher_id');
        $class   = $request->query('class_id');

        $query = Batch::with([
            'class:id,title',
            'teacher:id,name',
            'schedules:id,batch_id,day_of_week,start_time,end_time'
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('class', function ($q2) use ($search) {
                    $q2->where('title', 'like', "%{$search}%");
                })
                ->orWhereHas('teacher', function ($q3) use ($search) {
                    $q3->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($teacher) {
            $query->where('teacher_id', $teacher);
        }

        if ($class) {
            $query->where('class_id', $class);
        }

        $query->latest();

        $batches = $query->paginate($perPage);

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
            'class_id'   => 'required|exists:classes,id',
            'teacher_id' => 'required|exists:teachers,id',
            'name'       => 'required|string|max:255',
            'total_seat' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'zoom_link'  => 'nullable|url',
            'status'     => 'nullable|in:upcoming,ongoing,completed',

            'schedules' => 'required|array|min:1',
            'schedules.*.day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.start_time'  => 'required|date_format:H:i',
            'schedules.*.end_time'    => 'required|date_format:H:i|after:start_time',
        ]);

        try {
            return DB::transaction(function () use ($validated) {

                $validated['end_date'] = $validated['end_date'] ?? $validated['start_date'];

                $duplicates = collect($validated['schedules'])
                    ->map(fn ($s) => $s['day_of_week'].'-'.$s['start_time'].'-'.$s['end_time'])
                    ->duplicates();

                if ($duplicates->isNotEmpty()) {
                    throw new \Exception('Duplicate schedules found in request.');
                }

                foreach ($validated['schedules'] as $schedule) {

                    $conflict = BatchSchedule::where('teacher_id', $validated['teacher_id'])
                        ->where('day_of_week', $schedule['day_of_week'])

                        ->whereHas('batch', function ($q) use ($validated) {
                            $q->where(function ($query) use ($validated) {
                                $query->whereDate('start_date', '<=', $validated['end_date'])
                                    ->whereDate('end_date', '>=', $validated['start_date']);
                            });
                        })
                        ->where(function ($query) use ($schedule) {
                            $query->where('start_time', '<', $schedule['end_time'])
                                ->where('end_time', '>', $schedule['start_time']);
                        })->exists();

                    if ($conflict) {
                        throw new \Exception(
                            "Conflict: Teacher already has a class on {$schedule['day_of_week']} between {$schedule['start_time']} - {$schedule['end_time']} within selected date range"
                        );
                    }
                }

                $batchData = collect($validated)->except('schedules')->toArray();
                $batch = Batch::create($batchData);
                $now = now();

                $schedules = collect($validated['schedules'])->map(function ($schedule) use ($batch, $validated, $now) {
                    return [
                        'batch_id'    => $batch->id,
                        'teacher_id'  => $validated['teacher_id'],
                        'day_of_week' => $schedule['day_of_week'],
                        'start_time'  => $schedule['start_time'],
                        'end_time'    => $schedule['end_time'],
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                })->toArray();

                BatchSchedule::insert($schedules);

                return response()->json([
                    'success' => true,
                    'message' => 'Batch with schedules created successfully',
                    'data'    => $batch->load('schedules')
                ], 201);
            });

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function edit($id)
    {
        $batch = Batch::with([
            'class:id,title',
            'teacher:id,name',
            'schedules:id,batch_id,day_of_week,start_time,end_time'
        ])->find($id);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Batch fetched successfully',
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
            'class_id'   => 'required|exists:classes,id',
            'teacher_id' => 'required|exists:teachers,id',
            'name'       => 'required|string|max:255',
            'total_seat' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'zoom_link'  => 'nullable|url',
            'status'     => 'nullable|in:upcoming,ongoing,completed',

            'schedules' => 'required|array|min:1',
            'schedules.*.day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.start_time'  => 'required|date_format:H:i',
            'schedules.*.end_time'    => 'required|date_format:H:i|after:start_time',
        ]);

        try {
            return DB::transaction(function () use ($validated, $batch) {

                $validated['end_date'] = $validated['end_date'] ?? $validated['start_date'];

                $duplicates = collect($validated['schedules'])
                    ->map(fn ($s) => $s['day_of_week'].'-'.$s['start_time'].'-'.$s['end_time'])
                    ->duplicates();

                if ($duplicates->isNotEmpty()) {
                    throw new \Exception('Duplicate schedules found in request.');
                }

                foreach ($validated['schedules'] as $schedule) {

                    $conflict = BatchSchedule::where('teacher_id', $validated['teacher_id'])
                        ->where('day_of_week', $schedule['day_of_week'])
                        ->where('batch_id', '!=', $batch->id)

                        ->whereHas('batch', function ($q) use ($validated) {

                            $newStart = $validated['start_date'];
                            $newEnd   = $validated['end_date'];

                            $q->whereRaw("
                                DATE(start_date) <= ?
                                AND DATE(COALESCE(end_date, start_date)) >= ?
                            ", [$newEnd, $newStart]);
                        })

                        ->where(function ($query) use ($schedule) {
                            $query->where('start_time', '<', $schedule['end_time'])
                                ->where('end_time', '>', $schedule['start_time']);
                        })

                        ->exists();

                    if ($conflict) {
                        throw new \Exception(
                            "Conflict: Teacher already has a class on {$schedule['day_of_week']} between {$schedule['start_time']} - {$schedule['end_time']} within selected date range"
                        );
                    }
                }

                $batchData = collect($validated)->except('schedules')->toArray();
                $batch->update($batchData);

                BatchSchedule::where('batch_id', $batch->id)->delete();
                $now = now();

                $schedules = collect($validated['schedules'])->map(function ($schedule) use ($batch, $validated, $now) {
                    return [
                        'batch_id'    => $batch->id,
                        'teacher_id'  => $validated['teacher_id'],
                        'day_of_week' => $schedule['day_of_week'],
                        'start_time'  => $schedule['start_time'],
                        'end_time'    => $schedule['end_time'],
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                })->toArray();

                BatchSchedule::insert($schedules);

                return response()->json([
                    'success' => true,
                    'message' => 'Batch updated successfully',
                    'data'    => $batch->load('schedules')
                ]);
            });

        } catch (\Throwable $e) {
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

}
