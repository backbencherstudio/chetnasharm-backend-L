<?php

namespace App\Common;

class UpdateEnvValue
{
    /**
     * Set an environment variable value in the .env file.
     */
    public function handle(string $key, mixed $value, ?string $path = null): bool
    {
        $path ??= base_path('.env');

        if (! file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);

        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace(
                "/^{$key}=.*/m",
                $key.'="'.addslashes((string) $value).'"',
                $content
            );
        } else {
            $content .= PHP_EOL.$key.'="'.addslashes((string) $value).'"';
        }

        file_put_contents($path, $content);

        return true;
    }
}
