<?php

use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use Illuminate\Support\Facades\Notification;

test('send otp dispatches password otp notification', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset@example.com',
    ]);

    $this->postJson('/api/send-otp', ['email' => $user->email])
        ->assertOk()
        ->assertJsonPath('status', true);

    Notification::assertSentTo($user, PasswordOtpNotification::class, function (PasswordOtpNotification $notification) {
        return (string) $notification->otp !== '';
    });
});
