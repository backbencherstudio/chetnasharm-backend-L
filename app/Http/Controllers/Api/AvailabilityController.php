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
        $user = auth('api')->user();

        if ($user->hasRole('teacher')) {
            $teacherId = $user->teacher->id;
        } else {
            $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
            ]);
            $teacherId = $request->teacher_id;
        }

        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $start = Carbon::parse($request->month)->startOfMonth();
        $end   = Carbon::parse($request->month)->endOfMonth();

        $slots = TeacherAvailability::where('teacher_id', $teacherId)->get();

        $result = [];

        $current = $start->copy();

        while ($current <= $end) {

            $day = $current->day;
            $date = $current->toDateString();

            $daySlots = $slots
                ->where('day_of_month', $day)
                ->sortBy('start_time')
                ->map(function ($slot) {
                    return [
                        'id' => $slot->id,
                        'start_time' => Carbon::parse($slot->start_time)->format('H:i'),
                        'end_time' => Carbon::parse($slot->end_time)->format('H:i'),
                    ];
                })
                ->values();

            $result[] = [
                'date' => $date,
                'day' => $day,
                'slots' => $daySlots,
            ];

            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'message' => 'Availability data retrieved successfully',
            'data' => $result
        ]);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();

        if ($user->hasRole('teacher')) {
            $teacherId = $user->teacher->id;
        } else {
            $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
            ]);
            $teacherId = $request->teacher_id;
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required',
        ]);

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
        $failedSlots = [];

        foreach ($validated['slots'] as $index => $slot) {

            $startTime = Carbon::parse($slot['start_time']);
            $endTime   = $startTime->copy()->addMinutes($classTime);

            if ($endTime->gt(Carbon::parse('23:59'))) {
                $failedSlots[] = [
                    'start_time' => $startTime->format('H:i'),
                    'message' => 'Exceeds day limit'
                ];
                continue;
            }

            $exists = TeacherAvailability::where('teacher_id', $teacherId)
                ->where('day_of_month', $dayOfMonth)
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime->format('H:i:s'))
                    ->where('end_time', '>', $startTime->format('H:i:s'));
                })
                ->exists();

            if ($exists) {
                $failedSlots[] = [
                    'start_time' => $startTime->format('H:i'),
                    'message' => 'Overlaps with existing slot'
                ];
                continue;
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
            'message' => 'Slots processed successfully',
            'created' => $createdSlots,
            'failed' => $failedSlots,
            'summary' => [
                'total' => count($validated['slots']),
                'created' => count($createdSlots),
                'failed' => count($failedSlots),
            ]
        ]);
    }

    public function update(Request $request)
    {
        $user = auth('api')->user();

        if ($user->hasRole('teacher')) {
            $teacherId = $user->teacher->id;
        } else {
            $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
            ]);
            $teacherId = $request->teacher_id;
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required',
        ]);

        $dayOfMonth = Carbon::parse($validated['date'])->day;

        $setting = Setting::first();

        if (!$setting || !$setting->class_time) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not configured'
            ], 422);
        }

        $classTime = (int) $setting->class_time;

        TeacherAvailability::where('teacher_id', $teacherId)
            ->where('day_of_month', $dayOfMonth)
            ->delete();

        $createdSlots = [];
        $failedSlots = [];

        foreach ($validated['slots'] as $index => $slot) {

            $startTime = Carbon::parse($slot['start_time']);
            $endTime   = $startTime->copy()->addMinutes($classTime);

            if ($endTime->gt(Carbon::parse('23:59'))) {
                $failedSlots[] = [
                    'start_time' => $startTime->format('H:i'),
                    'message' => 'Exceeds day limit'
                ];
                continue;
            }

            $overlap = collect($createdSlots)->contains(function ($existing) use ($startTime, $endTime) {
                return $existing->start_time < $endTime->format('H:i:s') &&
                    $existing->end_time > $startTime->format('H:i:s');
            });

            if ($overlap) {
                $failedSlots[] = [
                    'start_time' => $startTime->format('H:i'),
                    'message' => 'Overlaps within request'
                ];
                continue;
            }

            $slotModel = TeacherAvailability::create([
                'teacher_id' => $teacherId,
                'day_of_month' => $dayOfMonth,
                'start_time' => $startTime->format('H:i:s'),
                'end_time' => $endTime->format('H:i:s'),
            ]);

            $createdSlots[] = $slotModel;
        }

        return response()->json([
            'success' => true,
            'message' => 'Slots updated successfully',
            'created' => $createdSlots,
            'failed' => $failedSlots,
            'summary' => [
                'total' => count($validated['slots']),
                'created' => count($createdSlots),
                'failed' => count($failedSlots),
            ]
        ]);
    }

    public function destroyByDate(Request $request)
    {

        $user = auth('api')->user();

        if ($user->hasRole('teacher')) {
            $teacherId = $user->teacher->id;
        } else {
            $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
            ]);
            $teacherId = $request->teacher_id;
        }
        $request->validate([
            'date' => 'required|date',
        ]);

        $teacherId = $request->teacher_id;

        $dayOfMonth = Carbon::parse($request->date)->day;

        $deleted = TeacherAvailability::where('teacher_id', $teacherId)
            ->where('day_of_month', $dayOfMonth)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "All slots deleted for the day {$dayOfMonth}",
            'deleted_count' => $deleted
        ]);
    }


}
