<?php

namespace Tests\Unit\Services;

use App\Models\DataQualityIssue;
use App\Services\DataQuality\DataQualityIssuePresenter;
use Tests\TestCase;

class DataQualityIssuePresenterTest extends TestCase
{
    public function test_it_uses_the_current_interface_language_instead_of_persisted_scan_text(): void
    {
        app()->setLocale('ru');
        $issue = new DataQualityIssue([
            'rule_code' => 'copy.location.fund_branch_conflict',
            'category' => 'locations',
            'field_name' => 'location',
            'current_value' => '{"branch_id":5,"fund_id":4}',
            'expected_format' => 'The fund must belong to the selected library point',
            'description' => 'Қор таңдалған кітапхана нүктесіне жатпайды.',
            'suggested_action' => 'Сенімді дереккөзбен салыстыру',
        ]);

        $presentation = app(DataQualityIssuePresenter::class)->present($issue, null);

        $this->assertSame('Фонд экземпляра не относится к выбранной библиотечной точке.', $presentation['title']);
        $this->assertStringContainsString('Библиотечная точка: 5', $presentation['current']);
        $this->assertStringContainsString('Фонд: 4', $presentation['current']);
        $this->assertStringContainsString('Выбранный фонд должен относиться к выбранной библиотечной точке.', $presentation['guidance']);
        $this->assertNull($presentation['replacement']);
        $this->assertStringNotContainsString('Сенімді', implode(' ', array_filter($presentation)));
        $this->assertStringNotContainsString('The fund must', implode(' ', array_filter($presentation)));
    }
}
