<?php

use App\Models\Batch;
use App\Models\BatchAssignment;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');

    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

/**
 * @return array{0: User, 1: Teacher, 2: Batch, 3: User}
 */
function createAssignmentWindowContext(): array
{
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
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
        'filled_seat' => 1,
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
        'zoom_link' => 'https://zoom.example/test',
    ]);

    $student = User::factory()->create(['name' => 'Student Alpha']);
    $student->assignRole('student');

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'class_id' => $class->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $batch->end_date,
    ]);

    return [$teacherUser, $teacher, $batch, $student];
}

test('teacher can set starts_at and due_at on assignment', function () {
    [$teacherUser, $teacher, $batch] = createAssignmentWindowContext();
    $token = auth('api')->login($teacherUser);

    $startsAt = now()->addHour()->toDateTimeString();
    $dueAt = now()->addDays(2)->toDateTimeString();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/teacher/assignments', [
            'batch_id' => $batch->id,
            'title' => 'Timed homework',
            'starts_at' => $startsAt,
            'due_at' => $dueAt,
            'total_marks' => 40,
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Timed homework');

    $this->assertDatabaseHas('batch_assignments', [
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Timed homework',
    ]);

    $assignment = BatchAssignment::query()->where('title', 'Timed homework')->first();

    expect($assignment->starts_at)->not->toBeNull()
        ->and($assignment->due_at)->not->toBeNull();
});

test('due_at must be after or equal to starts_at', function () {
    [$teacherUser, , $batch] = createAssignmentWindowContext();
    $token = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/teacher/assignments', [
            'batch_id' => $batch->id,
            'title' => 'Invalid window',
            'starts_at' => now()->addDays(3)->toDateTimeString(),
            'due_at' => now()->addDay()->toDateTimeString(),
            'total_marks' => 40,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_at']);
});

test('student cannot submit before starts_at', function () {
    [, $teacher, $batch, $student] = createAssignmentWindowContext();

    $assignment = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Not started yet',
        'starts_at' => now()->addHour(),
        'due_at' => now()->addDays(2),
        'total_marks' => 100,
    ]);

    $token = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post("/api/student/assignments/{$assignment->id}/submit", [
            'file' => UploadedFile::fake()->create('early.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Assignment submission is closed');
});

test('student can submit inside starts_at and due_at window', function () {
    [, $teacher, $batch, $student] = createAssignmentWindowContext();

    $assignment = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Open window',
        'starts_at' => now()->subHour(),
        'due_at' => now()->addDays(2),
        'total_marks' => 100,
    ]);

    $token = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post("/api/student/assignments/{$assignment->id}/submit", [
            'file' => UploadedFile::fake()->create('on-time.pdf', 100, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('active assignment list excludes assignments that have not started', function () {
    [, $teacher, $batch, $student] = createAssignmentWindowContext();

    $open = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Already open',
        'starts_at' => now()->subHour(),
        'due_at' => now()->addDays(2),
        'total_marks' => 100,
    ]);

    BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Future start',
        'starts_at' => now()->addDay(),
        'due_at' => now()->addDays(3),
        'total_marks' => 100,
    ]);

    $token = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/student/assignments')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.id', $open->id);
});

test('student batch assignment list hides assignments that have not started', function () {
    [, $teacher, $batch, $student] = createAssignmentWindowContext();

    $visible = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Visible homework',
        'starts_at' => now()->subHour(),
        'due_at' => now()->addDays(2),
        'total_marks' => 100,
    ]);

    BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Hidden until start',
        'starts_at' => now()->addDay(),
        'due_at' => now()->addDays(3),
        'total_marks' => 100,
    ]);

    $token = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/student/assignments/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonMissing(['title' => 'Hidden until start']);
});
