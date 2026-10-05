<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

function setupBatchCrudContext(): array
{
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');
    $teacher = Teacher::create(['user_id' => $teacherUser->id]);

    TeacherAvailability::create([
        'teacher_id' => $teacher->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $class = ClassModel::create([
        'title' => 'Grammar Masterclass',
        'description' => 'Desc',
        'price' => 100,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
    ]);

    Setting::create([
        'class_time' => 60,
    ]);

    return [$teacher, $class, $teacherUser];
}

test('admin can create and list batches with schedule', function () {
    [$teacher, $class] = setupBatchCrudContext();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $createRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/batches', [
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'name' => 'Grammar Batch 1',
            'total_seat' => 15,
            'status' => 'ongoing',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(32)->toDateString(),
            'zoom_link' => 'https://zoom.us/j/1234567890',
            'schedules' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '10:00',
                    'end_time' => '11:00',
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Grammar Batch 1');

    $batchId = $createRes->json('data.id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/batches')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/admin/batches/{$batchId}")
        ->assertOk()
        ->assertJsonPath('data.id', $batchId);
});

test('teacher can update zoom link of assigned batch', function () {
    [$teacher, $class, $teacherUser] = setupBatchCrudContext();

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Grammar Batch 2',
        'total_seat' => 15,
        'filled_seat' => 0,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(32)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
        'zoom_link' => 'https://zoom.us/old',
    ]);

    $token = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/update-zoom-link/{$batch->id}", [
            'zoom_link' => 'https://zoom.us/new-link',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.zoom_link', 'https://zoom.us/new-link');

    $batch->refresh();
    expect($batch->zoom_link)->toBe('https://zoom.us/new-link');
});

test('admin can toggle active status and delete batch', function () {
    [$teacher, $class] = setupBatchCrudContext();

    $batch = Batch::create([
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'name' => 'Grammar Batch 3',
        'total_seat' => 15,
        'filled_seat' => 0,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(32)->toDateString(),
        'status' => 'ongoing',
        'active_status' => 1,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/admin/batch-active-status/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/batches/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('batches', ['id' => $batch->id]);
});
