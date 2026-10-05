<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\ClassRecording;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

function setupClassRecordingContext(): array
{
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
    ]);

    $class = ClassModel::create([
        'title' => 'Recording Masterclass',
        'description' => 'Desc',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Recording Batch',
        'total_seat' => 10,
        'filled_seat' => 1,
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->addDays(25)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
    ]);

    $student = User::factory()->create(['name' => 'Recording Student']);
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

test('teacher can create, show, update and delete class recording for assigned batch', function (): void {
    [$teacherUser, $teacher, $batch, $student] = setupClassRecordingContext();
    $token = auth('api')->login($teacherUser);

    // Create recording
    $createRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/teacher/recordings', [
            'batch_id' => $batch->id,
            'class_date' => now()->toDateString(),
            'recording_url' => 'https://example.com/recording/1',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.recording_url', 'https://example.com/recording/1');

    $recordingId = $createRes->json('data.id');

    // List recordings for teacher
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/teacher/recordings/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    // Show single recording
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/teacher/edit-recording/{$recordingId}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $recordingId);

    // Update recording
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/teacher/recordings/{$recordingId}", [
            'recording_url' => 'https://example.com/recording/updated',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.recording_url', 'https://example.com/recording/updated');

    // Delete recording
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/teacher/recordings/{$recordingId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(ClassRecording::find($recordingId))->toBeNull();
});

test('enrolled student can view recordings but non-enrolled student cannot', function (): void {
    [$teacherUser, $teacher, $batch, $student] = setupClassRecordingContext();

    ClassRecording::create([
        'batch_id' => $batch->id,
        'class_date' => now()->toDateString(),
        'recording_url' => 'https://example.com/recording/lesson-1',
    ]);

    $studentToken = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson("/api/student/recordings/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    // Non-enrolled student
    $otherStudent = User::factory()->create();
    $otherStudent->assignRole('student');
    $otherToken = auth('api')->login($otherStudent);

    $this->withHeader('Authorization', "Bearer {$otherToken}")
        ->getJson("/api/student/recordings/{$batch->id}")
        ->assertForbidden();
});

test('unassigned teacher cannot create recording for a batch', function (): void {
    [$teacherUser, $teacher, $batch] = setupClassRecordingContext();

    $unassignedUser = User::factory()->create();
    $unassignedUser->assignRole('teacher');
    Teacher::create(['user_id' => $unassignedUser->id]);

    $unassignedToken = auth('api')->login($unassignedUser);

    $this->withHeader('Authorization', "Bearer {$unassignedToken}")
        ->postJson('/api/teacher/recordings', [
            'batch_id' => $batch->id,
            'class_date' => now()->toDateString(),
            'recording_url' => 'https://example.com/recording/unauthorized',
        ])
        ->assertForbidden();
});
