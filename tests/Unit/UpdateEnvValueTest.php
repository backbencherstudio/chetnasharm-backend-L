<?php

namespace Tests\Unit;

use App\Actions\UpdateEnvValue;
use PHPUnit\Framework\TestCase;

class UpdateEnvValueTest extends TestCase
{
    public function test_it_updates_an_existing_env_key(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, "APP_NAME=Laravel\nSTRIPE_KEY=old-key\n");

        $result = (new UpdateEnvValue)->handle('STRIPE_KEY', 'new-key', $path);

        $this->assertTrue($result);
        $this->assertStringContainsString('STRIPE_KEY="new-key"', file_get_contents($path));
        $this->assertStringNotContainsString('old-key', file_get_contents($path));

        unlink($path);
    }

    public function test_it_appends_a_missing_env_key(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, "APP_NAME=Laravel\n");

        $result = (new UpdateEnvValue)->handle('PAYPAL_MODE', 'live', $path);

        $this->assertTrue($result);
        $this->assertStringContainsString('PAYPAL_MODE="live"', file_get_contents($path));

        unlink($path);
    }

    public function test_it_returns_false_when_env_file_is_missing(): void
    {
        $result = (new UpdateEnvValue)->handle('APP_NAME', 'Demo', '/tmp/does-not-exist.env');

        $this->assertFalse($result);
    }
}
