<?php

namespace App\Support;

/**
 * Parses numbers pasted from Excel, WhatsApp or notes.
 * Accepts one number per line ("9876543210" or "9876543210, Ravi" or tab separated),
 * or many numbers on one line separated by commas, semicolons or spaces.
 */
class PastedNumbers
{
    /** @return list<array{0: string, 1: ?string}> [raw phone, name] */
    public static function parse(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $tokens = array_values(array_filter(array_map('trim', preg_split('/[,;\t]+/', $line)), fn ($t) => $t !== ''));
            $numeric = fn ($t) => preg_match('/^\+?[\d\s\-().]{6,}$/', $t) === 1;

            if ($tokens && count(array_filter($tokens, $numeric)) === count($tokens)) {
                // Only numbers on this line: maybe several separated by commas or wide spaces.
                foreach ($tokens as $t) {
                    foreach (preg_split('/\s{2,}/', $t) as $n) {
                        $out[] = [$n, null];
                    }
                }

                continue;
            }

            $phone = null;
            $nameParts = [];
            foreach ($tokens as $t) {
                if ($phone === null && $numeric($t)) {
                    $phone = $t;
                } else {
                    $nameParts[] = $t;
                }
            }
            if ($phone !== null) {
                $out[] = [$phone, $nameParts ? implode(' ', $nameParts) : null];
            }
        }

        return $out;
    }

    /** Writes the parsed rows to a CSV the ImportContacts job can read. Returns the storage path. */
    public static function toCsv(string $text): string
    {
        $path = 'imports/paste-'.uniqid().'.csv';
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['phone', 'name'], escape: '\\');
        foreach (self::parse($text) as [$phone, $name]) {
            fputcsv($handle, [$phone, $name], escape: '\\');
        }
        rewind($handle);
        \Illuminate\Support\Facades\Storage::put($path, stream_get_contents($handle));
        fclose($handle);

        return $path;
    }
}
