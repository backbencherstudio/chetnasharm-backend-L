<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\TeacherNote;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

function setupTeacherNoteContext(): array
{
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
    ]);

    $class = ClassModel::create([
        'title' => 'Note Masterclass',
        'description' => 'Desc',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Notes Batch',
        'total_seat' => 10,
        'filled_seat' => 1,
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->addDays(25)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
    ]);

    $student = User::factory()->create(['name' => 'Note Student']);
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

test('teacher can create, show, update and delete note for assigned batch', function (): void {
    [$teacherUser, $teacher, $batch, $student] = setupTeacherNoteContext();
    $token = auth('api')->login($teacherUser);

    // Create note
    $createRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/teacher/notes', [
            'batch_id' => $batch->id,
            'title' => 'Grammar Rule 1',
            'note' => 'Always use correct tenses.',
            'note_link' => 'https://example.com/notes/rule1',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Grammar Rule 1');

    $noteId = $createRes->json('data.id');

    // List notes for teacher
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/teacher/notes/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    // Show single note
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/teacher/notes-edit/{$noteId}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $noteId);

    // Update note
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/teacher/notes/{$noteId}", [
            'title' => 'Grammar Rule 1 Updated',
            'note' => 'Updated content for grammar rule.',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Grammar Rule 1 Updated');

    // Delete note
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/teacher/notes/{$noteId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(TeacherNote::find($noteId))->toBeNull();
});

test('enrolled student can view notes but non-enrolled cannot', function (): void {
    [$teacherUser, $teacher, $batch, $student] = setupTeacherNoteContext();

    TeacherNote::create([
        'user_id' => $teacherUser->id,
        'batch_id' => $batch->id,
        'title' => 'Vocabulary List',
        'note' => 'Ten new words for today',
    ]);

    $studentToken = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson("/api/student/notes/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    // Non-enrolled student
    $otherStudent = User::factory()->create();
    $otherStudent->assignRole('student');
    $otherToken = auth('api')->login($otherStudent);

    $this->withHeader('Authorization', "Bearer {$otherToken}")
        ->getJson("/api/student/notes/{$batch->id}")
        ->assertForbidden();
});

test('unassigned teacher cannot access or create notes in other batch', function (): void {
    [$teacherUser, $teacher, $batch] = setupTeacherNoteContext();

    $unassignedUser = User::factory()->create();
    $unassignedUser->assignRole('teacher');
    Teacher::create(['user_id' => $unassignedUser->id]);

    $unassignedToken = auth('api')->login($unassignedUser);

    $this->withHeader('Authorization', "Bearer {$unassignedToken}")
        ->postJson('/api/teacher/notes', [
            'batch_id' => $batch->id,
            'title' => 'Sneaky Note',
            'note' => 'I should not be able to post this',
        ])
        ->assertForbidden();
});
