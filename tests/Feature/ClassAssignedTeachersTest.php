<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'teacher', 'guard_name' => 'api']);
});

/**
 * @return array{0: ClassModel, 1: Teacher, 2: Teacher, 3: Batch, 4: Batch, 5: Batch}
 */
function createClassWithBatchTeachers(): array
{
    $teacherUserOne = User::factory()->create();
    $teacherUserOne->assignRole('teacher');
    $teacherOne = Teacher::create([
        'user_id' => $teacherUserOne->id,
    ]);

    $teacherUserTwo = User::factory()->create();
    $teacherUserTwo->assignRole('teacher');
    $teacherTwo = Teacher::create([
        'user_id' => $teacherUserTwo->id,
    ]);

    $class = ClassModel::create([
        'title' => 'Spoken English Masterclass',
        'description' => 'Full course',
        'short_description' => 'Speak better',
        'who_is_for' => 'Beginners',
        'curriculum' => [
            [
                'title' => 'Foundations',
                'keypoints' => ['Grammar', 'Conversation'],
            ],
        ],
        'price' => 3000,
        'duration_in_days' => 90,
        'total_classes' => 24,
        'is_active' => 1,
        'is_class_recording' => 1,
    ]);

    $morning = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacherOne->id,
        'name' => 'Morning Batch',
        'total_seat' => 10,
        'filled_seat' => 0,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
    ]);

    $evening = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacherOne->id,
        'name' => 'Evening Batch',
        'total_seat' => 10,
        'filled_seat' => 0,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
    ]);

    $weekend = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacherTwo->id,
        'name' => 'Weekend Batch',
        'total_seat' => 10,
        'filled_seat' => 0,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
    ]);

    return [$class, $teacherOne, $teacherTwo, $morning, $evening, $weekend];
}

test('landing class list derives teachers and batch counts from batches', function () {
    [$class, $teacherOne, $teacherTwo] = createClassWithBatchTeachers();

    $this->getJson('/api/classes')
        ->assertOk()
        ->assertJsonPath('data.0.id', $class->id)
        ->assertJsonPath('data.0.teachers_count', 2)
        ->assertJsonPath('data.0.batches_count', 3)
        ->assertJsonPath('data.0.teachers.0.id', $teacherOne->id)
        ->assertJsonPath('data.0.teachers.0.batches_count', 2)
        ->assertJsonPath('data.0.teachers.0.batches.0.name', 'Morning Batch')
        ->assertJsonPath('data.0.teachers.1.id', $teacherTwo->id)
        ->assertJsonPath('data.0.teachers.1.batches_count', 1)
        ->assertJsonMissingPath('data.0.teacher_ids');
});

test('single class derives teachers and batches from batch assignments', function () {
    [$class, $teacherOne] = createClassWithBatchTeachers();

    $this->getJson("/api/single-class/{$class->id}")
        ->assertOk()
        ->assertJsonPath('data.teachers_count', 2)
        ->assertJsonPath('data.batches_count', 3)
        ->assertJsonPath('data.teachers.0.id', $teacherOne->id)
        ->assertJsonPath('data.teachers.0.batches_count', 2)
        ->assertJsonMissingPath('data.teacher_ids');
});

test('class teachers endpoint returns teachers grouped by assigned batches', function () {
    [$class, $teacherOne, $teacherTwo] = createClassWithBatchTeachers();

    $this->getJson("/api/class-teachers/{$class->id}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $teacherOne->id)
        ->assertJsonPath('data.0.batches_count', 2)
        ->assertJsonPath('data.1.id', $teacherTwo->id)
        ->assertJsonPath('data.1.batches_count', 1);
});

test('admin class create ignores teacher_ids and returns empty teachers until batches exist', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'api']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/classes', [
            'title' => 'New Class',
            'description' => 'Desc',
            'price' => 1000,
            'duration_in_days' => 30,
            'total_classes' => 10,
            'teacher_ids' => [1],
        ])
        ->assertCreated()
        ->assertJsonPath('data.teachers_count', 0)
        ->assertJsonPath('data.batches_count', 0)
        ->assertJsonPath('data.teachers', [])
        ->assertJsonMissingPath('data.teacher_ids');

    expect(ClassModel::query()->where('title', 'New Class')->exists())->toBeTrue();
});
