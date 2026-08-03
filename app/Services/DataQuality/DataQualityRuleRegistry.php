<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\Catalog\UdcCode;
use App\Models\Setting;
use App\Services\Library\IsbnService;
use Illuminate\Database\Eloquent\Model;

class DataQualityRuleRegistry
{
    public const VERSION = '2026.07.31.1';

    public function __construct(
        private readonly IsbnService $isbn,
        private readonly EncodingInspector $encoding,
    ) {}

    /**
     * @return array<string,array{entity:string,category:string,severity:string,auto_fixable:bool}>
     */
    public function catalogue(): array
    {
        return [
            'bib.title.missing' => $this->definition('bibliographic_record', 'completeness', 'critical'),
            'bib.title.suspicious' => $this->definition('bibliographic_record', 'validity', 'high'),
            'bib.title.spacing' => $this->definition('bibliographic_record', 'normalization', 'low', true),
            'bib.author.missing' => $this->definition('bibliographic_record', 'completeness', 'high'),
            'bib.author.suspicious' => $this->definition('bibliographic_record', 'validity', 'medium'),
            'bib.year.missing' => $this->definition('bibliographic_record', 'completeness', 'medium'),
            'bib.year.invalid' => $this->definition('bibliographic_record', 'validity', 'high'),
            'bib.isbn.invalid' => $this->definition('bibliographic_record', 'identifier', 'high'),
            'bib.isbn.not_normalized' => $this->definition('bibliographic_record', 'normalization', 'low', true),
            'bib.udc.missing' => $this->definition('bibliographic_record', 'classification', 'high'),
            'bib.udc.unknown' => $this->definition('bibliographic_record', 'classification', 'medium'),
            'bib.language.invalid' => $this->definition('bibliographic_record', 'classification', 'medium'),
            'copy.inventory.missing' => $this->definition('book_copy', 'identifier', 'critical'),
            'copy.barcode.missing' => $this->definition('book_copy', 'identifier', 'high'),
            'copy.status.invalid' => $this->definition('book_copy', 'integrity', 'critical'),
            'copy.location.missing' => $this->definition('book_copy', 'completeness', 'medium'),
            'copy.loan_state.conflict' => $this->definition('book_copy', 'process', 'critical'),
            'copy.reservation_state.conflict' => $this->definition('book_copy', 'process', 'high'),
            'copy.price.negative' => $this->definition('book_copy', 'validity', 'high'),
            'reader.profile.invalid' => $this->definition('reader_profile', 'completeness', 'high'),
            'reader.block.invalid' => $this->definition('reader_profile', 'process', 'high'),
            'loan.dates.invalid' => $this->definition('loan', 'process', 'critical'),
            'fine.state.invalid' => $this->definition('fine', 'process', 'high'),
            'reservation.state.invalid' => $this->definition('reservation', 'process', 'high'),
            'encoding.replacement_character' => $this->definition('bibliographic_record', 'encoding', 'high'),
            'encoding.null_byte' => $this->definition('bibliographic_record', 'encoding', 'high'),
            'encoding.control_character' => $this->definition('bibliographic_record', 'encoding', 'medium', true),
            'encoding.non_breaking_space' => $this->definition('bibliographic_record', 'encoding', 'low', true),
            'encoding.mojibake' => $this->definition('bibliographic_record', 'encoding', 'high'),
            'encoding.mixed_alphabet' => $this->definition('bibliographic_record', 'encoding', 'low'),
            'encoding.legacy_kazakh_glyph' => $this->definition('bibliographic_record', 'encoding', 'medium'),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function inspect(Model $entity): array
    {
        return match (true) {
            $entity instanceof BibliographicRecord => $this->bibliographic($entity),
            $entity instanceof BookCopy => $this->copy($entity),
            $entity instanceof ReaderProfile => $this->reader($entity),
            $entity instanceof Loan => $this->loan($entity),
            $entity instanceof Fine => $this->fine($entity),
            $entity instanceof Reservation => $this->reservation($entity),
            default => [],
        };
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function bibliographic(BibliographicRecord $record): array
    {
        $issues = [];
        $title = trim((string) $record->title);
        $authorRequired = ! in_array($record->resource_type, ['periodical', 'journal'], true);

        if ($title === '') {
            $issues[] = $this->violation('bib.title.missing', 'title', $record->title, 'A non-empty descriptive title');
        } elseif (mb_strlen($title) === 1 || preg_match('/^\d+$/u', $title) || preg_match('/^(?:unknown|untitled|test|без названия|нет данных)$/iu', $title)) {
            $issues[] = $this->violation('bib.title.suspicious', 'title', $record->title, 'A meaningful source-backed title');
        }
        if (preg_match('/\s{2,}/u', (string) $record->title)) {
            $issues[] = $this->violation('bib.title.spacing', 'title', $record->title, 'Single spaces', preg_replace('/\s+/u', ' ', trim((string) $record->title)));
        }
        if ($authorRequired && trim((string) $record->primary_author) === '') {
            $issues[] = $this->violation('bib.author.missing', 'primary_author', null, 'Personal or corporate author');
        } elseif ($record->primary_author && (preg_match('/^\d+$/', trim($record->primary_author)) || mb_strlen(trim($record->primary_author)) < 3)) {
            $issues[] = $this->violation('bib.author.suspicious', 'primary_author', $record->primary_author, 'A source-backed author name');
        }

        $minimumYear = (int) Setting::valueFor('data_quality_min_publication_year', 1450);
        $maximumYear = (int) Setting::valueFor('data_quality_max_future_years', 1) + (int) now()->format('Y');
        if ($record->publication_year === null) {
            $issues[] = $this->violation('bib.year.missing', 'publication_year', null, "{$minimumYear}–{$maximumYear}");
        } elseif ($record->publication_year < $minimumYear || $record->publication_year > $maximumYear || in_array($record->publication_year, [0, 1, 9999], true)) {
            $issues[] = $this->violation('bib.year.invalid', 'publication_year', $record->publication_year, "{$minimumYear}–{$maximumYear}");
        }

        if ($record->isbn) {
            $isbn = $this->isbn->validate($record->isbn);
            if (! $isbn['valid']) {
                $issues[] = $this->violation('bib.isbn.invalid', 'isbn', $record->isbn, 'Valid ISBN-10 or ISBN-13 checksum');
            } elseif ($isbn['isbn'] !== $record->isbn) {
                $issues[] = $this->violation('bib.isbn.not_normalized', 'isbn', $record->isbn, $isbn['isbn'], $isbn['isbn']);
            }
        }

        if (! $record->udc_code) {
            $issues[] = $this->violation('bib.udc.missing', 'udc_code', null, 'A code from the UDC dictionary');
        } elseif (! UdcCode::query()->where('code', $record->udc_code)->exists()) {
            $issues[] = $this->violation('bib.udc.unknown', 'udc_code', $record->udc_code, 'A code from the UDC dictionary');
        }
        if (! in_array($record->language, BibliographicRecord::LANGUAGES, true)) {
            $issues[] = $this->violation('bib.language.invalid', 'language', $record->language, implode(', ', BibliographicRecord::LANGUAGES));
        }

        foreach (['title', 'subtitle', 'primary_author', 'publisher', 'annotation'] as $field) {
            if (is_string($record->{$field}) && $record->{$field} !== '') {
                foreach ($this->encoding->inspect($record->{$field}, $field) as $encodingIssue) {
                    $issues[] = $this->violation(
                        $encodingIssue['code'],
                        $field,
                        $encodingIssue['value'],
                        'Valid UTF-8 text verified against the source',
                        $encodingIssue['suggestion'],
                        $encodingIssue['context'] + ['unambiguous' => $encodingIssue['unambiguous']],
                    );
                }
            }
        }

        return $issues;
    }

    /** @return list<array<string,mixed>> */
    private function copy(BookCopy $copy): array
    {
        $issues = [];
        if (trim((string) $copy->inventory_number) === '') {
            $issues[] = $this->violation('copy.inventory.missing', 'inventory_number', $copy->inventory_number, 'Unique inventory number');
        }
        if (trim((string) $copy->barcode) === '') {
            $issues[] = $this->violation('copy.barcode.missing', 'barcode', $copy->barcode, 'Unique scannable barcode');
        }
        if (! in_array($copy->status, BookCopy::STATUSES, true)) {
            $issues[] = $this->violation('copy.status.invalid', 'status', $copy->status, implode(', ', BookCopy::STATUSES));
        }
        if ($copy->branch_id === null || $copy->fund_id === null || trim((string) $copy->shelf_location) === '') {
            $issues[] = $this->violation('copy.location.missing', 'location', json_encode($copy->only(['branch_id', 'fund_id', 'shelf_location'])), 'Branch, fund and shelf');
        }
        if ($copy->price !== null && (float) $copy->price < 0) {
            $issues[] = $this->violation('copy.price.negative', 'price', $copy->price, 'A non-negative price');
        }
        $hasLoan = $copy->loans()->whereIn('status', ['active', 'overdue'])->whereNull('returned_at')->exists();
        if (($copy->status === 'issued') !== $hasLoan || (in_array($copy->status, ['lost', 'written_off'], true) && $hasLoan)) {
            $issues[] = $this->violation('copy.loan_state.conflict', 'status', $copy->status, 'Copy status consistent with its open loan');
        }
        $hasReservation = $copy->reservations()->whereIn('status', ['confirmed', 'ready_for_pickup'])->exists();
        if ($copy->status === 'reserved' && ! $hasReservation) {
            $issues[] = $this->violation('copy.reservation_state.conflict', 'status', $copy->status, 'An active reservation for a reserved copy');
        }

        return $issues;
    }

    /** @return list<array<string,mixed>> */
    private function reader(ReaderProfile $profile): array
    {
        $issues = [];
        if (! $profile->user || trim((string) $profile->ticket_number) === '' || ! in_array($profile->category, ReaderProfile::CATEGORIES, true)) {
            $issues[] = $this->violation('reader.profile.invalid', null, json_encode($profile->only(['user_id', 'ticket_number', 'category'])), 'Linked user, ticket and valid category');
        }
        if ($profile->status === 'blocked' && trim((string) $profile->block_reason) === '') {
            $issues[] = $this->violation('reader.block.invalid', 'block_reason', null, 'A documented reason for blocking');
        }

        return $issues;
    }

    /** @return list<array<string,mixed>> */
    private function loan(Loan $loan): array
    {
        return ($loan->due_at->lt($loan->issued_at) || ($loan->returned_at && $loan->returned_at->lt($loan->issued_at)))
            ? [$this->violation('loan.dates.invalid', 'dates', json_encode($loan->only(['issued_at', 'due_at', 'returned_at'])), 'due/return dates not earlier than issue date')]
            : [];
    }

    /** @return list<array<string,mixed>> */
    private function fine(Fine $fine): array
    {
        return ($fine->status === 'paid' && $fine->resolved_at === null) || trim((string) $fine->reason) === ''
            ? [$this->violation('fine.state.invalid', 'status', json_encode($fine->only(['status', 'reason', 'resolved_at'])), 'Reason and resolution date consistent with status')]
            : [];
    }

    /** @return list<array<string,mixed>> */
    private function reservation(Reservation $reservation): array
    {
        $hasLinkedLoan = $reservation->assigned_copy_id !== null
            && Loan::query()
                ->where('user_id', $reservation->user_id)
                ->where('copy_id', $reservation->assigned_copy_id)
                ->where('issued_at', '>=', $reservation->created_at)
                ->exists();

        return $reservation->status === 'fulfilled' && ! $hasLinkedLoan
            ? [$this->violation('reservation.state.invalid', 'status', $reservation->status, 'A linked loan for a fulfilled reservation')]
            : [];
    }

    /** @return array{entity:string,category:string,severity:string,auto_fixable:bool} */
    private function definition(string $entity, string $category, string $severity, bool $autoFixable = false): array
    {
        return compact('entity', 'category', 'severity') + ['auto_fixable' => $autoFixable];
    }

    /** @return array<string,mixed> */
    private function violation(string $code, ?string $field, mixed $value, string $expected, mixed $suggestion = null, array $context = []): array
    {
        $definition = $this->catalogue()[$code];

        return [
            'code' => $code,
            'category' => $definition['category'],
            'severity' => $definition['severity'],
            'field' => $field,
            'value' => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            'expected' => $expected,
            'description' => __('data_quality.rules.'.$code),
            'suggested_action' => $suggestion === null ? __('data_quality.actions.review_source') : (string) $suggestion,
            'context' => $context,
        ];
    }
}
