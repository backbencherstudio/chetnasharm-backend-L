<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use App\Models\User;

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

}
