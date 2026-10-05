<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

function setupDashboardContext(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');
    $teacher = Teacher::create(['user_id' => $teacherUser->id]);

    $class = ClassModel::create([
        'title' => 'Dashboard Class',
        'description' => 'Desc',
        'price' => 150,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Dashboard Batch',
        'total_seat' => 20,
        'filled_seat' => 5,
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->addDays(28)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
    ]);

    $student = User::factory()->create(['name' => 'Dashboard Student']);
    $student->assignRole('student');

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'class_id' => $class->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    return [$admin, $teacherUser, $student, $batch];
}

test('admin can access revenue stats and monthly totals', function () {
    [$admin, $teacherUser, $student, $batch] = setupDashboardContext();
    $adminToken = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/admin/revenue-stats')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_batches', 1)
        ->assertJsonPath('data.total_seats', 20)
        ->assertJsonPath('data.filled_seats', 5);

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/admin/total-student-per-month')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/admin/total-enrollment-per-month')
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('teacher can access teacher dashboard', function () {
    [$admin, $teacherUser, $student, $batch] = setupDashboardContext();
    $teacherToken = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$teacherToken}")
        ->getJson('/api/teacher/dashboard')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'statistics',
                'upcoming_batches',
                'top_batches',
            ],
        ]);
});

test('student can access student dashboard', function () {
    [$admin, $teacherUser, $student, $batch] = setupDashboardContext();
    $studentToken = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson('/api/student/dashboard')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'statistics',
                'active_courses',
                'recent_enrollments',
            ],
        ]);
});
