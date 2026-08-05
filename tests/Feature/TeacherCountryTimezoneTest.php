<?php

use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('admin can create teacher with country and timezone', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/teacher-store', [
            'name' => 'Sarah Rahman',
            'email' => 'sarah.country@example.com',
            'country' => 'Bangladesh',
            'timezone' => 'Asia/Dhaka',
        ])
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.country', 'Bangladesh')
        ->assertJsonPath('data.timezone', 'Asia/Dhaka');

    $this->assertDatabaseHas('users', [
        'email' => 'sarah.country@example.com',
    ]);

    $this->assertDatabaseHas('teachers', [
        'country' => 'Bangladesh',
        'timezone' => 'Asia/Dhaka',
    ]);
});

test('admin can update teacher country and timezone', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $teacherUser = User::factory()->create([
        'email' => 'teacher.tz@example.com',
        'department' => 'Teacher',
    ]);
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
        'country' => 'India',
        'timezone' => 'Asia/Kolkata',
    ]);

    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/teacher-update/{$teacher->id}", [
            'name' => 'Teacher TZ',
            'email' => 'teacher.tz@example.com',
            'country' => 'United Kingdom',
            'timezone' => 'Europe/London',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.country', 'United Kingdom')
        ->assertJsonPath('data.timezone', 'Europe/London');

    $this->assertDatabaseHas('teachers', [
        'id' => $teacher->id,
        'country' => 'United Kingdom',
        'timezone' => 'Europe/London',
    ]);
});

test('admin teacher edit returns country and timezone', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $teacherUser = User::factory()->create([
        'email' => 'teacher.edit@example.com',
        'department' => 'Teacher',
    ]);
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
        'country' => 'Canada',
        'timezone' => 'America/Toronto',
    ]);

    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/admin/teacher-edit-data/{$teacher->id}")
        ->assertOk()
        ->assertJsonPath('data.country', 'Canada')
        ->assertJsonPath('data.timezone', 'America/Toronto');
});

test('teacher timezone must be a valid timezone identifier', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/teacher-store', [
            'name' => 'Bad TZ',
            'email' => 'bad.tz@example.com',
            'timezone' => 'Not/A_Timezone',
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', false);
});
