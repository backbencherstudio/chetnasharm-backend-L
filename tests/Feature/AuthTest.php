<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('guest can register as a student', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'johndoe@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'mobile' => '+8801700000001',
    ];

    $response = $this->postJson('/api/register', $payload);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'johndoe@example.com');

    $this->assertDatabaseHas('users', ['email' => 'johndoe@example.com']);
    $user = User::where('email', 'johndoe@example.com')->first();
    expect($user->hasRole('student'))->toBeTrue();
});

test('user can login with valid credentials and receive token', function () {
    $user = User::factory()->create([
        'email' => 'student@example.com',
        'password' => Hash::make('Secret123!'),
    ]);
    $user->assignRole('student');

    $response = $this->postJson('/api/login', [
        'email' => 'student@example.com',
        'password' => 'Secret123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['token', 'user', 'data']);
});

test('login fails with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'student@example.com',
        'password' => Hash::make('Secret123!'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'student@example.com',
        'password' => 'WrongPassword',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('success', false);
});

test('suspended user cannot login', function () {
    $user = User::factory()->create([
        'email' => 'suspended@example.com',
        'password' => Hash::make('Secret123!'),
        'suspend_status' => 1,
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'suspended@example.com',
        'password' => 'Secret123!',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('success', false);
});

test('authenticated user can access me and logout', function () {
    $user = User::factory()->create();
    $user->assignRole('student');
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('guest can request otp, verify otp, and reset password', function () {
    $user = User::factory()->create([
        'email' => 'resetme@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $this->postJson('/api/send-otp', ['email' => 'resetme@example.com'])
        ->assertOk()
        ->assertJsonPath('success', true);

    $otpRecord = DB::table('password_otps')->where('user_id', $user->id)->first();
    expect($otpRecord)->not->toBeNull();

    $this->postJson('/api/verify-otp', [
        'email' => 'resetme@example.com',
        'otp' => (string) $otpRecord->otp,
    ])->assertOk()->assertJsonPath('success', true);

    $this->postJson('/api/password-reset', [
        'email' => 'resetme@example.com',
        'otp' => (string) $otpRecord->otp,
        'new_password' => 'NewPassword123!',
        'new_password_confirmation' => 'NewPassword123!',
    ])->assertOk()->assertJsonPath('success', true);

    $user->refresh();
    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue();
});
