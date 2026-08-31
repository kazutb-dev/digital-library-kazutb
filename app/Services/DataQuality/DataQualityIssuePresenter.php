<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BookCopy;
use App\Models\DataQualityIssue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

/**
 * Turns scanner-oriented issue data into language-safe librarian copy.
 *
 * Issue rows intentionally retain the evidence captured at scan time, but
 * their description/action may have been created under another UI locale.
 * Never present those persisted sentences as interface copy: derive all
 * guidance from the stable rule code and the current request locale.
 */
class DataQualityIssuePresenter
{
    public function __construct(private readonly DataQualityRuleRegistry $rules) {}

    /** @return array{title:string,current:string,guidance:string,replacement:?string,raw_current:string,field:string} */
    public function present(DataQualityIssue $issue, ?Model $entity): array
    {
        return [
            'title' => $this->translation('data_quality.rules.'.$issue->rule_code, $issue->rule_code),
            'current' => $this->currentValue($issue, $entity),
            'guidance' => $this->guidance($issue),
            'replacement' => $this->replacement($issue),
            'raw_current' => $issue->current_value ?? '—',
            'field' => $issue->field_name ?: '—',
        ];
    }

    private function currentValue(DataQualityIssue $issue, ?Model $entity): string
    {
        if ($entity instanceof BookCopy && str_starts_with($issue->rule_code, 'copy.location.')) {
            $entity->loadMissing(['branch', 'fund.branch']);

            $lines = [
                __('data_quality.presentation.library_point', ['value' => $entity->branch?->name ?: __('data_quality.presentation.not_specified')]),
                __('data_quality.presentation.fund', ['value' => $entity->fund?->name ?: __('data_quality.presentation.not_specified')]),
            ];

            if ($issue->rule_code === 'copy.location.fund_branch_conflict') {
                $lines[] = __('data_quality.presentation.fund_belongs_to', [
                    'value' => $entity->fund?->branch?->name ?: __('data_quality.presentation.not_specified'),
                ]);
            }

            return implode("\n", $lines);
        }

        $decoded = json_decode((string) $issue->current_value, true);
        if (is_array($decoded) && array_is_list($decoded) === false) {
            $lines = [];
            foreach ($decoded as $field => $value) {
                $labelKey = 'data_quality.raw_fields.'.$field;
                $label = Lang::has($labelKey) ? __($labelKey) : str_replace('_', ' ', (string) $field);
                $lines[] = $label.': '.$this->scalarValue($value);
            }

            return implode("\n", $lines);
        }

        return $issue->current_value === null || $issue->current_value === ''
            ? __('data_quality.presentation.not_specified')
            : (string) $issue->current_value;
    }

    private function guidance(DataQualityIssue $issue): string
    {
        $ruleKey = 'data_quality.guidance.rules.'.$issue->rule_code;
        if (Lang::has($ruleKey)) {
            return __($ruleKey);
        }

        $categoryKey = 'data_quality.guidance.categories.'.$issue->category;

        return Lang::has($categoryKey)
            ? __($categoryKey)
            : __('data_quality.guidance.default');
    }

    private function replacement(DataQualityIssue $issue): ?string
    {
        $definition = $this->rules->catalogue()[$issue->rule_code] ?? null;
        $suggestion = trim((string) $issue->suggested_action);
        if (! ($definition['auto_fixable'] ?? false) || $suggestion === '' || $this->isGenericSuggestion($suggestion)) {
            return null;
        }

        return $suggestion;
    }

    private function isGenericSuggestion(string $value): bool
    {
        foreach (['ru', 'kk', 'en'] as $locale) {
            if ($value === trans('data_quality.actions.review_source', [], $locale)) {
                return true;
            }
        }

        return false;
    }

    private function scalarValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('data_quality.presentation.not_specified');
        }
        if (is_bool($value)) {
            return $value ? __('common.boolean.yes') : __('common.boolean.no');
        }

        return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function translation(string $key, string $fallback): string
    {
        return Lang::has($key) ? __($key) : $fallback;
    }
}
