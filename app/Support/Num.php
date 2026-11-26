<?php

namespace App\Support;

/** Number formatting for Indian customers. */
class Num
{
    /** 125000 => "1,25,000" (Indian digit grouping). */
    public static function indian(int|float $n): string
    {
        $negative = $n < 0;
        $digits = (string) (int) round(abs($n));
        if (strlen($digits) > 3) {
            $last3 = substr($digits, -3);
            $rest = substr($digits, 0, -3);
            $digits = ltrim(preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest), ',').','.$last3;
        }

        return ($negative ? '-' : '').$digits;
    }
}
