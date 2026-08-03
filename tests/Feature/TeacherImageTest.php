<?php

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('teacher store saves a square optimized image', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/admin/teacher-store', [
            'name' => 'Teacher One',
            'email' => 'teacher1@example.com',
            'image' => UploadedFile::fake()->image('photo.jpg', 1200, 800),
        ]);

    $response->assertCreated()
        ->assertJsonPath('status', true);

    $teacher = Teacher::where('email', 'teacher1@example.com')->first();

    expect($teacher)->not->toBeNull()
        ->and($teacher->image)->not->toBeNull()
        ->and($teacher->image)->toEndWith('.webp')
        ->and(Storage::disk('public')->exists($teacher->image))->toBeTrue();
});

test('teacher update replaces image with a square optimized image', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $teacherUser = User::factory()->create([
        'email' => 'teacher2@example.com',
        'department' => 'Teacher',
    ]);
    $teacherUser->assignRole('teacher');

    $oldPath = UploadedFile::fake()->image('old.jpg', 200, 200)->store('teachers', 'public');

    $teacher = Teacher::create([
        'user_id' => $teacherUser->id,
        'name' => 'Teacher Two',
        'email' => 'teacher2@example.com',
        'image' => $oldPath,
    ]);

    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post("/api/admin/teacher-update/{$teacher->id}", [
            'name' => 'Teacher Two',
            'email' => 'teacher2@example.com',
            'image' => UploadedFile::fake()->image('new.jpg', 1600, 900),
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $teacher->refresh();

    expect($teacher->image)->not->toBe($oldPath)
        ->and($teacher->image)->toEndWith('.webp')
        ->and(Storage::disk('public')->exists($teacher->image))->toBeTrue()
        ->and(Storage::disk('public')->exists($oldPath))->toBeFalse();
});
