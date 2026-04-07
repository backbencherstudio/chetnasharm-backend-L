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

            'schedules' => 'required|array|min:1',
            'schedules.*.day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.start_time' => 'required|date_format:H:i',
            'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',
        ]);

        return DB::transaction(function () use ($validated, $request) {

            foreach ($request->schedules as $schedule) {

                $conflict = BatchSchedule::where('teacher_id', $validated['teacher_id'])
                    ->where('day_of_week', $schedule['day_of_week'])
                    ->where(function ($query) use ($schedule) {
                        $query->where('start_time', '<', $schedule['end_time'])
                            ->where('end_time', '>', $schedule['start_time']);
                    })
                    ->exists();

                if ($conflict) {
                    return response()->json([
                        'success' => false,
                        'message' => "Teacher already has a class on {$schedule['day_of_week']} between {$schedule['start_time']} - {$schedule['end_time']}"
                    ], 422);
                }
            }

            $batch = Batch::create($validated);

            $schedules = [];
            foreach ($request->schedules as $schedule) {
                $schedules[] = [
                    'batch_id'   => $batch->id,
                    'teacher_id' => $validated['teacher_id'],
                    'day_of_week'=> $schedule['day_of_week'],
                    'start_time' => $schedule['start_time'],
                    'end_time'   => $schedule['end_time'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            BatchSchedule::insert($schedules);

            return response()->json([
                'success' => true,
                'message' => 'Batch with schedules created successfully',
                'data' => $batch->load('schedules')
            ], 201);
        });
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

    public function classList()
    {
        $classes = ClassModel::select('id', 'title')->get();

        return response()->json([
            'success' => true,
            'message' => 'Class list retrieved successfully',
            'data' => $classes
        ]);
    }

    public function teacherList()
    {
        $teachers = Teacher::select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Teacher list retrieved successfully',
            'data' => $teachers
        ]);
    }

}
