<?php

namespace App\Support;

class PhoneNormalizer
{
    /**
     * Normalize a phone number to E.164 format.
     *
     * @return string
     */
    public static function toE164(string $mobile): string
    {
        return phone($mobile)->formatE164();
    }
}
