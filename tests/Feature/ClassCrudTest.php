<?php

use App\Models\ClassModel;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('public can view landing classes and single class', function () {
    $class = ClassModel::create([
        'title' => 'Spoken English Pro',
        'description' => 'Complete spoken english course',
        'price' => 120,
        'duration_in_days' => 30,
        'total_classes' => 15,
        'is_active' => 1,
    ]);

    $this->getJson('/api/classes')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.title', 'Spoken English Pro');

    $this->getJson("/api/single-class/{$class->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Spoken English Pro');
});

test('admin can create, update, and toggle status of class', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $createRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/classes', [
            'title' => 'Business English',
            'description' => 'Corporate communication skills',
            'price' => 250,
            'duration_in_days' => 60,
            'total_classes' => 20,
            'is_active' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Business English');

    $classId = $createRes->json('data.id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/admin/classes/{$classId}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Business English');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/classes/{$classId}", [
            'title' => 'Business English Advanced',
            'description' => 'Advanced corporate communication',
            'price' => 300,
            'duration_in_days' => 60,
            'total_classes' => 20,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Business English Advanced');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/admin/class-status/{$classId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $class = ClassModel::find($classId);
    expect($class->is_active)->toBe(0);
});

test('student cannot create or manage classes', function () {
    $student = User::factory()->create();
    $student->assignRole('student');
    $token = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/classes', [
            'title' => 'Hacker English',
            'price' => 10,
        ])
        ->assertStatus(403);
});
