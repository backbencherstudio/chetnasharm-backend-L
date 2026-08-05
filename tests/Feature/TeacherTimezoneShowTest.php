<?php

use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('authenticated teacher can view own country and timezone', function () {
    $user = User::factory()->create();
    $user->assignRole('teacher');

    Teacher::create([
        'user_id' => $user->id,
        'country' => 'Bangladesh',
        'timezone' => 'Asia/Dhaka',
    ]);

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher/timezone')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.country', 'Bangladesh')
        ->assertJsonPath('data.timezone', 'Asia/Dhaka');
});

test('student cannot access teacher timezone endpoint', function () {
    $student = User::factory()->create();
    $student->assignRole('student');
    $token = auth('api')->login($student);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher/timezone')
        ->assertForbidden();
});
