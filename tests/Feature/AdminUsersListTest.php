<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('admin users list hides admin role users', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole('admin');

    $teacher = User::factory()->create(['email' => 'teacher@example.com']);
    $teacher->assignRole('teacher');

    $student = User::factory()->create(['email' => 'student@example.com']);
    $student->assignRole('student');

    $token = auth('api')->login($admin);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/users?page=1&per_page=10')
        ->assertOk()
        ->assertJsonPath('status', true);

    $emails = collect($response->json('data'))->pluck('email');

    expect($emails)
        ->toContain('teacher@example.com')
        ->toContain('student@example.com')
        ->not->toContain('admin@example.com');
});

test('admin users list ignores role=admin filter', function () {
    $admin = User::factory()->create(['email' => 'admin2@example.com']);
    $admin->assignRole('admin');

    $token = auth('api')->login($admin);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/users?role=admin')
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
});
