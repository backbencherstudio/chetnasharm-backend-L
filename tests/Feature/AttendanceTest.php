<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

function setupAttendanceContext(): array
{
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
    ]);

    $class = ClassModel::create([
        'title' => 'Attendance Class',
        'description' => 'Desc',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Attendance Batch',
        'total_seat' => 10,
        'filled_seat' => 1,
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->addDays(25)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
    ]);

    $student = User::factory()->create(['name' => 'Test Student']);
    $student->assignRole('student');

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'class_id' => $class->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    return [$teacherUser, $teacher, $batch, $student];
}

test('teacher can fetch attendance sheet and monthly attendance for assigned batch', function (): void {
    [$teacherUser, $teacher, $batch, $student] = setupAttendanceContext();
    $token = auth('api')->login($teacherUser);

    $date = now()->toDateString();
    $month = now()->format('Y-m');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/attendances/{$batch->id}?date={$date}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/attendance-monthly/{$batch->id}?month={$month}")
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('teacher can store and update single attendance', function (): void {
    [$teacherUser, $teacher, $batch, $student] = setupAttendanceContext();
    $token = auth('api')->login($teacherUser);

    $date = now()->toDateString();

    // Store attendance
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/attendance-save', [
            'batch_id' => $batch->id,
            'class_date' => $date,
            'attendances' => [
                [
                    'user_id' => $student->id,
                    'status' => 'present',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    // Update single attendance
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/attendance-single', [
            'batch_id' => $batch->id,
            'user_id' => $student->id,
            'class_date' => $date,
            'status' => 'absent',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'absent');
});

test('unauthorized teacher cannot access attendance of another batch', function (): void {
    [$teacherUser, $teacher, $batch] = setupAttendanceContext();

    $otherUser = User::factory()->create();
    $otherUser->assignRole('teacher');
    Teacher::create(['user_id' => $otherUser->id]);

    $otherToken = auth('api')->login($otherUser);

    $this->withHeader('Authorization', "Bearer {$otherToken}")
        ->getJson("/api/attendances/{$batch->id}?date=".now()->toDateString())
        ->assertForbidden();
});
