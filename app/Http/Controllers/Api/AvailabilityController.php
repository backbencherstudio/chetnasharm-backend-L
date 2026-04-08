<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Models\TeacherAvailability;


class AvailabilityController extends Controller
{

    public function index(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $teacherId = auth('api')->user()->teacher->id;

        $start = Carbon::parse($request->month)->startOfMonth();
        $end   = Carbon::parse($request->month)->endOfMonth();

        $availabilities = TeacherAvailability::where('teacher_id', $teacherId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->groupBy('date')
            ->map(function ($slots) {
                return $slots->map(function ($slot) {
                    return [
                        'id' => $slot->id,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                    ];
                });
            });

        return response()->json([
            'success' => true,
            'data' => $availabilities
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required',
        ]);

        $teacherId = auth('api')->user()->teacher->id;

        $dayOfMonth = Carbon::parse($validated['date'])->day;

        $setting = Setting::first();

        if (!$setting || !$setting->class_time) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not configured'
            ], 422);
        }

        $classTime = (int) $setting->class_time;

        $createdSlots = [];

        foreach ($validated['slots'] as $index => $slot) {

            $startTime = Carbon::parse($slot['start_time']);
            $endTime   = $startTime->copy()->addMinutes($classTime);

            if ($endTime->gt(Carbon::parse('23:59'))) {
                return response()->json([
                    'success' => false,
                    'message' => "Slot #".($index+1)." exceeds day limit"
                ], 422);
            }

            $exists = TeacherAvailability::where('teacher_id', $teacherId)
                ->where('day_of_month', $dayOfMonth)
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->whereBetween('start_time', [
                            $startTime->format('H:i:s'),
                            $endTime->format('H:i:s')
                        ])
                    ->orWhereBetween('end_time', [
                            $startTime->format('H:i:s'),
                            $endTime->format('H:i:s')
                        ])
                    ->orWhere(function ($q2) use ($startTime, $endTime) {
                        $q2->where('start_time', '<=', $startTime->format('H:i:s'))
                            ->where('end_time', '>=', $endTime->format('H:i:s'));
                    });
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Time slot {$startTime->format('H:i')} overlaps"
                ], 422);
            }

            $createdSlots[] = TeacherAvailability::create([
                'teacher_id' => $teacherId,
                'day_of_month' => $dayOfMonth,
                'start_time' => $startTime->format('H:i:s'),
                'end_time' => $endTime->format('H:i:s'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Slots saved (recurring monthly)',
            'data' => $createdSlots
        ]);
    }

    public function update(Request $request, $id)
    {
        $availability = TeacherAvailability::find($id);

        if (!$availability) {
            return response()->json([
                'success' => false,
                'message' => 'Not found'
            ], 404);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        $availability->update($validated);

        return response()->json([
            'success' => true,
            'data' => $availability
        ]);
    }

    public function destroy($id)
    {
        $availability = TeacherAvailability::find($id);

        if (!$availability) {
            return response()->json([
                'success' => false,
                'message' => 'Not found'
            ], 404);
        }

        $availability->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully'
        ]);
    }



}
