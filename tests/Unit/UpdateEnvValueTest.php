<?php

use App\Common\UpdateEnvValue;

test('it updates an existing env key', function () {
    $path = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($path, "APP_NAME=Laravel\nSTRIPE_KEY=old-key\n");

    $result = (new UpdateEnvValue)->handle('STRIPE_KEY', 'new-key', $path);

    expect($result)->toBeTrue()
        ->and(file_get_contents($path))
        ->toContain('STRIPE_KEY="new-key"')
        ->not->toContain('old-key');

    unlink($path);
});

test('it appends a missing env key', function () {
    $path = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($path, "APP_NAME=Laravel\n");

    $result = (new UpdateEnvValue)->handle('PAYPAL_MODE', 'live', $path);

    expect($result)->toBeTrue()
        ->and(file_get_contents($path))
        ->toContain('PAYPAL_MODE="live"');

    unlink($path);
});

test('it returns false when env file is missing', function () {
    $result = (new UpdateEnvValue)->handle('APP_NAME', 'Demo', '/tmp/does-not-exist.env');

    expect($result)->toBeFalse();
});
