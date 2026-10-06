<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('guest can register as a student', function (): void {
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

test('user can login with valid credentials and receive token', function (): void {
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

test('login fails with invalid credentials', function (): void {
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

test('suspended user cannot login', function (): void {
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

test('authenticated user can access me and logout', function (): void {
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

test('guest can request otp, verify otp, and reset password', function (): void {
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

test('user can login with google access token and receive token', function (): void {
    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('valid_google_token')
        ->andReturn(
            (new Laravel\Socialite\Two\User)->map([
                'id' => 'google_987654',
                'name' => 'Google Student',
                'email' => 'googlestudent@example.com',
                'avatar' => 'https://example.com/photo.jpg',
            ])
        );

    $response = $this->postJson('/api/auth/google', [
        'access_token' => 'valid_google_token',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['token', 'user', 'data']);

    $this->assertDatabaseHas('users', [
        'email' => 'googlestudent@example.com',
        'provider' => 'google',
        'provider_id' => 'google_987654',
    ]);
});

test('google login fails with invalid token', function (): void {
    $response = $this->postJson('/api/auth/google', [
        'access_token' => 'invalid_token_123',
    ]);

    $response->assertStatus(401);
});

test('user can refresh token successfully', function (): void {
    $user = User::factory()->create();
    $user->assignRole('student');
    $token = auth('api')->login($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/refresh');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['token', 'user', 'data']);

    $newToken = $response->json('token');
    expect($newToken)->not->toBeNull()->and($newToken)->not->toBe($token);

    // Old token should be invalidated/blacklisted
    auth('api')->forgetUser();
    app('tymon.jwt')->unsetToken();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me')
        ->assertUnauthorized();

    // New token should authenticate
    auth('api')->forgetUser();
    app('tymon.jwt')->unsetToken();
    $this->withHeader('Authorization', "Bearer {$newToken}")
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

test('expired token within refresh window can be refreshed', function (): void {
    $user = User::factory()->create();
    $user->assignRole('student');

    Carbon::setTestNow(now());
    auth('api')->factory()->setTTL(1);
    $token = auth('api')->login($user);

    // Advance 5 minutes past 1 minute TTL
    Carbon::setTestNow(now()->addMinutes(5));

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/refresh');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['token', 'user', 'data']);
});

test('refresh fails without token', function (): void {
    $response = $this->postJson('/api/refresh');

    $response->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Token invalid or not provided');
});

test('suspended user cannot refresh token', function (): void {
    $user = User::factory()->create(['suspend_status' => 1]);
    $user->assignRole('student');
    $token = auth('api')->login($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/refresh');

    $response->assertStatus(403)
        ->assertJsonPath('success', false);
});
