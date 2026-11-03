<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Builds a single-sheet .xlsx file (bold, frozen header row; set column
 * widths) without a spreadsheet library. An .xlsx is a zip of a few XML
 * parts; strings are written inline so no shared-strings table is needed.
 */
class SimpleXlsx
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<int>  $columnWidths  in characters, one per column
     * @return string the .xlsx file's bytes
     */
    public static function build(string $sheetName, array $headers, array $rows, array $columnWidths = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the spreadsheet.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::escape(mb_substr($sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        // Style 0 = normal, style 1 = bold header with a light teal fill.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF073D40"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE6F2F1"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');

        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($headers, $rows, $columnWidths));
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    private static function sheetXml(array $headers, array $rows, array $columnWidths): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            // Freeze the header row so it stays visible while scrolling.
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';

        if ($columnWidths) {
            $xml .= '<cols>';
            foreach ($columnWidths as $i => $width) {
                $n = $i + 1;
                $xml .= '<col min="'.$n.'" max="'.$n.'" width="'.$width.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>'.self::rowXml(1, $headers, 1);
        foreach (array_values($rows) as $i => $row) {
            $xml .= self::rowXml($i + 2, $row, 0);
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function rowXml(int $rowNumber, array $cells, int $style): string
    {
        $xml = '<row r="'.$rowNumber.'">';
        foreach (array_values($cells) as $i => $value) {
            $ref = self::column($i).$rowNumber;
            $s = $style ? ' s="'.$style.'"' : '';

            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="'.$ref.'"'.$s.'><v>'.$value.'</v></c>';
            } elseif ($value !== null && $value !== '') {
                $xml .= '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::escape((string) $value).'</t></is></c>';
            }
        }

        return $xml.'</row>';
    }

    /** 0 => A, 25 => Z, 26 => AA … */
    private static function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + (($n - 1) % 26)).$name;
        }

        return $name;
    }

    private static function escape(string $value): string
    {
        // Strip characters XML 1.0 forbids, then escape the rest.
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
