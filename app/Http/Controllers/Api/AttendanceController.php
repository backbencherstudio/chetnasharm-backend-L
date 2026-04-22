<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceController extends Controller
{
    public function getAttendanceSheet(Request $request, $batchId)
    {
        $user   = auth('api')->user();
        $date   = $request->query('date');
        $search = $request->query('search');

        if (!$date) {
            return response()->json([
                'success' => false,
                'message' => 'Date is required'
            ], 422);
        }

        if (!$user->hasRole('admin')) {
            $isTeacher = Batch::where('id', $batchId)
                ->where('teacher_id', $user->teacher->id ?? 0)
                ->exists();

            if (!$isTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        $query = Enrollment::with('user:id,name,email')
            ->where('batch_id', $batchId)
            ->where('status', 'active');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $enrollments = $query->get();

        $attendanceMap = Attendance::where('batch_id', $batchId)
            ->whereDate('class_date', $date)
            ->pluck('status', 'user_id');

        $data = $enrollments->map(function ($enrollment) use ($attendanceMap) {
            $user = $enrollment->user;

            return [
                'user_id' => $user->id,
                'name'    => $user->name,
                'email'   => $user->email,
                'status'  => $attendanceMap[$user->id] ?? 'absent'
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Attendance sheet fetched',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();

        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'class_date' => 'required|date',
            'attendances' => 'required|array',
            'attendances.*.user_id' => 'required|exists:users,id',
            'attendances.*.status' => 'required|in:present,absent',
        ]);

        if (!$user->hasRole('admin')) {
            $isTeacher = Batch::where('id', $request->batch_id)
                ->where('teacher_id', $user->teacher->id ?? 0)
                ->exists();

            if (!$isTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        foreach ($request->attendances as $item) {

            $isEnrolled = Enrollment::where('batch_id', $request->batch_id)
                ->where('user_id', $item['user_id'])
                ->exists();

            if (!$isEnrolled) continue;

            Attendance::updateOrCreate(
                [
                    'batch_id'   => $request->batch_id,
                    'user_id'    => $item['user_id'],
                    'class_date' => $request->class_date,
                ],
                [
                    'status' => $item['status']
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance saved successfully'
        ]);
    }

    public function updateSingle(Request $request)
    {
        $user = auth('api')->user();

        $request->validate([
            'batch_id'   => 'required|exists:batches,id',
            'user_id'    => 'required|exists:users,id',
            'class_date' => 'required|date',
            'status'     => 'required|in:present,absent',
        ]);

        if (!$user->hasRole('admin')) {
            $isTeacher = Batch::where('id', $request->batch_id)
                ->where('teacher_id', $user->teacher->id ?? 0)
                ->exists();

            if (!$isTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        $isEnrolled = Enrollment::where('batch_id', $request->batch_id)
            ->where('user_id', $request->user_id)
            ->exists();

        if (!$isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'Student not enrolled in this batch'
            ], 422);
        }

        $attendance = Attendance::updateOrCreate(
            [
                'batch_id'   => $request->batch_id,
                'user_id'    => $request->user_id,
                'class_date' => $request->class_date,
            ],
            [
                'status' => $request->status
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendance updated successfully',
            'data' => $attendance
        ]);
    }

    public function getMonthlyAttendance(Request $request, $batchId)
    {
        $user = auth('api')->user();

        $month = $request->query('month');

        if (!$month) {
            return response()->json([
                'success' => false,
                'message' => 'Month is required (format: YYYY-MM)'
            ], 422);
        }

        if (!$user->hasRole('admin')) {
            $isTeacher = Batch::where('id', $batchId)
                ->where('teacher_id', $user->teacher->id ?? 0)
                ->exists();

            if (!$isTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end   = Carbon::parse($month . '-01')->endOfMonth();

        $attendanceDates = Attendance::where('batch_id', $batchId)
            ->whereBetween('class_date', [$start, $end])
            ->selectRaw('DATE(class_date) as date')
            ->distinct()
            ->pluck('date')
            ->toArray();

        $period = CarbonPeriod::create($start, $end);

        $data = [];

        foreach ($period as $date) {
            $formattedDate = $date->toDateString();

            $data[] = [
                'date' => $formattedDate,
                'has_status' => in_array($formattedDate, $attendanceDates) ? 1 : 0
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Monthly attendance fetched',
            'data' => $data
        ]);
    }

}
