<?php

use App\Models\AssignmentSubmission;
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
function createAssignmentContext(): array
{
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'name' => 'Teacher One',
        'email' => $teacherUser->email,
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

test('teacher can create and list assignments on own batch', function () {
    [$teacherUser, $teacher, $batch] = createAssignmentContext();
    $token = auth('api')->login($teacherUser);

    $create = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/teacher/assignments', [
            'batch_id' => $batch->id,
            'title' => 'Speaking Practice 1',
            'description' => 'Record a 2-minute intro',
            'due_at' => now()->addDays(3)->toISOString(),
            'total_marks' => 50,
            'attachment' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Speaking Practice 1')
        ->assertJsonPath('data.total_marks', '50.00');

    $assignmentId = $create->json('data.id');

    $this->assertDatabaseHas('batch_assignments', [
        'id' => $assignmentId,
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Speaking Practice 1',
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/teacher/assignments/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $assignmentId)
        ->assertJsonPath('pagination.total', 1);
});

test('other teacher cannot create assignment on foreign batch', function () {
    [, , $batch] = createAssignmentContext();

    $otherTeacherUser = User::factory()->create();
    $otherTeacherUser->assignRole('teacher');
    Teacher::create([
        'name' => 'Other Teacher',
        'email' => $otherTeacherUser->email,
        'user_id' => $otherTeacherUser->id,
    ]);

    $token = auth('api')->login($otherTeacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/teacher/assignments', [
            'batch_id' => $batch->id,
            'title' => 'Not allowed',
            'total_marks' => 100,
        ])
        ->assertStatus(403);
});

test('enrolled student can list submit and replace assignment file', function () {
    [$teacherUser, $teacher, $batch, $student] = createAssignmentContext();

    $assignment = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Homework 1',
        'description' => 'Upload worksheet',
        'due_at' => now()->addDay(),
        'total_marks' => 100,
    ]);

    $studentToken = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson("/api/student/assignments/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $assignment->id)
        ->assertJsonPath('data.0.my_submission', null)
        ->assertJsonPath('data.0.is_open', true);

    $first = $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->post("/api/student/assignments/{$assignment->id}/submit", [
            'file' => UploadedFile::fake()->create('work-v1.pdf', 120, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $submissionId = $first->json('data.id');
    $firstPath = AssignmentSubmission::find($submissionId)->file_path;

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->post("/api/student/assignments/{$assignment->id}/submit", [
            'file' => UploadedFile::fake()->create('work-v2.pdf', 140, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $submissionId);

    expect(AssignmentSubmission::where('assignment_id', $assignment->id)->count())->toBe(1)
        ->and(AssignmentSubmission::find($submissionId)->file_path)->not->toBe($firstPath);

    Storage::disk('public')->assertMissing($firstPath);

    $teacherToken = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$teacherToken}")
        ->getJson("/api/teacher/assignments/{$assignment->id}/submissions")
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.student_user_id', $student->id);
});

test('non enrolled student cannot submit assignment', function () {
    [, $teacher, $batch] = createAssignmentContext();

    $assignment = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Homework 1',
        'total_marks' => 100,
    ]);

    $outsider = User::factory()->create();
    $outsider->assignRole('student');
    $token = auth('api')->login($outsider);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post("/api/student/assignments/{$assignment->id}/submit", [
            'file' => UploadedFile::fake()->create('work.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(403);
});

test('student cannot submit after due date', function () {
    [, $teacher, $batch, $student] = createAssignmentContext();

    $assignment = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Late homework',
        'due_at' => now()->subHour(),
        'total_marks' => 100,
    ]);

    $token = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post("/api/student/assignments/{$assignment->id}/submit", [
            'file' => UploadedFile::fake()->create('late.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Assignment submission is closed');
});

test('student assignment tab lists active assignments across enrolled batches', function () {
    [$teacherUser, $teacher, $batch, $student] = createAssignmentContext();

    $active = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Active homework',
        'due_at' => now()->addDays(2),
        'total_marks' => 100,
    ]);

    BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Closed homework',
        'due_at' => now()->subDay(),
        'total_marks' => 100,
    ]);

    $token = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/student/assignments')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('data.0.batch_id', $batch->id)
        ->assertJsonPath('data.0.batch_name', $batch->name)
        ->assertJsonPath('data.0.has_submitted', false)
        ->assertJsonPath('data.0.is_open', true);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post("/api/student/assignments/{$active->id}/submit", [
            'file' => UploadedFile::fake()->create('done.pdf', 100, 'application/pdf'),
        ])
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/student/assignments?pending_only=1')
        ->assertOk()
        ->assertJsonPath('pagination.total', 0);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/student/assignments')
        ->assertOk()
        ->assertJsonPath('data.0.has_submitted', true);
});

test('teacher and student batch responses include active assignments count', function () {
    [$teacherUser, $teacher, $batch, $student] = createAssignmentContext();

    BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Open assignment',
        'due_at' => now()->addDays(2),
        'total_marks' => 100,
    ]);

    BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'No due date assignment',
        'due_at' => null,
        'total_marks' => 100,
    ]);

    BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Closed assignment',
        'due_at' => now()->subDay(),
        'total_marks' => 100,
    ]);

    $teacherToken = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$teacherToken}")
        ->getJson('/api/teacher/batches')
        ->assertOk()
        ->assertJsonPath('data.0.active_assignments_count', 2);

    $this->withHeader('Authorization', "Bearer {$teacherToken}")
        ->getJson("/api/teacher/single-batch/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('data.active_assignments_count', 2);

    $studentToken = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson('/api/student/batches')
        ->assertOk()
        ->assertJsonPath('data.0.active_assignments_count', 2);

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson("/api/student/single-batch/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('data.active_assignments_count', 2);
});

test('teacher can update and delete assignment', function () {
    [$teacherUser, $teacher, $batch, $student] = createAssignmentContext();

    $assignment = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Old title',
        'attachment' => 'assignments/old.pdf',
        'total_marks' => 100,
    ]);

    Storage::disk('public')->put('assignments/old.pdf', 'old');

    $submission = AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'student_user_id' => $student->id,
        'file_path' => 'assignment-submissions/student.pdf',
    ]);

    Storage::disk('public')->put($submission->file_path, 'student-file');

    $token = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post("/api/teacher/assignments/{$assignment->id}", [
            'title' => 'Updated title',
            'description' => 'Updated description',
            'total_marks' => 80,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.total_marks', '80.00');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/teacher/assignments/{$assignment->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('batch_assignments', ['id' => $assignment->id]);
    $this->assertDatabaseMissing('assignment_submissions', ['id' => $submission->id]);
    Storage::disk('public')->assertMissing('assignments/old.pdf');
    Storage::disk('public')->assertMissing('assignment-submissions/student.pdf');
});
