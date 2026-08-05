<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'teacher', 'guard_name' => 'api']);
});

test('landing batches include teacher country and timezone', function () {
    $user = User::factory()->create();
    $user->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $user->id,
        'country' => 'Bangladesh',
        'timezone' => 'Asia/Dhaka',
    ]);

    $class = ClassModel::create([
        'title' => 'Spoken English',
        'description' => 'Desc',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Morning Batch',
        'total_seat' => 10,
        'filled_seat' => 0,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
    ]);

    $this->getJson("/api/batches/{$class->id}")
        ->assertOk()
        ->assertJsonPath('data.0.teacher.id', $teacher->id)
        ->assertJsonPath('data.0.teacher.country', 'Bangladesh')
        ->assertJsonPath('data.0.teacher.timezone', 'Asia/Dhaka');
});

test('single batch includes teacher country and timezone', function () {
    $user = User::factory()->create();
    $user->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $user->id,
        'country' => 'Bangladesh',
        'timezone' => 'Asia/Dhaka',
    ]);

    $class = ClassModel::create([
        'title' => 'Spoken English',
        'description' => 'Desc',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Morning Batch',
        'total_seat' => 10,
        'filled_seat' => 0,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
    ]);

    $this->getJson("/api/single-batch/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('data.teacher.country', 'Bangladesh')
        ->assertJsonPath('data.teacher.timezone', 'Asia/Dhaka');
});
