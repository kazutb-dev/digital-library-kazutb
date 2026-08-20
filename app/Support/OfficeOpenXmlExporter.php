<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Str;
use RuntimeException;
use Stringable;
use ZipArchive;

/**
 * Small dependency-free OOXML writer for flat official report tables.
 *
 * The output is a real ZIP-based XLSX/DOCX package (not HTML or CSV carrying
 * an Office extension). Cells are inline strings, so user data can never be
 * interpreted by spreadsheet software as a formula.
 */
final class OfficeOpenXmlExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function xlsx(string $title, array $headers, iterable $rows): string
    {
        $path = $this->temporaryPath('xlsx');
        $zip = $this->open($path);
        $created = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $sheetRows = [$headers];
        $headerRowIndex = 0;
        foreach ($rows as $row) {
            $sheetRows[] = array_values($row);
        }

        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.($headerRowIndex + 1).'" topLeftCell="A'.($headerRowIndex + 2).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$this->spreadsheetColumns(count($headers)).'</cols><sheetData>';
        foreach ($sheetRows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $worksheet .= '<row r="'.$number.'">';
            foreach ($row as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1).$number;
                $style = $rowIndex === $headerRowIndex ? ' s="1"' : '';
                if ($rowIndex !== $headerRowIndex && (is_int($value) || is_float($value)) && is_finite((float) $value)) {
                    $worksheet .= '<c r="'.$cell.'"'.$style.' t="n"><v>'.$value.'</v></c>';
                } else {
                    $worksheet .= '<c r="'.$cell.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.$this->xml($this->value($value)).'</t></is></c>';
                }
            }
            $worksheet .= '</row>';
        }
        $worksheet .= '</sheetData><autoFilter ref="A'.($headerRowIndex + 1).':'.$this->columnName(max(1, count($headers))).($headerRowIndex + 1).'"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>');
        $zip->addFromString('docProps/core.xml', $this->coreProperties($title, $created));
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>Library Reports</Application><AppVersion>1.0</AppVersion></Properties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->xml(mb_substr($title, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->spreadsheetStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
        $this->close($zip, $path);

        return $path;
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     * @param  array<string, scalar|null>  $filters
     */
    public function docx(string $title, array $headers, iterable $rows, array $filters = [], array $metadata = []): string
    {
        $path = $this->temporaryPath('docx');
        $zip = $this->open($path);
        $created = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $body = $this->paragraph($title, 'Title');
        $body .= $this->paragraph((string) __('analytics.generated', ['date' => now()->format('d.m.Y H:i')]), 'Subtitle');
        foreach ($metadata as $key => $value) {
            $body .= $this->paragraph((string) $key.': '.$this->value($value), 'Normal');
        }
        if ($filters !== []) {
            $filterText = collect($filters)->filter(static fn (mixed $value): bool => $value !== null && $value !== '')
                ->map(fn (mixed $value, string $key): string => Str::headline($key).': '.$this->value($value))->implode(' · ');
            $body .= $this->paragraph($filterText, 'Normal');
        }
        $body .= '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/></w:tblPr><w:tblGrid/>';
        $body .= $this->wordTableRow($headers, true);
        foreach ($rows as $row) {
            $body .= $this->wordTableRow(array_values($row), false);
        }
        $body .= '</w:tbl><w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/></w:sectPr>';

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>');
        $zip->addFromString('docProps/core.xml', $this->coreProperties($title, $created));
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Library Reports</Application></Properties>');
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/styles.xml', $this->wordStyles());
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $this->close($zip, $path);

        return $path;
    }

    private function temporaryPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'library-report-');
        if ($base === false) {
            throw new RuntimeException('Unable to allocate a temporary report file.');
        }
        $path = $base.'.'.$extension;
        @unlink($base);

        return $path;
    }

    private function open(string $path): ZipArchive
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZIP extension is required for Office report exports.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create an Office report package.');
        }

        return $zip;
    }

    private function close(ZipArchive $zip, string $path): void
    {
        if (! $zip->close() || ! is_file($path) || filesize($path) === 0) {
            @unlink($path);
            throw new RuntimeException('Unable to finalize an Office report package.');
        }
    }

    private function coreProperties(string $title, string $created): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->xml($title).'</dc:title><dc:creator>Library Reports</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:modified></cp:coreProperties>';
    }

    private function spreadsheetColumns(int $count): string
    {
        $xml = '';
        for ($column = 1; $column <= max(1, $count); $column++) {
            $xml .= '<col min="'.$column.'" max="'.$column.'" width="20" customWidth="1"/>';
        }

        return $xml;
    }

    private function spreadsheetStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B4D4A"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function wordStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="18"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:rPr><w:b/><w:color w:val="0B4D4A"/><w:sz w:val="34"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:rPr><w:color w:val="657573"/><w:sz w:val="18"/></w:rPr></w:style>'
            .'<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="B7C8C5"/><w:left w:val="single" w:sz="4" w:color="B7C8C5"/><w:bottom w:val="single" w:sz="4" w:color="B7C8C5"/><w:right w:val="single" w:sz="4" w:color="B7C8C5"/><w:insideH w:val="single" w:sz="4" w:color="B7C8C5"/><w:insideV w:val="single" w:sz="4" w:color="B7C8C5"/></w:tblBorders></w:tblPr></w:style>'
            .'</w:styles>';
    }

    /** @param array<int, mixed> $cells */
    private function wordTableRow(array $cells, bool $header): string
    {
        $xml = '<w:tr>';
        foreach ($cells as $cell) {
            $shading = $header ? '<w:shd w:val="clear" w:color="auto" w:fill="0B4D4A"/>' : '';
            $run = $header ? '<w:b/><w:color w:val="FFFFFF"/>' : '';
            $xml .= '<w:tc><w:tcPr>'.$shading.'</w:tcPr><w:p><w:r><w:rPr>'.$run.'</w:rPr><w:t xml:space="preserve">'.$this->xml($this->value($cell)).'</w:t></w:r></w:p></w:tc>';
        }

        return $xml.'</w:tr>';
    }

    private function paragraph(string $text, string $style): string
    {
        return '<w:p><w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr><w:r><w:t xml:space="preserve">'.$this->xml($text).'</w:t></w:r></w:p>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function value(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return implode(', ', array_map(fn (mixed $item): string => $this->value($item), $value));
        }
        if ($value instanceof Stringable || is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function xml(string $value): string
    {
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
