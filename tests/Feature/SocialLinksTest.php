<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'admin', 'guard_name' => 'api']);
});

test('public can get social links with defaults', function () {
    Setting::create([
        'class_time' => 30,
        'class_notify_time' => 30,
    ]);

    $this->getJson('/api/social-links')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.youtube', null)
        ->assertJsonPath('data.tiktok', null)
        ->assertJsonPath('data.instagram', null)
        ->assertJsonPath('data.linkedin', null)
        ->assertJsonPath('data.facebook', null)
        ->assertJsonMissingPath('data.phone');
});

test('admin can get social links', function () {
    Setting::create([
        'class_time' => 30,
        'class_notify_time' => 30,
        'social_links' => [
            'youtube' => 'https://youtube.com/@chetnasharma',
            'tiktok' => null,
            'instagram' => 'https://instagram.com/chetnasharma',
            'linkedin' => null,
            'facebook' => null,
        ],
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/social-links')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.youtube', 'https://youtube.com/@chetnasharma')
        ->assertJsonPath('data.instagram', 'https://instagram.com/chetnasharma')
        ->assertJsonMissingPath('data.phone');
});

test('admin can update social link urls', function () {
    Setting::create([
        'class_time' => 30,
        'class_notify_time' => 30,
        'social_links' => Setting::defaultSocialLinks(),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $payload = [
        'youtube' => 'https://youtube.com/@updated',
        'tiktok' => 'https://tiktok.com/@updated',
        'instagram' => 'https://instagram.com/updated',
        'linkedin' => 'https://linkedin.com/in/updated',
        'facebook' => 'https://facebook.com/updated',
    ];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/admin/social-links', $payload)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.youtube', 'https://youtube.com/@updated')
        ->assertJsonPath('data.tiktok', 'https://tiktok.com/@updated')
        ->assertJsonPath('data.instagram', 'https://instagram.com/updated')
        ->assertJsonPath('data.linkedin', 'https://linkedin.com/in/updated')
        ->assertJsonPath('data.facebook', 'https://facebook.com/updated')
        ->assertJsonMissingPath('data.phone');

    $this->getJson('/api/social-links')
        ->assertOk()
        ->assertJsonPath('data.youtube', 'https://youtube.com/@updated')
        ->assertJsonPath('data.facebook', 'https://facebook.com/updated');
});

test('admin social links update rejects invalid urls', function () {
    Setting::create([
        'class_time' => 30,
        'class_notify_time' => 30,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/admin/social-links', [
            'youtube' => 'not-a-url',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['youtube']);
});

test('guest cannot update social links', function () {
    $this->putJson('/api/admin/social-links', [
        'youtube' => 'https://youtube.com/@test',
    ])->assertUnauthorized();
});
