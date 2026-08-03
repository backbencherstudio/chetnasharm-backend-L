<?php

use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\BatchAssignment;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\StudentActivityNote;
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
 * @return array{0: User, 1: Teacher, 2: Batch, 3: User, 4: BatchAssignment}
 */
function createMarkedAssignmentContext(): array
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

    $assignment = BatchAssignment::create([
        'batch_id' => $batch->id,
        'teacher_id' => $teacher->id,
        'title' => 'Essay 1',
        'description' => 'Write a short essay',
        'due_at' => now()->addDays(3),
        'total_marks' => 50,
    ]);

    return [$teacherUser, $teacher, $batch, $student, $assignment];
}

test('teacher can grade submission within total marks', function () {
    [$teacherUser, , , $student, $assignment] = createMarkedAssignmentContext();

    $submission = AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'student_user_id' => $student->id,
        'file_path' => 'assignment-submissions/essay.pdf',
    ]);

    $token = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/teacher/assignments/submissions/{$submission->id}/grade", [
            'obtained_marks' => 42,
            'feedback' => 'Strong structure',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.obtained_marks', '42.00')
        ->assertJsonPath('data.total_marks', '50.00')
        ->assertJsonPath('data.feedback', 'Strong structure');

    $this->assertDatabaseHas('assignment_submissions', [
        'id' => $submission->id,
        'obtained_marks' => 42,
        'feedback' => 'Strong structure',
    ]);
});

test('teacher cannot grade above total marks', function () {
    [$teacherUser, , , $student, $assignment] = createMarkedAssignmentContext();

    $submission = AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'student_user_id' => $student->id,
        'file_path' => 'assignment-submissions/essay.pdf',
    ]);

    $token = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/teacher/assignments/submissions/{$submission->id}/grade", [
            'obtained_marks' => 55,
        ])
        ->assertStatus(422);
});

test('student can see marks and activity notes on apis and dashboard', function () {
    [$teacherUser, $teacher, $batch, $student, $assignment] = createMarkedAssignmentContext();

    $submission = AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'student_user_id' => $student->id,
        'file_path' => 'assignment-submissions/essay.pdf',
        'obtained_marks' => 40,
        'feedback' => 'Good work',
        'graded_at' => now(),
    ]);

    StudentActivityNote::create([
        'teacher_id' => $teacher->id,
        'batch_id' => $batch->id,
        'student_user_id' => $student->id,
        'comment' => 'Participates well in class',
        'status' => 'good',
    ]);

    $studentToken = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson('/api/student/assignments')
        ->assertOk()
        ->assertJsonPath('data.0.total_marks', '50.00')
        ->assertJsonPath('data.0.my_submission.obtained_marks', '40.00')
        ->assertJsonPath('data.0.my_submission.feedback', 'Good work');

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson('/api/student/activity-notes')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.comment', 'Participates well in class')
        ->assertJsonPath('data.0.status', 'good')
        ->assertJsonPath('data.0.batch_name', 'Morning Batch')
        ->assertJsonPath('data.0.teacher_name', 'Teacher One');

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson('/api/student/dashboard')
        ->assertOk()
        ->assertJsonPath('data.statistics.pending_assignments', 0)
        ->assertJsonPath('data.recent_graded_assignments.0.submission_id', $submission->id)
        ->assertJsonPath('data.recent_graded_assignments.0.obtained_marks', '40.00')
        ->assertJsonPath('data.recent_graded_assignments.0.total_marks', '50.00')
        ->assertJsonPath('data.recent_activity_notes.0.comment', 'Participates well in class');

    $teacherToken = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$teacherToken}")
        ->getJson("/api/teacher/assignments/{$assignment->id}/submissions")
        ->assertOk()
        ->assertJsonPath('data.0.obtained_marks', '40.00')
        ->assertJsonPath('data.0.total_marks', '50.00');
});

test('student can upload assignment file for grading flow', function () {
    [$teacherUser, , , $student, $assignment] = createMarkedAssignmentContext();
    $studentToken = auth('api')->login($student);

    $submit = $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->post("/api/student/assignments/{$assignment->id}/submit", [
            'file' => UploadedFile::fake()->create('work.pdf', 100, 'application/pdf'),
        ])
        ->assertOk();

    $submissionId = $submit->json('data.id');
    $teacherToken = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$teacherToken}")
        ->postJson("/api/teacher/assignments/submissions/{$submissionId}/grade", [
            'obtained_marks' => 35,
            'feedback' => 'Needs more examples',
        ])
        ->assertOk();

    $studentToken = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson("/api/student/assignments/{$assignment->batch_id}")
        ->assertOk()
        ->assertJsonPath('data.0.my_submission.obtained_marks', '35.00')
        ->assertJsonPath('data.0.my_submission.feedback', 'Needs more examples');
});
