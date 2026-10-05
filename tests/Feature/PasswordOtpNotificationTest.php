<?php

use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use Illuminate\Support\Facades\Notification;

test('send otp dispatches password otp notification', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset@example.com',
    ]);

    $this->postJson('/api/send-otp', ['email' => $user->email])
        ->assertOk()
        ->assertJsonPath('status', true);

    Notification::assertSentTo($user, PasswordOtpNotification::class, fn (PasswordOtpNotification $notification): bool => (string) $notification->otp !== '');
});
