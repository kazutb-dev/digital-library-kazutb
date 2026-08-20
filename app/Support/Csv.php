<?php

namespace App\Support;

final class Csv
{
    /**
     * Write an Excel-safe CSV row without changing numeric values.
     *
     * @param  resource  $stream
     * @param  array<int, mixed>  $values
     */
    public static function writeRow($stream, array $values): void
    {
        fputcsv($stream, array_map(
            static function (mixed $value): mixed {
                if (! is_string($value)) {
                    return $value;
                }

                return preg_match('/^[\s]*[=+\-@]/u', $value) === 1
                    ? "'".$value
                    : $value;
            },
            $values,
        ), ',', '"', '');
    }
}
