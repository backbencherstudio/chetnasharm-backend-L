<?php

use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('admin can manage users CRUD and suspension', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    // 1. Create User
    $createRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/user-store', [
            'name' => 'New Staff',
            'email' => 'staff@example.com',
            'mobile' => '+15551234567',
            'department' => 'Support',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $createdUserId = $createRes->json('data.id');

    // 2. Edit User Data
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/admin/user-edit-data/{$createdUserId}")
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.user.id', $createdUserId);

    // 3. Update User
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/user-update/{$createdUserId}", [
            'name' => 'Updated Staff',
            'email' => 'updatedstaff@example.com',
            'department' => 'Operations',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    // 4. Suspend User
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/admin/user-suspend/{$createdUserId}")
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.suspend_status', 1);

    // 5. Delete User
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/user/{$createdUserId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    expect(User::find($createdUserId))->toBeNull();
});

test('admin cannot delete own account or suspend super admin', function () {
    // ID 1 super admin
    $superAdmin = User::factory()->create(['id' => 1]);
    $superAdmin->assignRole('admin');

    $admin2 = User::factory()->create(['id' => 2]);
    $admin2->assignRole('admin');
    $token = auth('api')->login($admin2);

    // Cannot delete self
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/admin/user/2')
        ->assertStatus(400);

    // Cannot suspend ID 1
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/admin/user-suspend/1')
        ->assertForbidden();
});

test('admin can store, update, suspend and toggle top status for teachers', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    // Store teacher
    $createRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/teacher-store', [
            'name' => 'Professor Smith',
            'email' => 'smith@example.com',
            'country' => 'United Kingdom',
            'timezone' => 'Europe/London',
            'expertise' => 'IELTS Specialist',
            'years_of_exp' => 5,
        ])
        ->assertCreated()
        ->assertJsonPath('status', true);

    $teacherId = $createRes->json('data.id');

    // Update teacher
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/teacher-update/{$teacherId}", [
            'name' => 'Professor Smith PhD',
            'email' => 'smith@example.com',
            'country' => 'United Kingdom',
            'timezone' => 'Europe/London',
            'expertise' => 'TOEFL & IELTS',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    // Suspend teacher
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/admin/teacher-suspend/{$teacherId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    // Toggle top status
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/teachers/{$teacherId}/toggle-top")
        ->assertOk()
        ->assertJsonPath('status', true);

    expect(Teacher::find($teacherId)->is_top)->toBe(1);
});
