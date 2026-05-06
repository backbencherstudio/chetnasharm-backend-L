<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

}
