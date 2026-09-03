<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class XlsxExport
{
    /**
     * Store a small, dependency-free XLSX workbook on the public disk.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function store(string $path, array $headers, iterable $rows, string $sheetName = 'Registrations'): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'fursa_xlsx_');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create a temporary XLSX file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporary);
            throw new RuntimeException('Unable to open the XLSX archive.');
        }

        $allRows = [$headers];
        foreach ($rows as $row) {
            $allRows[] = array_values($row);
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRelationships());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationships());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::worksheet($allRows));
        $zip->close();

        $contents = file_get_contents($temporary);
        @unlink($temporary);
        if ($contents === false || ! Storage::disk('public')->put($path, $contents)) {
            throw new RuntimeException('Unable to store the generated XLSX file.');
        }

        return Storage::disk('public')->url($path);
    }

    /** @param array<int, array<int, mixed>> $rows */
    protected static function worksheet(array $rows): string
    {
        $columnCount = max(1, count($rows[0] ?? []));
        $lastColumn = self::columnName($columnCount);
        $xmlRows = '';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = self::columnName($columnIndex + 1).$number;
                $style = $rowIndex === 0 ? ' s="1"' : '';
                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="'.$reference.'"'.$style.'><v>'.$value.'</v></c>';
                } else {
                    $cells .= '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'
                        .self::escape((string) ($value ?? '')).'</t></is></c>';
                }
            }
            $xmlRows .= '<row r="'.$number.'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols><col min="1" max="'.$columnCount.'" width="22" customWidth="1"/></cols>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetData>'.$xmlRows.'</sheetData>'
            .'<autoFilter ref="A1:'.$lastColumn.max(1, count($rows)).'"/>'
            .'</worksheet>';
    }

    protected static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    protected static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    protected static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    protected static function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::escape(substr($sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    protected static function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    protected static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Arial"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF352A86"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'</styleSheet>';
    }
}
