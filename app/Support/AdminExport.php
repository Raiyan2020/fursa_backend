<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Spreadsheet export shared by the admin panel.
 *
 * Emits an HTML table with a UTF-8 BOM under an .xls filename — the same trick
 * the users export already used, kept because Excel opens it directly and it
 * needs no PHP extension or Composer package.
 */
class AdminExport
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function spreadsheet(string $filenamePrefix, array $headers, iterable $rows): StreamedResponse
    {
        $filename = $filenamePrefix.'_'.now()->format('Y-m-d_His').'.xls';

        return response()->streamDownload(function () use ($headers, $rows) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';

            echo '<tr>';
            foreach ($headers as $header) {
                echo '<th>'.e($header).'</th>';
            }
            echo '</tr>';

            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>'.e((string) $cell).'</td>';
                }
                echo '</tr>';
            }

            echo '</table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }
}
