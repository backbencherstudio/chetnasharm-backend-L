<?php

namespace App\Http\Controllers;

use App\Models\TeacherAvailability;
use Illuminate\Http\Request;
use Carbon\Carbon;

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
            'slots.*.end_time' => 'required|after:slots.*.start_time',
        ]);

        $teacherId = auth('api')->user()->teacher->id;

        $createdSlots = [];

        foreach ($validated['slots'] as $slot) {

            $exists = TeacherAvailability::where('teacher_id', $teacherId)
                ->where('date', $validated['date'])
                ->where(function ($q) use ($slot) {
                    $q->whereBetween('start_time', [$slot['start_time'], $slot['end_time']])
                    ->orWhereBetween('end_time', [$slot['start_time'], $slot['end_time']])
                    ->orWhere(function ($q2) use ($slot) {
                        $q2->where('start_time', '<=', $slot['start_time'])
                            ->where('end_time', '>=', $slot['end_time']);
                    });
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Time slot {$slot['start_time']} - {$slot['end_time']} overlaps"
                ], 422);
            }

            $createdSlots[] = TeacherAvailability::create([
                'teacher_id' => $teacherId,
                'date' => $validated['date'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Slots created successfully',
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
