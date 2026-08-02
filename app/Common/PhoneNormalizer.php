<?php

namespace App\Common;

class PhoneNormalizer
{
    /**
     * Normalize a phone number to E.164 format.
     */
    public static function toE164(string $mobile): string
    {
        return phone($mobile)->formatE164();
    }
}
