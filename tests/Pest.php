<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * @return array{0: Batch, 1: User}
 */
function createBatchWithStudent(int $filledSeat = 0): array
{
    $teacherUser = User::factory()->create(['name' => 'Teacher One']);
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
    ]);

    $class = ClassModel::create([
        'title' => 'Test Class',
        'description' => 'Desc',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Batch A',
        'total_seat' => 10,
        'filled_seat' => $filledSeat,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
        'zoom_link' => 'https://zoom.example/test',
    ]);

    $student = User::factory()->create();
    $student->assignRole('student');

    return [$batch, $student];
}
