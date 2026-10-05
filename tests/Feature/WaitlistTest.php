<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Waitlist;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

function createWaitlistContext(): array
{
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');
    $teacher = Teacher::create(['user_id' => $teacherUser->id]);

    $class = ClassModel::create([
        'title' => 'Advanced English',
        'description' => 'Desc',
        'price' => 150,
        'duration_in_days' => 30,
        'total_classes' => 12,
        'is_active' => 1,
    ]);

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Weekend Batch',
        'total_seat' => 10,
        'filled_seat' => 10,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
    ]);

    return [$teacher, $class, $batch];
}

test('student can join waitlist for a batch', function (): void {
    [$teacher, $class, $batch] = createWaitlistContext();

    $student = User::factory()->create();
    $student->assignRole('student');
    $token = auth('api')->login($student);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/student/waiting-list', [
            'batch_id' => $batch->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('waitlists', [
        'user_id' => $student->id,
        'batch_id' => $batch->id,
    ]);
});

test('student cannot duplicate waitlist for same batch', function (): void {
    [$teacher, $class, $batch] = createWaitlistContext();

    $student = User::factory()->create();
    $student->assignRole('student');
    $token = auth('api')->login($student);

    Waitlist::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/student/waiting-list', [
            'batch_id' => $batch->id,
        ]);

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Already in waitlist');
});

test('student can get own waitlist', function (): void {
    [$teacher, $class, $batch] = createWaitlistContext();

    $student = User::factory()->create();
    $student->assignRole('student');
    $studentToken = auth('api')->login($student);

    Waitlist::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
    ]);

    $studentRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson('/api/student/waiting-list')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($studentRes->json('data'))->toHaveCount(1);
});

test('admin can list all waitlist with pagination', function (): void {
    [$teacher, $class, $batch] = createWaitlistContext();

    $student = User::factory()->create();
    $student->assignRole('student');

    Waitlist::create([
        'user_id' => $student->id,
        'batch_id' => $batch->id,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $adminToken = auth('api')->login($admin);

    $adminRes = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/admin/waiting-list')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($adminRes->json('data'))->toHaveCount(1)
        ->and($adminRes->json('pagination'))->not->toBeNull();
});
