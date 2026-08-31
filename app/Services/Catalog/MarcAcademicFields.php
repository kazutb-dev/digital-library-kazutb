<?php

namespace App\Services\Catalog;

/**
 * Extracts the academic-targeting and provenance attributes the original
 * import dropped, from raw MARC field rows (tag 952 + the 008 fixed field).
 *
 * Source semantics (2026-08-28 audit of dbo.DOC / marc_fields):
 *   952$a — тип литературы для КСУ (КУК/КУР/КНК…)
 *   952$d — факультет
 *   952$e — кафедра
 *   952$i — дисциплины (repeatable)
 *   952$j — специальность (repeatable)
 *   008/00-05 — дата создания записи (YYMMDD)
 *   008/15-17 — код страны издания
 *
 * The parser never invents data: a missing subfield yields null so callers can
 * distinguish "empty" from "not present" and never overwrite a curated value
 * with a blank.
 */
class MarcAcademicFields
{
    /**
     * Build the attribute set from a flat list of MARC field rows. Each row is
     * an array (or object) exposing `tag`, `subfield_code` and `value`.
     *
     * @param  iterable<array<string,mixed>|object>  $rows
     * @return array{
     *     ksu_literature_type: string|null,
     *     faculty: string|null,
     *     department: string|null,
     *     disciplines: string|null,
     *     specialty: string|null,
     *     country_code: string|null,
     *     record_created_on: string|null
     * }
     */
    public static function fromFieldRows(iterable $rows): array
    {
        $sub = [
            'a' => [], 'd' => [], 'e' => [], 'i' => [], 'j' => [],
        ];
        $fixed008 = null;

        foreach ($rows as $row) {
            $tag = (string) self::get($row, 'tag');
            $value = self::get($row, 'value');

            if ($tag === '008') {
                $fixed008 ??= (string) $value;

                continue;
            }
            if ($tag !== '952') {
                continue;
            }
            $code = mb_strtolower(trim((string) self::get($row, 'subfield_code')));
            if (! array_key_exists($code, $sub)) {
                continue;
            }
            $clean = self::clean($value);
            if ($clean !== null) {
                $sub[$code][] = $clean;
            }
        }

        return self::assemble(
            literatureTypes: $sub['a'],
            faculties: $sub['d'],
            departments: $sub['e'],
            disciplines: $sub['i'],
            specialties: $sub['j'],
            fixed008: $fixed008,
        );
    }

    /**
     * Build the attribute set from already-split subfield values plus the 008
     * fixed field. Used by importers that read the source through native
     * subfield accessors instead of a field table.
     *
     * @param  list<string>|string|null  $literatureTypes
     * @param  list<string>|string|null  $disciplines
     * @param  list<string>|string|null  $specialties
     * @return array{
     *     ksu_literature_type: string|null,
     *     faculty: string|null,
     *     department: string|null,
     *     disciplines: string|null,
     *     specialty: string|null,
     *     country_code: string|null,
     *     record_created_on: string|null
     * }
     */
    public static function fromValues(
        array|string|null $literatureTypes,
        array|string|null $faculty,
        array|string|null $department,
        array|string|null $disciplines,
        array|string|null $specialties,
        ?string $fixed008,
    ): array {
        return self::assemble(
            literatureTypes: self::listOf($literatureTypes),
            faculties: self::listOf($faculty),
            departments: self::listOf($department),
            disciplines: self::listOf($disciplines),
            specialties: self::listOf($specialties),
            fixed008: $fixed008,
        );
    }

    /**
     * @param  list<string>  $literatureTypes
     * @param  list<string>  $faculties
     * @param  list<string>  $departments
     * @param  list<string>  $disciplines
     * @param  list<string>  $specialties
     * @return array{
     *     ksu_literature_type: string|null,
     *     faculty: string|null,
     *     department: string|null,
     *     disciplines: string|null,
     *     specialty: string|null,
     *     country_code: string|null,
     *     record_created_on: string|null
     * }
     */
    private static function assemble(
        array $literatureTypes,
        array $faculties,
        array $departments,
        array $disciplines,
        array $specialties,
        ?string $fixed008,
    ): array {
        return [
            'ksu_literature_type' => self::join($literatureTypes, 128),
            'faculty' => self::first($faculties, 255),
            'department' => self::first($departments, 255),
            'disciplines' => self::join($disciplines, 500),
            'specialty' => self::join($specialties, 1000),
            'country_code' => self::country($fixed008),
            'record_created_on' => self::createdOn($fixed008),
        ];
    }

    /** @return list<string> */
    private static function listOf(array|string|null $value): array
    {
        if ($value === null) {
            return [];
        }
        $items = is_array($value) ? $value : (preg_split('/\s*;\s*/u', $value) ?: []);

        return array_values(array_filter(array_map(
            static fn ($item): ?string => self::clean($item),
            $items,
        )));
    }

    /** @param list<string> $values */
    private static function join(array $values, int $maxLength): ?string
    {
        $unique = [];
        foreach ($values as $value) {
            if (! in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }
        if ($unique === []) {
            return null;
        }

        return mb_substr(implode('; ', $unique), 0, $maxLength);
    }

    /** @param list<string> $values */
    private static function first(array $values, int $maxLength): ?string
    {
        return $values === [] ? null : mb_substr($values[0], 0, $maxLength);
    }

    /** 008/15-17 — code страны издания; drop MARC fill characters. */
    private static function country(?string $fixed008): ?string
    {
        if ($fixed008 === null || mb_strlen($fixed008) < 16) {
            return null;
        }
        $raw = mb_substr($fixed008, 15, 3);
        $code = trim(str_replace('|', '', $raw));

        return $code === '' ? null : mb_substr($code, 0, 8);
    }

    /** 008/00-05 — YYMMDD date the record was created in the source catalogue. */
    private static function createdOn(?string $fixed008): ?string
    {
        if ($fixed008 === null || mb_strlen($fixed008) < 6) {
            return null;
        }
        $digits = mb_substr($fixed008, 0, 6);
        if (preg_match('/^\d{6}$/', $digits) !== 1) {
            return null;
        }
        $yy = (int) mb_substr($digits, 0, 2);
        $month = (int) mb_substr($digits, 2, 2);
        $day = (int) mb_substr($digits, 4, 2);
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }
        // Two-digit year pivot: values up to next year belong to the 2000s.
        $pivot = ((int) date('y')) + 1;
        $year = $yy <= $pivot ? 2000 + $yy : 1900 + $yy;
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value;
        $clean = trim($clean, " \t\n\r\0\x0B;,");

        return $clean === '' ? null : $clean;
    }

    private static function get(array|object $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->{$key} ?? null;
    }
}
