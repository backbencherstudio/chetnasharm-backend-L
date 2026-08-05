<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'admin', 'guard_name' => 'api']);
});

test('admin can get integration settings from database', function () {
    Setting::create([
        'class_time' => 30,
        'class_notify_time' => 30,
        'integrations' => [
            'stripe' => [
                'key' => 'pk_test_public',
                'secret' => 'sk_test_secret_value',
                'webhook_secret' => 'whsec_abcdef',
            ],
            'paypal' => [
                'client_id' => 'paypal-client',
                'client_secret' => 'paypal-secret-value',
                'mode' => 'sandbox',
                'base_url' => 'https://api-m.sandbox.paypal.com',
            ],
            'whatsapp' => [
                'token' => 'wa-token-secret',
                'phone_number_id' => '1112996207',
                'url' => 'https://graph.facebook.com/v25.0',
            ],
        ],
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/env-settings')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('stripe.key', 'pk_test_public')
        ->assertJsonPath('stripe.secret', '******_value')
        ->assertJsonPath('paypal.client_id', 'paypal-client')
        ->assertJsonPath('paypal.mode', 'sandbox')
        ->assertJsonPath('whatsapp.phone_number_id', '1112996207')
        ->assertJsonPath('whatsapp.token', '******secret');
});

test('admin can update integration settings in database without touching env', function () {
    $envBefore = file_get_contents(base_path('.env'));

    Setting::create([
        'class_time' => 30,
        'class_notify_time' => 30,
        'integrations' => Setting::defaultIntegrations(),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/env-settings', [
            'stripe' => [
                'key' => 'pk_live_new',
                'secret' => 'sk_live_new_secret',
                'webhook_secret' => 'whsec_new_secret',
            ],
            'paypal' => [
                'client_id' => 'new-paypal-id',
                'client_secret' => 'new-paypal-secret',
                'mode' => 'live',
                'base_url' => 'https://api-m.paypal.com',
            ],
            'whatsapp' => [
                'token' => 'new-wa-token',
                'phone_number_id' => '999888777',
                'url' => 'https://graph.facebook.com/v25.0',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Environment settings updated successfully.');

    $setting = Setting::query()->first();

    expect($setting->integrations['stripe']['key'])->toBe('pk_live_new')
        ->and($setting->integrations['stripe']['secret'])->toBe('sk_live_new_secret')
        ->and($setting->integrations['paypal']['mode'])->toBe('live')
        ->and($setting->integrations['whatsapp']['phone_number_id'])->toBe('999888777');

    expect(file_get_contents(base_path('.env')))->toBe($envBefore);
});

test('guest cannot access env settings', function () {
    $this->getJson('/api/admin/env-settings')->assertUnauthorized();
    $this->postJson('/api/admin/env-settings', [])->assertUnauthorized();
});
