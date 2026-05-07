<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
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

    public function totalBatchEnrolled()
    {
        $totalBatch = Batch::count();
        $totalEnrolled = Batch::sum('filled_seat');

        return response()->json([
            'success' => true,
            'message' => 'Batch statistics retrieved successfully',
            'total_batch' => $totalBatch,
            'total_enrolled' => $totalEnrolled,
        ]);
    }

    public function revenueStats()
    {
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

}
