<?php

use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('admin can list availability with string teacher_id query param', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');
    $teacher = Teacher::create(['user_id' => $teacherUser->id]);

    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher-availability?teacher_id='.$teacher->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Availability fetched successfully');
});

test('teacher can list own availability without teacher_id', function () {
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('teacher');
    Teacher::create(['user_id' => $teacherUser->id]);

    $token = auth('api')->login($teacherUser);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/teacher-availability')
        ->assertOk()
        ->assertJsonPath('success', true);
});
