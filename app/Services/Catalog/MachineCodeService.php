<?php

namespace App\Services\Catalog;

use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\QrCode;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;

/** Local, deterministic machine-readable codes; no browser/CDN/API dependency. */
class MachineCodeService
{
    public function code128(string $value, float $widthFactor = 2, float $height = 60): string
    {
        return (new BarcodeGeneratorSVG)->getBarcode($this->safeValue($value), BarcodeGenerator::TYPE_CODE_128, $widthFactor, $height);
    }

    public function qr(string $value): string
    {
        // Endroid supplies the standards-compliant QR matrix. Rendering it
        // directly keeps SVG generation independent of optional DOM/XMLWriter.
        $matrix = (new MatrixFactory)->create(new QrCode($this->safeValue($value), size: 240, margin: 0));
        $count = $matrix->getBlockCount();
        $quiet = 4;
        $size = $count + ($quiet * 2);
        $path = '';
        for ($row = 0; $row < $count; $row++) {
            for ($column = 0; $column < $count; $column++) {
                if ($matrix->getBlockValue($row, $column) === 1) {
                    $path .= 'M'.($column + $quiet).' '.($row + $quiet).'h1v1h-1z';
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" role="img" aria-label="QR code"><rect width="%1$d" height="%1$d" fill="#fff"/><path d="%2$s" fill="#000" shape-rendering="crispEdges"/></svg>',
            $size,
            $path,
        );
    }

    private function safeValue(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new \InvalidArgumentException('Machine-readable code must be 1–128 printable characters.');
        }

        return $value;
    }
}
