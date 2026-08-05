<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\StudentActivityNote;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

/**
 * @return array{0: User, 1: Teacher, 2: Batch, 3: User, 4: ClassModel}
 */
function createTeacherStudentContext(array $batchOverrides = []): array
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

    $batch = Batch::create(array_merge([
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
    ], $batchOverrides));

    $student = User::factory()->create([
        'name' => 'Student Alpha',
    ]);
    $student->assignRole('student');

    Enrollment::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
        'class_id' => $class->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $batch->end_date,
    ]);

    return [$teacherUser, $teacher, $batch, $student, $class];
}

test('teacher can list students from running batches with latest note', function () {
    [$teacherUser, $teacher, $batch, $student] = createTeacherStudentContext();

    StudentActivityNote::create([
        'teacher_id' => $teacher->id,
        'batch_id' => $batch->id,
        'student_user_id' => $student->id,
        'comment' => 'Older note',
        'status' => 'average',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    StudentActivityNote::create([
        'teacher_id' => $teacher->id,
        'batch_id' => $batch->id,
        'student_user_id' => $student->id,
        'comment' => 'Latest progress note',
        'status' => 'good',
    ]);

    $token = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher/students')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.user_id', $student->id)
        ->assertJsonPath('data.0.batch_id', $batch->id)
        ->assertJsonPath('data.0.name', 'Student Alpha')
        ->assertJsonPath('data.0.latest_note.status', 'good')
        ->assertJsonPath('data.0.latest_note.comment', 'Latest progress note');
});

test('teacher students list excludes non running batches and supports search', function () {
    [$teacherUser, $teacher, $endedBatch, $endedStudent, $class] = createTeacherStudentContext([
        'name' => 'Ended Batch',
        'status' => 'completed',
        'end_date' => now()->subDay()->toDateString(),
    ]);

    $upcomingBatch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Upcoming Batch',
        'total_seat' => 10,
        'filled_seat' => 1,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
        'status' => 'upcoming',
        'active_status' => 1,
        'zoom_link' => 'https://zoom.example/upcoming',
    ]);

    $upcomingStudent = User::factory()->create(['name' => 'Upcoming Student']);
    $upcomingStudent->assignRole('student');

    Enrollment::create([
        'user_id' => $upcomingStudent->id,
        'batch_id' => $upcomingBatch->id,
        'class_id' => $class->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $upcomingBatch->end_date,
    ]);

    $runningBatch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Running Batch',
        'total_seat' => 10,
        'filled_seat' => 1,
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->addDays(20)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
        'zoom_link' => 'https://zoom.example/running',
    ]);

    $searchableStudent = User::factory()->create([
        'name' => 'Zara Searchable',
        'email' => 'zara.search@example.com',
    ]);
    $searchableStudent->assignRole('student');

    Enrollment::create([
        'user_id' => $searchableStudent->id,
        'batch_id' => $runningBatch->id,
        'class_id' => $class->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'expiry_date' => $runningBatch->end_date,
    ]);

    $token = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher/students')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.user_id', $searchableStudent->id)
        ->assertJsonMissing(['user_id' => $endedStudent->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher/students?search=Zara')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.name', 'Zara Searchable');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher/students?search=missing-name')
        ->assertOk()
        ->assertJsonPath('pagination.total', 0);
});

test('teacher can manage student activity notes', function () {
    [$teacherUser, $teacher, $batch, $student] = createTeacherStudentContext();
    $token = auth('api')->login($teacherUser);

    $create = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/teacher/student-notes', [
            'batch_id' => $batch->id,
            'student_user_id' => $student->id,
            'comment' => 'Needs more speaking practice',
            'status' => 'bad',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'bad');

    $noteId = $create->json('data.id');

    $this->assertDatabaseHas('student_activity_notes', [
        'id' => $noteId,
        'teacher_id' => $teacher->id,
        'student_user_id' => $student->id,
        'status' => 'bad',
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/teacher/students/{$student->id}/notes?batch_id={$batch->id}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $noteId)
        ->assertJsonPath('data.0.comment', 'Needs more speaking practice');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/teacher/student-notes/{$noteId}", [
            'comment' => 'Improving steadily',
            'status' => 'average',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'average')
        ->assertJsonPath('data.comment', 'Improving steadily');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/teacher/student-notes/{$noteId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('student_activity_notes', [
        'id' => $noteId,
    ]);
});

test('teacher does not see other teachers students', function () {
    [, , , $otherStudent] = createTeacherStudentContext();
    [$teacherUser] = createTeacherStudentContext([
        'name' => 'Own Batch',
    ]);

    $token = auth('api')->login($teacherUser);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher/students')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1);

    $userIds = collect($response->json('data'))->pluck('user_id');

    expect($userIds)->not->toContain($otherStudent->id);
});

test('teacher cannot create note for another teachers batch or non enrolled student', function () {
    [$teacherUser, , $batch, $student] = createTeacherStudentContext();

    $otherTeacherUser = User::factory()->create();
    $otherTeacherUser->assignRole('teacher');
    Teacher::create([
        'user_id' => $otherTeacherUser->id,
    ]);

    $outsider = User::factory()->create();
    $outsider->assignRole('student');

    $token = auth('api')->login($otherTeacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/teacher/student-notes', [
            'batch_id' => $batch->id,
            'student_user_id' => $student->id,
            'comment' => 'Not allowed',
            'status' => 'good',
        ])
        ->assertStatus(403);

    $ownerToken = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->postJson('/api/teacher/student-notes', [
            'batch_id' => $batch->id,
            'student_user_id' => $outsider->id,
            'comment' => 'Not enrolled',
            'status' => 'good',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Student is not enrolled in this batch');
});
