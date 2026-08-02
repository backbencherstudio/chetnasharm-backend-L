<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'student', 'guard_name' => 'api']);
});

test('profile update resizes and stores uploaded image', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'profile@example.com',
    ]);
    $user->assignRole('student');

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/profile-update', [
            'name' => 'New Name',
            'email' => 'profile@example.com',
            'image' => UploadedFile::fake()->image('avatar.jpg', 2400, 1800),
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'New Name');

    $user->refresh();

    expect($user->image)->not->toBeNull()
        ->and(Storage::disk('public')->exists($user->image))->toBeTrue()
        ->and($user->image)->toEndWith('.webp');
});

test('profile update rejects non image upload', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'email' => 'profile2@example.com',
    ]);
    $user->assignRole('student');

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/profile-update', [
            'name' => 'New Name',
            'email' => 'profile2@example.com',
            'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', false);
});

test('profile update rejects image larger than five mb', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'email' => 'profile-large@example.com',
    ]);
    $user->assignRole('student');

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/profile-update', [
            'name' => 'Large Image',
            'email' => 'profile-large@example.com',
            'image' => UploadedFile::fake()->image('big.jpg')->size(5121),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('profile update accepts webp image', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'email' => 'profile3@example.com',
    ]);
    $user->assignRole('student');

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/profile-update', [
            'name' => 'Webp User',
            'email' => 'profile3@example.com',
            'image' => UploadedFile::fake()->image('avatar.webp', 800, 600),
        ])
        ->assertOk()
        ->assertJsonPath('status', true);
});

test('profile update ignores non file image value', function () {
    $user = User::factory()->create([
        'name' => 'Keep Name',
        'email' => 'profile4@example.com',
        'image' => 'users/existing.jpg',
    ]);
    $user->assignRole('student');

    $token = auth('api')->login($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/profile-update', [
            'name' => 'Updated Name',
            'email' => 'profile4@example.com',
            'image' => 'https://example.com/old.jpg',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.image', 'users/existing.jpg');
});
