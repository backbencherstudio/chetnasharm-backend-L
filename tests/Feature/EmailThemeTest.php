<?php

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\User;

test('password otp email uses the brand theme', function () {
    $html = view('emails.password_otp', ['otp' => '4321'])->render();

    expect($html)
        ->toContain('4321')
        ->toContain('#6d28d9')
        ->toContain('assets/img/logo/logo.webp')
        ->toContain('Reset your');
});

test('enrollment email uses the brand theme', function () {
    $user = User::factory()->make(['name' => 'Aisha']);
    $class = new ClassModel(['title' => 'Spoken English']);
    $batch = new Batch([
        'name' => 'Morning Batch',
        'start_date' => now()->addDays(2),
        'end_date' => now()->addDays(30),
        'zoom_link' => 'https://zoom.example/join',
    ]);

    $html = view('emails.enrollment', [
        'user' => $user,
        'class' => $class,
        'batch' => $batch,
        'schedules' => [
            ['day' => 'Monday', 'start' => '10:00', 'end' => '11:00'],
        ],
    ])->render();

    expect($html)
        ->toContain('Aisha')
        ->toContain('Spoken English')
        ->toContain('Join class')
        ->toContain('#6d28d9')
        ->toContain('assets/img/logo/logo.webp');
});
