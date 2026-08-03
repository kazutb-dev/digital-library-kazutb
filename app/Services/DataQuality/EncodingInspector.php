<?php

namespace App\Services\DataQuality;

class EncodingInspector
{
    /** @var list<string> */
    public const KAZAKH_LETTERS = ['ә', 'ғ', 'қ', 'ң', 'ө', 'ұ', 'ү', 'һ', 'і'];

    /** @var array<string, list<string>> */
    private const AMBIGUOUS_GLYPHS = [
        'є' => ['ә', 'е'],
        'ѓ' => ['ғ'],
        'ќ' => ['қ'],
        '±' => ['ұ'],
        'µ' => ['ө', 'ё'],
        'ў' => ['ү'],
    ];

    /**
     * @return list<array{code:string,severity:string,field:string,value:string,description:string,suggestion:?string,unambiguous:bool,context:array<string,mixed>}>
     */
    public function inspect(string $value, string $field): array
    {
        $issues = [];

        $signals = [
            'encoding.replacement_character' => str_contains($value, '�'),
            'encoding.null_byte' => str_contains($value, "\0"),
            'encoding.control_character' => preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1,
            'encoding.non_breaking_space' => str_contains($value, "\u{00A0}"),
            'encoding.mojibake' => preg_match('/(?:Р[А-Яа-я]|С[А-Яа-я]|Ð.|Ñ.)/u', $value) === 1,
            'encoding.mixed_alphabet' => preg_match('/(?=[\p{Cyrillic}\p{Latin}]*\p{Cyrillic})(?=[\p{Cyrillic}\p{Latin}]*\p{Latin})[\p{Cyrillic}\p{Latin}]{4,}/u', $value) === 1,
        ];

        foreach ($signals as $code => $detected) {
            if (! $detected) {
                continue;
            }
            $issues[] = $this->issue($code, $field, $value, null, false);
        }

        foreach (self::AMBIGUOUS_GLYPHS as $glyph => $replacements) {
            if (! str_contains($value, $glyph)) {
                continue;
            }
            $unambiguous = count($replacements) === 1;
            $preview = $unambiguous ? str_replace($glyph, $replacements[0], $value) : null;
            $issues[] = $this->issue(
                'encoding.legacy_kazakh_glyph',
                $field,
                $value,
                $preview,
                $unambiguous,
                ['glyph' => $glyph, 'candidates' => $replacements],
            );
        }

        return $issues;
    }

    /**
     * A safe representation for the correction UI: code points are shown,
     * while the original text remains escaped by Blade.
     *
     * @return list<array{character:string,codepoint:string,hex:string}>
     */
    public function characters(string $value): array
    {
        return array_map(static function (string $character): array {
            $codepoint = mb_ord($character, 'UTF-8');

            return [
                'character' => $character,
                'codepoint' => sprintf('U+%04X', $codepoint),
                'hex' => strtoupper(bin2hex($character)),
            ];
        }, preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * @param  array<string,mixed>  $extra
     * @return array{code:string,severity:string,field:string,value:string,description:string,suggestion:?string,unambiguous:bool,context:array<string,mixed>}
     */
    private function issue(
        string $code,
        string $field,
        string $value,
        ?string $suggestion,
        bool $unambiguous,
        array $extra = [],
    ): array {
        return [
            'code' => $code,
            'severity' => in_array($code, ['encoding.null_byte', 'encoding.replacement_character'], true) ? 'high' : 'medium',
            'field' => $field,
            'value' => $value,
            'description' => __('data_quality.rules.'.$code),
            'suggestion' => $suggestion,
            'unambiguous' => $unambiguous,
            'context' => $extra + ['characters' => $this->characters($value)],
        ];
    }
}
