<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

/** Converts the first sheet of an Excel (.xlsx) file into a CSV file in storage, so the normal CSV import can read it. */
class SpreadsheetToCsv
{
    /** @return string storage path of the CSV */
    public static function convert(string $xlsxPath): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Excel files need the PHP zip extension on this server. Save the sheet as CSV and upload that instead.');
        }

        $csvPath = 'imports/'.uniqid('xlsx-', true).'.csv';
        Storage::put($csvPath, '');
        $out = fopen(Storage::path($csvPath), 'w');

        $reader = new Reader;
        $reader->open($xlsxPath);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(function ($v) {
                        if ($v instanceof \DateTimeInterface) {
                            return $v->format('Y-m-d');
                        }
                        // Excel stores long phone numbers as floats (9.19876543210E+11).
                        if (is_float($v) && floor($v) === $v) {
                            return number_format($v, 0, '', '');
                        }

                        return (string) $v;
                    }, $row->toArray());
                    fputcsv($out, $values, escape: '\\');
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
            fclose($out);
        }

        return $csvPath;
    }
}
