<?php

use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('admin can create teacher with profile fields', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/teacher-store', [
            'name' => 'Profile Teacher',
            'email' => 'profile.teacher@example.com',
            'about' => 'Experienced IELTS coach',
            'specializations' => ['IELTS', 'Speaking'],
            'languages_spoken' => ['English', 'Bengali'],
            'courses_can_teach' => ['Spoken English', 'IELTS Prep'],
            'interests' => ['Public speaking', 'Travel'],
        ])
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.about', 'Experienced IELTS coach')
        ->assertJsonPath('data.specializations.0', 'IELTS')
        ->assertJsonPath('data.languages_spoken.1', 'Bengali')
        ->assertJsonPath('data.courses_can_teach.0', 'Spoken English')
        ->assertJsonPath('data.interests.0', 'Public speaking');

    $this->assertDatabaseHas('teachers', [
        'email' => 'profile.teacher@example.com',
        'about' => 'Experienced IELTS coach',
    ]);
});

test('admin can update teacher profile fields', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $teacherUser = User::factory()->create([
        'email' => 'update.profile@example.com',
        'department' => 'Teacher',
    ]);
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
        'name' => 'Update Profile',
        'email' => 'update.profile@example.com',
        'about' => 'Old about',
        'specializations' => ['Old'],
        'languages_spoken' => ['English'],
        'courses_can_teach' => ['Old Course'],
        'interests' => ['Old Interest'],
    ]);

    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/teacher-update/{$teacher->id}", [
            'name' => 'Update Profile',
            'email' => 'update.profile@example.com',
            'about' => 'Updated about',
            'specializations' => ['Business English'],
            'languages_spoken' => ['English', 'Hindi'],
            'courses_can_teach' => ['Business English'],
            'interests' => ['Debating'],
        ])
        ->assertOk()
        ->assertJsonPath('data.about', 'Updated about')
        ->assertJsonPath('data.specializations.0', 'Business English')
        ->assertJsonPath('data.languages_spoken.1', 'Hindi')
        ->assertJsonPath('data.courses_can_teach.0', 'Business English')
        ->assertJsonPath('data.interests.0', 'Debating');
});

test('admin teacher edit returns profile fields', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $teacherUser = User::factory()->create([
        'email' => 'edit.profile@example.com',
        'department' => 'Teacher',
    ]);
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
        'name' => 'Edit Profile',
        'email' => 'edit.profile@example.com',
        'about' => 'About text',
        'specializations' => ['Grammar'],
        'languages_spoken' => ['English'],
        'courses_can_teach' => ['Grammar Basics'],
        'interests' => ['Reading'],
    ]);

    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/admin/teacher-edit-data/{$teacher->id}")
        ->assertOk()
        ->assertJsonPath('data.about', 'About text')
        ->assertJsonPath('data.specializations.0', 'Grammar')
        ->assertJsonPath('data.languages_spoken.0', 'English')
        ->assertJsonPath('data.courses_can_teach.0', 'Grammar Basics')
        ->assertJsonPath('data.interests.0', 'Reading');
});

test('public teacher profile includes new profile fields', function () {
    $teacherUser = User::factory()->create([
        'email' => 'public.profile@example.com',
        'department' => 'Teacher',
    ]);
    $teacherUser->assignRole('teacher');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
        'name' => 'Public Profile',
        'email' => 'public.profile@example.com',
        'about' => 'Public about',
        'specializations' => ['IELTS'],
        'languages_spoken' => ['English'],
        'courses_can_teach' => ['IELTS Prep'],
        'interests' => ['Travel'],
        'suspend_status' => 0,
    ]);

    $this->getJson("/api/teachers/{$teacher->id}")
        ->assertOk()
        ->assertJsonPath('data.about', 'Public about')
        ->assertJsonPath('data.specializations.0', 'IELTS')
        ->assertJsonPath('data.languages_spoken.0', 'English')
        ->assertJsonPath('data.courses_can_teach.0', 'IELTS Prep')
        ->assertJsonPath('data.interests.0', 'Travel');
});
