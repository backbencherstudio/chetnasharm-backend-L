<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Teacher;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function totalStudentMonthly(Request $request)
    {
        $year = $request->year ?? now()->year;

        $students = User::whereHas('roles', function ($query) {
                $query->where('name', 'student');
            })
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupByRaw('MONTH(created_at)')
            ->pluck('count', 'month');

        $monthlyData = collect(range(1, 12))->map(function ($month) use ($students) {
            return [
                'month' => $month,
                'count' => $students[$month] ?? 0,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Student monthly data retrieved successfully',
            'year' => (int) $year,
            'data' => $monthlyData,
        ]);
    }

    public function totalEnrollmentMonthly(Request $request)
    {
        $year = $request->year ?? now()->year;

        $enrollments = Enrollment::whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupByRaw('MONTH(created_at)')
            ->pluck('count', 'month');

        $monthlyData = collect(range(1, 12))->map(function ($month) use ($enrollments) {
            return [
                'month' => $month,
                'count' => $enrollments[$month] ?? 0,
            ];
        });

        $totalEnrollments = $monthlyData->sum('count');

        return response()->json([
            'success' => true,
            'message' => 'Enrollment monthly data retrieved successfully',
            'year' => (int) $year,
            'total_enrollments' => $totalEnrollments,
            'data' => $monthlyData,
        ]);
    }

    // public function totalBatchEnrolled()
    // {
    //     $totalBatch = Batch::count();
    //     $totalEnrolled = Batch::sum('filled_seat');

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Batch statistics retrieved successfully',
    //         'total_batch' => $totalBatch,
    //         'total_enrolled' => $totalEnrolled,
    //     ]);
    // }

    public function revenueStats()
    {
        $totalBatches = Batch::count();
        $totalRevenue = Batch::join('classes', 'batches.class_id', '=', 'classes.id')
            ->selectRaw('SUM(batches.filled_seat * classes.price) as total')
            ->value('total');

        $potentialRevenue = Batch::join('classes', 'batches.class_id', '=', 'classes.id')
            ->selectRaw('SUM(batches.total_seat * classes.price) as total')
            ->value('total');

        $lostRevenue = Batch::join('classes', 'batches.class_id', '=', 'classes.id')
            ->selectRaw('SUM((batches.total_seat - batches.filled_seat) * classes.price) as total')
            ->value('total');

        $averageRevenuePerBatch = Batch::join('classes', 'batches.class_id', '=', 'classes.id')
            ->selectRaw('AVG(batches.filled_seat * classes.price) as average')
            ->value('average');

        // Top earning classes
        $topClasses = ClassModel::join('batches', 'classes.id', '=', 'batches.class_id')
            ->select(
                'classes.id',
                'classes.title',
                DB::raw('SUM(batches.filled_seat * classes.price) as revenue'),
                DB::raw('SUM(batches.filled_seat) as total_students'),
                DB::raw('COUNT(batches.id) as total_batches')
            )
            ->groupBy('classes.id', 'classes.title')
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'title' => $class->title,
                    'revenue' => round($class->revenue, 2),
                    'total_students' => (int) $class->total_students,
                    'total_batches' => (int) $class->total_batches,
                ];
            });

        // Top earning batches
        $topBatches = Batch::join('classes', 'batches.class_id', '=', 'classes.id')
            ->leftJoin('teachers', 'batches.teacher_id', '=', 'teachers.id')
            ->leftJoin('users', 'teachers.user_id', '=', 'users.id')
            ->select(
                'batches.id',
                'batches.name',
                'users.name as teacher_name',
                DB::raw('(batches.filled_seat * classes.price) as revenue')
            )
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'teacher_name' => $batch->teacher_name,
                    'revenue' => round($batch->revenue, 2),
                ];
            });

        $seatStats = Batch::selectRaw('
                SUM(total_seat) as total_seats,
                SUM(filled_seat) as filled_seats
            ')
            ->first();

        $occupancyRate = 0;

        if ($seatStats->total_seats > 0) {
            $occupancyRate = round(
                ($seatStats->filled_seats / $seatStats->total_seats) * 100,
                2
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Revenue statistics retrieved successfully',

            'data' => [

                'total_batches' => $totalBatches,
                'total_revenue' => round($totalRevenue ?? 0, 2),
                'potential_revenue' => round($potentialRevenue ?? 0, 2),
                'lost_revenue' => round($lostRevenue ?? 0, 2),
                'average_revenue_per_batch' => round($averageRevenuePerBatch ?? 0, 2),
                'seat_occupancy_rate' => $occupancyRate . '%',
                'total_seats' => (int) ($seatStats->total_seats ?? 0),
                'filled_seats' => (int) ($seatStats->filled_seats ?? 0),
                'top_earning_classes' => $topClasses,
                'top_earning_batches' => $topBatches,
            ]
        ]);
    }

    public function teacherDashboard()
    {
        $user = auth('api')->user();

        $teacher = Teacher::where('user_id', $user->id)->firstOrFail();

        $batches = Batch::where('teacher_id', $teacher->id);

        // Statistics
        $totalBatches = (clone $batches)->count();

        $activeBatches = (clone $batches)
            ->where('active_status', 1)
            ->count();

        $completedBatches = (clone $batches)
            ->whereDate('end_date', '<', now())
            ->count();

        $totalStudents = (clone $batches)
            ->sum('filled_seat');

        $totalSeats = (clone $batches)
            ->sum('total_seat');

        $occupancyRate = $totalSeats > 0
            ? round(($totalStudents / $totalSeats) * 100, 2)
            : 0;

        // Revenue generated
        $totalRevenue = Batch::join('classes', 'batches.class_id', '=', 'classes.id')
            ->where('batches.teacher_id', $teacher->id)
            ->selectRaw('SUM(batches.filled_seat * classes.price) as total')
            ->value('total');

        // Upcoming classes/batches
        $upcomingClasses = Batch::with('class:id,title')
            ->where('teacher_id', $teacher->id)
            ->whereDate('start_date', '>=', now())
            ->orderBy('start_date')
            ->take(5)
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_name' => $batch->name,
                    'class_title' => optional($batch->class)->title,
                    'start_date' => $batch->start_date,
                    'end_date' => $batch->end_date,
                    'filled_seat' => $batch->filled_seat,
                    'total_seat' => $batch->total_seat,
                ];
            });

        // Top performing batches
        $topBatches = Batch::join('classes', 'batches.class_id', '=', 'classes.id')
            ->where('batches.teacher_id', $teacher->id)
            ->select(
                'batches.id',
                'batches.name',
                'batches.filled_seat',
                'batches.total_seat',
                DB::raw('(batches.filled_seat * classes.price) as revenue')
            )
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'filled_seat' => (int) $batch->filled_seat,
                    'total_seat' => (int) $batch->total_seat,
                    'revenue' => round($batch->revenue, 2),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Teacher dashboard data retrieved successfully',

            'data' => [
                'statistics' => [
                    'total_batches' => $totalBatches,
                    'active_batches' => $activeBatches,
                    'completed_batches' => $completedBatches,
                    'total_students' => $totalStudents,
                    'total_seats' => $totalSeats,
                    'seat_occupancy_rate' => $occupancyRate . '%',
                    'total_revenue_generated' => round($totalRevenue ?? 0, 2),
                ],

                'upcoming_batches' => $upcomingClasses,
                'top_batches' => $topBatches,
            ]
        ]);
    }

    public function studentDashboard()
    {
        $user = auth('api')->user();

        $enrollments = Enrollment::with([
            'class:id,title,image,price,duration_in_days,total_classes',
            'batch:id,name,start_date,end_date,zoom_link,total_seat,filled_seat'
        ])->where('user_id', $user->id);

        $totalEnrollments = (clone $enrollments)->count();

        $activeCourses = Enrollment::where('user_id', $user->id)
            ->whereHas('batch', function ($q) {
                $q->whereDate('end_date', '>=', now());
            })
            ->count();

        $completedCourses = (clone $enrollments)
            ->whereDate('expiry_date', '<', now())
            ->count();

        $totalSpent = Enrollment::join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->where('enrollments.user_id', $user->id)
            ->sum('classes.price');

        $activeCourseList = Enrollment::with([
            'class:id,title,price',
            'batch:id,name,start_date,end_date,total_seat,filled_seat'
        ])
            ->where('user_id', $user->id)
            ->whereHas('batch', function ($q) {
                $q->whereDate('end_date', '>=', now());
            })
            ->latest()
            ->get()
            ->map(function ($enrollment) {

                $batch = $enrollment->batch;

                $progress = 0;

                if ($batch && $batch->start_date && $batch->end_date) {

                    $totalDays = $batch->start_date->diffInDays($batch->end_date);

                    $passedDays = $batch->start_date->diffInDays(now(), false);

                    if ($totalDays > 0) {
                        $progress = min(
                            100,
                            max(0, round(($passedDays / $totalDays) * 100))
                        );
                    }
                }

                return [
                    'enrollment_id' => $enrollment->id,

                    'class_title' => optional($enrollment->class)->title,

                    'batch_name' => optional($batch)->name,

                    'start_date' => optional($batch)->start_date,
                    'end_date' => optional($batch)->end_date,

                    'progress_percent' => $progress . '%',

                    'expiry_date' => $enrollment->expiry_date,
                ];
            });

        // Recent enrollments
        $recentEnrollments = Enrollment::with([
            'class:id,title,image',
            'batch:id,name'
        ])
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,

                    'class_title' => optional($enrollment->class)->title,

                    'batch_name' => optional($enrollment->batch)->name,

                    'status' => $enrollment->status,

                    'enrolled_at' => $enrollment->enrolled_at,
                ];
            });

        // Completed courses
        $completedCourseList = Enrollment::with([
            'class:id,title,image',
            'batch:id,name,end_date'
        ])
            ->where('user_id', $user->id)
            ->whereHas('batch', function ($q) {
                $q->whereDate('end_date', '<', now());
            })
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,

                    'class_title' => optional($enrollment->class)->title,

                    'batch_name' => optional($enrollment->batch)->name,

                    'completed_at' => optional($enrollment->batch)->end_date,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Student dashboard retrieved successfully',

            'data' => [

                'statistics' => [
                    'total_enrollments' => $totalEnrollments,
                    'active_courses' => $activeCourses,
                    'completed_courses' => $completedCourses,
                    'total_spent' => round($totalSpent ?? 0, 2),
                ],
                'active_courses' => $activeCourseList,
                'recent_enrollments' => $recentEnrollments,
                'completed_courses' => $completedCourseList,
            ]
        ]);
    }

}
