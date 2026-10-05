<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /** Get monthly student registration totals for a year. */
    public function totalStudentMonthly(Request $request): JsonResponse
    {
        $year = $request->year ?? now()->year;
        $result = $this->dashboard->totalStudentMonthly((int) $year);

        return $this->success(
            data: $result['data'],
            message: 'Student monthly data retrieved successfully',
            extra: ['year' => $result['year']]
        );
    }

    /** Get monthly enrollment totals for a year. */
    public function totalEnrollmentMonthly(Request $request): JsonResponse
    {
        $year = $request->year ?? now()->year;
        $result = $this->dashboard->totalEnrollmentMonthly((int) $year);

        return $this->success(
            data: $result['data'],
            message: 'Enrollment monthly data retrieved successfully',
            extra: [
                'year' => $result['year'],
                'total_enrollments' => $result['total_enrollments'],
            ]
        );
    }

    /** Get admin revenue and occupancy statistics. */
    public function revenueStats(): JsonResponse
    {
        return $this->success(
            $this->dashboard->revenueStats(),
            'Revenue statistics retrieved successfully'
        );
    }

    /** Get dashboard statistics and summaries for a teacher. */
    public function teacherDashboard(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->dashboard->teacherDashboard($user->id);

        return $this->success([
            'statistics' => $data['statistics'],
            'upcoming_batches' => $data['upcoming_batches'],
            'top_batches' => $data['top_batches'],
        ], 'Teacher dashboard data retrieved successfully');
    }

    /** Get dashboard statistics and summaries for a student. */
    public function studentDashboard(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->dashboard->studentDashboard($user->id);

        return $this->success([
            'statistics' => $data['statistics'],
            'active_courses' => $data['active_courses'],
            'recent_enrollments' => $data['recent_enrollments'],
            'completed_courses' => $data['completed_courses'],
            'recent_graded_assignments' => $data['recent_graded_assignments'],
            'recent_activity_notes' => $data['recent_activity_notes'],
        ], 'Student dashboard retrieved successfully');
    }
}
