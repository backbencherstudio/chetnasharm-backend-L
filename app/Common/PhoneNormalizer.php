<?php

namespace App\Common;

class PhoneNormalizer
{
    /**
     * Normalize a phone number to E.164 format.
     */
    public static function toE164(string $mobile): string
    {
        $cleaned = trim($mobile);

        // If phone has a malformed leading "+0" (e.g., +017... from frontend phone input), sanitize it
        if (str_starts_with($cleaned, '+0')) {
            $cleaned = substr($cleaned, 2);
            if (! str_starts_with($cleaned, '0')) {
                $cleaned = '0'.$cleaned;
            }
        }

        // Parse international number, fallback to national formats for BD, IN, US, AUTO
        return phone($cleaned, ['BD', 'IN', 'US', 'AUTO'])->formatE164();
    }
}
