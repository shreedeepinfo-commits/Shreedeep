<?php

namespace App\Support;

class Phone
{
    /**
     * Normalise to WhatsApp's format: digits only, with country code (e.g. 919876543210).
     * A national number (no + or 00 prefix, 10 digits or fewer) gets the default country code.
     * Returns null when the value cannot be a valid number.
     */
    public static function normalize(?string $raw, ?string $defaultCountryCode = null): ?string
    {
        $raw = trim((string) $raw);
        $digits = preg_replace('/\D+/', '', $raw);
        $international = str_starts_with($raw, '+') || str_starts_with($digits, '00');
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $cc = preg_replace('/\D+/', '', (string) $defaultCountryCode);
        if (! $international && $cc !== '') {
            $national = ltrim($digits, '0');
            if (strlen($national) <= 10) {
                $digits = $cc.$national;
            }
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }
}
