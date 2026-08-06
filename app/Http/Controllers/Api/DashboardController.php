<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    /** Get monthly student registration totals for a year. */
    public function totalStudentMonthly(Request $request): JsonResponse
    {
        $year = $request->year ?? now()->year;
        $result = $this->dashboard->totalStudentMonthly((int) $year);

        return response()->json([
            'success' => true,
            'message' => 'Student monthly data retrieved successfully',
            'year' => $result['year'],
            'data' => $result['data'],
        ]);
    }

    /** Get monthly enrollment totals for a year. */
    public function totalEnrollmentMonthly(Request $request): JsonResponse
    {
        $year = $request->year ?? now()->year;
        $result = $this->dashboard->totalEnrollmentMonthly((int) $year);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment monthly data retrieved successfully',
            'year' => $result['year'],
            'total_enrollments' => $result['total_enrollments'],
            'data' => $result['data'],
        ]);
    }

    /** Get admin revenue and occupancy statistics. */
    public function revenueStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Revenue statistics retrieved successfully',

            'data' => $this->dashboard->revenueStats(),
        ]);
    }

    /** Get dashboard statistics and summaries for a teacher. */
    public function teacherDashboard(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->dashboard->teacherDashboard($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Teacher dashboard data retrieved successfully',

            'data' => [
                'statistics' => $data['statistics'],
                'upcoming_batches' => $data['upcoming_batches'],
                'top_batches' => $data['top_batches'],
            ],
        ]);
    }

    /** Get dashboard statistics and summaries for a student. */
    public function studentDashboard(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->dashboard->studentDashboard($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Student dashboard retrieved successfully',

            'data' => [
                'statistics' => $data['statistics'],
                'active_courses' => $data['active_courses'],
                'recent_enrollments' => $data['recent_enrollments'],
                'completed_courses' => $data['completed_courses'],
                'recent_graded_assignments' => $data['recent_graded_assignments'],
                'recent_activity_notes' => $data['recent_activity_notes'],
            ],
        ]);
    }
}
