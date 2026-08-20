<?php

namespace Tests\Unit\Support;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

class CsvFormulaInjectionTest extends TestCase
{
    public function test_spreadsheet_formula_prefixes_are_neutralized_in_export_only(): void
    {
        $stream = fopen('php://memory', 'w+');
        Csv::writeRow($stream, ['=SUM(1,1)', '+cmd', '-2+3', '@name', '  =hidden', 'Қазақша', 42]);
        rewind($stream);
        $row = fgetcsv($stream, null, ',', '"', '');
        fclose($stream);

        $this->assertSame(["'=SUM(1,1)", "'+cmd", "'-2+3", "'@name", "'  =hidden", 'Қазақша', '42'], $row);
    }
}
