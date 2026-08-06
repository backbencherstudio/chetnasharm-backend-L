<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\BatchAssignment;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\StudentActivityNote;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array{year: int, data: Collection<int, array{month: int, count: int}>}
     */
    public function totalStudentMonthly(?int $year = null): array
    {
        $year = $year ?? now()->year;

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

        return [
            'year' => (int) $year,
            'data' => $monthlyData,
        ];
    }

    /**
     * @return array{year: int, total_enrollments: int, data: Collection<int, array{month: int, count: int}>}
     */
    public function totalEnrollmentMonthly(?int $year = null): array
    {
        $year = $year ?? now()->year;

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

        return [
            'year' => (int) $year,
            'total_enrollments' => $monthlyData->sum('count'),
            'data' => $monthlyData,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revenueStats(): array
    {
        $revenueAggregates = Batch::join('classes', 'batches.class_id', '=', 'classes.id')
            ->selectRaw('
                COUNT(batches.id) as total_batches,
                SUM(batches.filled_seat * classes.price) as total_revenue,
                SUM(batches.total_seat * classes.price) as potential_revenue,
                SUM((batches.total_seat - batches.filled_seat) * classes.price) as lost_revenue,
                AVG(batches.filled_seat * classes.price) as average_revenue,
                SUM(batches.total_seat) as total_seats,
                SUM(batches.filled_seat) as filled_seats
            ')
            ->first();

        $totalBatches = (int) ($revenueAggregates->total_batches ?? 0);
        $totalRevenue = $revenueAggregates->total_revenue ?? 0;
        $potentialRevenue = $revenueAggregates->potential_revenue ?? 0;
        $lostRevenue = $revenueAggregates->lost_revenue ?? 0;
        $averageRevenuePerBatch = $revenueAggregates->average_revenue ?? 0;

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

        $totalSeats = (int) ($revenueAggregates->total_seats ?? 0);
        $filledSeats = (int) ($revenueAggregates->filled_seats ?? 0);

        $occupancyRate = 0;

        if ($totalSeats > 0) {
            $occupancyRate = round(
                ($filledSeats / $totalSeats) * 100,
                2
            );
        }

        return [
            'total_batches' => $totalBatches,
            'total_revenue' => round($totalRevenue ?? 0, 2),
            'potential_revenue' => round($potentialRevenue ?? 0, 2),
            'lost_revenue' => round($lostRevenue ?? 0, 2),
            'average_revenue_per_batch' => round($averageRevenuePerBatch ?? 0, 2),
            'seat_occupancy_rate' => $occupancyRate.'%',
            'total_seats' => $totalSeats,
            'filled_seats' => $filledSeats,
            'top_earning_classes' => $topClasses,
            'top_earning_batches' => $topBatches,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function teacherDashboard(int $userId): array
    {
        $teacher = Teacher::where('user_id', $userId)->firstOrFail();

        $stats = Batch::where('teacher_id', $teacher->id)
            ->selectRaw('
                COUNT(*) as total_batches,
                SUM(CASE WHEN active_status = 1 THEN 1 ELSE 0 END) as active_batches,
                SUM(CASE WHEN end_date < ? THEN 1 ELSE 0 END) as completed_batches,
                SUM(filled_seat) as total_students,
                SUM(total_seat) as total_seats
            ', [now()->toDateString()])
            ->first();

        $totalBatches = (int) ($stats->total_batches ?? 0);
        $activeBatches = (int) ($stats->active_batches ?? 0);
        $completedBatches = (int) ($stats->completed_batches ?? 0);
        $totalStudents = (int) ($stats->total_students ?? 0);
        $totalSeats = (int) ($stats->total_seats ?? 0);

        $occupancyRate = $totalSeats > 0
            ? round(($totalStudents / $totalSeats) * 100, 2)
            : 0;

        $totalRevenue = Batch::join('classes', 'batches.class_id', '=', 'classes.id')
            ->where('batches.teacher_id', $teacher->id)
            ->selectRaw('SUM(batches.filled_seat * classes.price) as total')
            ->value('total');

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

        return [
            'statistics' => [
                'total_batches' => $totalBatches,
                'active_batches' => $activeBatches,
                'completed_batches' => $completedBatches,
                'total_students' => $totalStudents,
                'total_seats' => $totalSeats,
                'seat_occupancy_rate' => $occupancyRate.'%',
                'total_revenue_generated' => round($totalRevenue ?? 0, 2),
            ],
            'upcoming_batches' => $upcomingClasses,
            'top_batches' => $topBatches,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function studentDashboard(int $userId): array
    {
        $today = now()->startOfDay();

        $enrollments = Enrollment::with([
            'class:id,title,image,price',
            'batch:id,name,start_date,end_date,total_seat,filled_seat',
        ])
            ->where('user_id', $userId)
            ->latest()
            ->get();

        $totalEnrollments = $enrollments->count();

        $activeEnrollments = $enrollments->filter(
            fn ($enrollment) => $enrollment->batch
                && $enrollment->batch->end_date
                && $enrollment->batch->end_date->copy()->startOfDay()->gte($today)
        );

        $completedCourses = $enrollments
            ->filter(
                fn ($enrollment) => $enrollment->expiry_date
                    && $enrollment->expiry_date->copy()->startOfDay()->lt($today)
            )
            ->count();

        $totalSpent = $enrollments->sum(
            fn ($enrollment) => (float) (optional($enrollment->class)->price ?? 0)
        );

        $activeCourseList = $activeEnrollments
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
                    'progress_percent' => $progress.'%',
                    'expiry_date' => $enrollment->expiry_date,
                ];
            })
            ->values();

        $recentEnrollments = $enrollments
            ->take(5)
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'class_title' => optional($enrollment->class)->title,
                    'batch_name' => optional($enrollment->batch)->name,
                    'status' => $enrollment->status,
                    'enrolled_at' => $enrollment->enrolled_at,
                ];
            })
            ->values();

        $completedCourseList = $enrollments
            ->filter(
                fn ($enrollment) => $enrollment->batch
                    && $enrollment->batch->end_date
                    && $enrollment->batch->end_date->copy()->startOfDay()->lt($today)
            )
            ->take(5)
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'class_title' => optional($enrollment->class)->title,
                    'batch_name' => optional($enrollment->batch)->name,
                    'completed_at' => optional($enrollment->batch)->end_date,
                ];
            })
            ->values();

        $activeBatchIds = $enrollments
            ->where('status', 'active')
            ->pluck('batch_id')
            ->filter()
            ->unique()
            ->values();

        $pendingAssignments = 0;

        if ($activeBatchIds->isNotEmpty()) {
            $pendingAssignments = BatchAssignment::query()
                ->active()
                ->whereIn('batch_id', $activeBatchIds)
                ->whereDoesntHave('submissions', function ($query) use ($userId) {
                    $query->where('student_user_id', $userId);
                })
                ->count();
        }

        $recentGradedAssignments = AssignmentSubmission::query()
            ->where('student_user_id', $userId)
            ->whereNotNull('graded_at')
            ->with([
                'assignment:id,title,batch_id,total_marks',
                'assignment.batch:id,name',
            ])
            ->latest('graded_at')
            ->limit(5)
            ->get()
            ->map(fn (AssignmentSubmission $submission) => [
                'submission_id' => $submission->id,
                'assignment_id' => $submission->assignment_id,
                'title' => $submission->assignment?->title,
                'batch_name' => $submission->assignment?->batch?->name,
                'obtained_marks' => $submission->obtained_marks,
                'total_marks' => $submission->assignment?->total_marks,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at,
            ])
            ->values();

        $recentActivityNotes = StudentActivityNote::query()
            ->where('student_user_id', $userId)
            ->with([
                'batch:id,name',
                'teacher:id,user_id',
                'teacher.user:id,name',
            ])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (StudentActivityNote $note) => [
                'id' => $note->id,
                'status' => $note->status,
                'comment' => $note->comment,
                'batch_name' => $note->batch?->name,
                'teacher_name' => $note->teacher?->name,
                'created_at' => $note->created_at,
            ])
            ->values();

        return [
            'statistics' => [
                'total_enrollments' => $totalEnrollments,
                'active_courses' => $activeEnrollments->count(),
                'completed_courses' => $completedCourses,
                'total_spent' => round($totalSpent, 2),
                'pending_assignments' => $pendingAssignments,
            ],
            'active_courses' => $activeCourseList,
            'recent_enrollments' => $recentEnrollments,
            'completed_courses' => $completedCourseList,
            'recent_graded_assignments' => $recentGradedAssignments,
            'recent_activity_notes' => $recentActivityNotes,
        ];
    }
}
