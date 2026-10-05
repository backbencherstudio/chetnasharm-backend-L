<?php

use App\Models\Setting;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }

    Setting::create([
        'class_time' => 60,
    ]);
});

function setupAvailabilityContext(): array
{
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
    ]);

    return [$teacherUser, $teacher];
}

test('teacher can store, fetch, edit, update and delete availability slots', function (): void {
    [$teacherUser, $teacher] = setupAvailabilityContext();
    $token = auth('api')->login($teacherUser);

    // Store availability slots
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/teacher-availability', [
            'day_of_week' => 1,
            'slots' => [
                ['start_time' => '09:00'],
                ['start_time' => '10:00'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(TeacherAvailability::where('teacher_id', $teacher->id)->count())->toBe(2);

    // Index
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher-availability')
        ->assertOk()
        ->assertJsonPath('success', true);

    // Edit
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher-availability/edit?day_of_week=1')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data');

    // Update / Sync
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/teacher-availability/update', [
            'day_of_week' => 1,
            'slots' => [
                ['start_time' => '09:00'],
                ['start_time' => '11:00'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    // Delete single slot
    $slot = TeacherAvailability::where('teacher_id', $teacher->id)->first();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/teacher-availability/{$slot->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(TeacherAvailability::find($slot->id))->toBeNull();
});

test('teacher can view schedule and cannot view another teachers schedule', function (): void {
    [$teacherUser, $teacher] = setupAvailabilityContext();
    $token = auth('api')->login($teacherUser);

    $otherUser = User::factory()->create();
    $otherUser->assignRole('teacher');
    $otherTeacher = Teacher::create(['user_id' => $otherUser->id]);

    $startDate = now()->toDateString();
    $endDate = now()->addDays(7)->toDateString();

    // View own schedule
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/teachers-schedule?teacher_id={$teacher->id}&start_date={$startDate}&end_date={$endDate}")
        ->assertOk()
        ->assertJsonPath('success', true);

    // Attempt to view other teacher schedule
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/teachers-schedule?teacher_id={$otherTeacher->id}&start_date={$startDate}&end_date={$endDate}")
        ->assertForbidden();
});
