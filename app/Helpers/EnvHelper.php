<?php

namespace App\Helpers;

class EnvHelper
{
    /**
     * Set an environment variable value in the .env file.
     *
     * @return bool
     */
    public static function set($key, $value)
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);

        if (preg_match("/^{$key}=.*/m", $content)) {

            $content = preg_replace(
                "/^{$key}=.*/m",
                $key.'="'.addslashes($value).'"',
                $content
            );

        } else {

            $content .= PHP_EOL.$key.'="'.addslashes($value).'"';
        }

        file_put_contents($path, $content);

        return true;
    }
}
