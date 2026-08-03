<?php

use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'teacher', 'guard_name' => 'api']);
});

test('public teacher show returns batches with class and schedules', function () {
    $user = User::factory()->create(['email' => 'teacher-show@example.com']);
    $user->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $user->id,
        'name' => 'Aisha Khan',
        'email' => 'teacher-show@example.com',
        'bio' => 'IELTS coach',
        'expertise' => 'Speaking',
        'qualification' => 'CELTA',
        'years_of_exp' => 5,
        'suspend_status' => 0,
        'is_top' => 1,
    ]);

    $class = ClassModel::create([
        'title' => 'Spoken English',
        'description' => 'Full course',
        'short_description' => 'Speak better',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 12,
        'is_active' => 1,
    ]);

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Morning Batch',
        'total_seat' => 10,
        'filled_seat' => 2,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
    ]);

    BatchSchedule::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'day_of_week' => 1,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);

    $this->getJson("/api/teachers/{$teacher->id}")
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.id', $teacher->id)
        ->assertJsonPath('data.name', 'Aisha Khan')
        ->assertJsonPath('data.batches.0.name', 'Morning Batch')
        ->assertJsonPath('data.batches.0.class.title', 'Spoken English')
        ->assertJsonPath('data.batches.0.schedules.0.day', 'Monday')
        ->assertJsonMissingPath('data.availability')
        ->assertJsonMissingPath('data.classes')
        ->assertJsonMissingPath('data.email');
});

test('public teacher show hides suspended teachers', function () {
    $user = User::factory()->create(['email' => 'suspended-teacher@example.com']);
    $user->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $user->id,
        'name' => 'Suspended Teacher',
        'email' => 'suspended-teacher@example.com',
        'suspend_status' => 1,
    ]);

    $this->getJson("/api/teachers/{$teacher->id}")
        ->assertNotFound()
        ->assertJsonPath('status', false);
});
