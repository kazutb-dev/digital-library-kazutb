<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BibliographicRecordTranslation;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\Setting;
use App\Services\Library\IsbnService;
use Illuminate\Database\Eloquent\Model;

class DataQualityRuleRegistry
{
    public const VERSION = '2026.08.29.1';

    /** ISO 639 values found in the verified MARC import. */
    public const LEGACY_LANGUAGE_CODES = [
        'kaz' => 'kk',
        'rus' => 'ru',
        'eng' => 'en',
        'ger' => 'other',
        'chi' => 'other',
    ];

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
            'bib.title.missing' => $this->definition('bibliographic_record', 'required_fields', 'critical', false, 'error'),
            'bib.title.suspicious' => $this->definition('bibliographic_record', 'title', 'high'),
            'bib.title.truncated' => $this->definition('bibliographic_record', 'title', 'medium'),
            'bib.title.spacing' => $this->definition('bibliographic_record', 'title', 'low', true, 'recommendation'),
            'bib.author.missing' => $this->definition('bibliographic_record', 'author', 'medium'),
            'bib.author.suspicious' => $this->definition('bibliographic_record', 'author', 'medium'),
            'bib.author.spacing' => $this->definition('bibliographic_record', 'author', 'low', true, 'recommendation'),
            'bib.year.missing' => $this->definition('bibliographic_record', 'year', 'medium'),
            'bib.year.invalid' => $this->definition('bibliographic_record', 'year', 'high', false, 'error'),
            'bib.isbn.invalid' => $this->definition('bibliographic_record', 'isbn', 'high', false, 'error'),
            'bib.isbn.not_normalized' => $this->definition('bibliographic_record', 'isbn', 'low', true, 'recommendation'),
            'bib.udc.missing' => $this->definition('bibliographic_record', 'udc', 'medium'),
            'bib.udc.invalid_format' => $this->definition('bibliographic_record', 'udc', 'medium'),
            'bib.author_mark.missing' => $this->definition('bibliographic_record', 'udc', 'low', false, 'recommendation'),
            'bib.language.invalid' => $this->definition('bibliographic_record', 'language', 'high', false, 'error'),
            'bib.language.legacy_code' => $this->definition('bibliographic_record', 'language', 'low', false, 'recommendation'),
            'bib.language.possible_mismatch' => $this->definition('bibliographic_record', 'language', 'medium'),
            'bib.resource_type.invalid' => $this->definition('bibliographic_record', 'required_fields', 'high', false, 'error'),
            'bib.physical.no_copies' => $this->definition('bibliographic_record', 'relations', 'high'),
            'bib.duplicate.exact' => $this->definition('bibliographic_record', 'duplicates', 'high'),
            'bib.duplicate.probable' => $this->definition('bibliographic_record', 'duplicates', 'medium'),
            'bib.duplicate.possible' => $this->definition('bibliographic_record', 'duplicates', 'low'),
            'bib.translation.locale_invalid' => $this->definition('bibliographic_record', 'language', 'high', false, 'error'),
            'bib.translation.title_empty' => $this->definition('bibliographic_record', 'required_fields', 'high', false, 'error'),
            'bib.translation.identical' => $this->definition('bibliographic_record', 'language', 'low'),
            'bib.translation.needs_review' => $this->definition('bibliographic_record', 'language', 'medium'),
            'bib.translation.encoding' => $this->definition('bibliographic_record', 'encoding', 'high', false, 'error'),
            'copy.inventory.missing' => $this->definition('book_copy', 'copies', 'critical', false, 'error'),
            'copy.barcode.missing' => $this->definition('book_copy', 'copies', 'low', false, 'recommendation'),
            'copy.status.invalid' => $this->definition('book_copy', 'copies', 'critical', false, 'error'),
            'copy.condition.invalid' => $this->definition('book_copy', 'copies', 'high', false, 'error'),
            'copy.record.missing' => $this->definition('book_copy', 'relations', 'critical', false, 'error'),
            'copy.location.missing' => $this->definition('book_copy', 'locations', 'medium'),
            'copy.location.inactive' => $this->definition('book_copy', 'locations', 'high', false, 'error'),
            'copy.location.fund_branch_conflict' => $this->definition('book_copy', 'locations', 'high', false, 'error'),
            'copy.loan_state.conflict' => $this->definition('book_copy', 'copies', 'critical', false, 'error'),
            'copy.reservation_state.conflict' => $this->definition('book_copy', 'copies', 'high', false, 'error'),
            'copy.price.negative' => $this->definition('book_copy', 'copies', 'high', false, 'error'),
            'reader.profile.invalid' => $this->definition('reader_profile', 'completeness', 'high'),
            'reader.block.invalid' => $this->definition('reader_profile', 'process', 'high'),
            'loan.dates.invalid' => $this->definition('loan', 'process', 'critical'),
            'fine.state.invalid' => $this->definition('fine', 'process', 'high'),
            'reservation.state.invalid' => $this->definition('reservation', 'process', 'high'),
            'encoding.replacement_character' => $this->definition('bibliographic_record', 'encoding', 'high', false, 'error'),
            'encoding.null_byte' => $this->definition('bibliographic_record', 'encoding', 'high', false, 'error'),
            'encoding.control_character' => $this->definition('bibliographic_record', 'encoding', 'medium', true),
            'encoding.non_breaking_space' => $this->definition('bibliographic_record', 'encoding', 'low', true),
            'encoding.mojibake' => $this->definition('bibliographic_record', 'encoding', 'high', false, 'error'),
            'encoding.html_entity' => $this->definition('bibliographic_record', 'encoding', 'medium'),
            'encoding.question_replacement' => $this->definition('bibliographic_record', 'encoding', 'high', false, 'error'),
            'encoding.mixed_alphabet' => $this->definition('bibliographic_record', 'encoding', 'low'),
            'encoding.legacy_kazakh_glyph' => $this->definition('bibliographic_record', 'encoding', 'high', false, 'error'),
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
        } elseif (mb_strlen($title) === 1
            || preg_match('/^\d+$/u', $title)
            || preg_match('/^(?:unknown|untitled|test|без названия|нет данных)$/iu', $title)
            || preg_match('/^(.)\1{5,}$/u', $title)) {
            $issues[] = $this->violation('bib.title.suspicious', 'title', $record->title, 'A meaningful source-backed title');
        }
        if (mb_strlen($title) === 250 && preg_match('/[\pL\pN]$/u', $title)) {
            $issues[] = $this->violation('bib.title.truncated', 'title', $record->title, 'The complete title verified against MARC or the physical item');
        }
        if (preg_match('/\s{2,}/u', (string) $record->title)) {
            $issues[] = $this->violation('bib.title.spacing', 'title', $record->title, 'Single spaces', preg_replace('/\s+/u', ' ', trim((string) $record->title)));
        }
        if ($authorRequired && trim((string) $record->primary_author) === '') {
            $issues[] = $this->violation('bib.author.missing', 'primary_author', null, 'Personal or corporate author');
        } elseif ($record->primary_author && (preg_match('/^\d+$/', trim($record->primary_author)) || mb_strlen(trim($record->primary_author)) < 3)) {
            $issues[] = $this->violation('bib.author.suspicious', 'primary_author', $record->primary_author, 'A source-backed author name');
        }
        if (preg_match('/\s{2,}/u', (string) $record->primary_author)) {
            $issues[] = $this->violation('bib.author.spacing', 'primary_author', $record->primary_author, 'Single spaces', preg_replace('/\s+/u', ' ', trim((string) $record->primary_author)));
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
            $issues[] = $this->violation('bib.udc.missing', 'udc_code', null, 'A source-backed UDC code when classification is expected');
        } elseif (! preg_match('/^[0-9A-Za-zА-Яа-яӘәҒғҚқҢңӨөҰұҮүҺһІі.:+()\/=\[\]\'" -]+$/u', trim((string) $record->udc_code))) {
            $issues[] = $this->violation('bib.udc.invalid_format', 'udc_code', $record->udc_code, 'A structurally valid source-backed UDC code');
        }
        if (! $record->author_mark) {
            $issues[] = $this->violation('bib.author_mark.missing', 'author_mark', null, 'Author mark where the local classification workflow requires it');
        }

        $knownLanguages = [...BibliographicRecord::LANGUAGES, ...array_keys(self::LEGACY_LANGUAGE_CODES)];
        if (! in_array($record->language, $knownLanguages, true)) {
            $issues[] = $this->violation('bib.language.invalid', 'language', $record->language, implode(', ', $knownLanguages));
        } elseif (isset(self::LEGACY_LANGUAGE_CODES[$record->language])) {
            $issues[] = $this->violation(
                'bib.language.legacy_code',
                'language',
                $record->language,
                self::LEGACY_LANGUAGE_CODES[$record->language],
                self::LEGACY_LANGUAGE_CODES[$record->language],
            );
        }
        if (in_array($record->language, ['ru', 'rus', 'en', 'eng'], true) && $this->containsKazakhLetters($title)) {
            $issues[] = $this->violation(
                'bib.language.possible_mismatch',
                'language',
                $record->language,
                'Language verified against the title and the MARC source',
                null,
                ['title_confidence' => $record->kazakhTitleConfidence()],
            );
        }
        if (! in_array($record->resource_type, BibliographicRecord::RESOURCE_TYPES, true)) {
            $issues[] = $this->violation('bib.resource_type.invalid', 'resource_type', $record->resource_type, implode(', ', BibliographicRecord::RESOURCE_TYPES));
        }
        if (! in_array($record->resource_type, ['ebook', 'digital_document', 'article'], true)) {
            $copyCount = array_key_exists('copies_count', $record->getAttributes())
                ? (int) $record->getAttribute('copies_count')
                : $record->copies()->count();
            if ($copyCount === 0) {
                $issues[] = $this->violation('bib.physical.no_copies', 'copies', 0, 'At least one linked physical copy, or a corrected resource type');
            }
        }

        $translations = $record->relationLoaded('translations') ? $record->translations : $record->translations()->get();
        foreach ($translations as $translation) {
            $field = 'translations.'.$translation->locale;
            if (! in_array($translation->locale, BibliographicRecordTranslation::LOCALES, true)) {
                $issues[] = $this->violation('bib.translation.locale_invalid', $field, $translation->locale, 'kk, ru or en');
            }
            if (trim((string) $translation->title) === '') {
                $issues[] = $this->violation('bib.translation.title_empty', $field.'.title', $translation->title, 'A non-empty translated title');
            }
            if ($translation->locale !== $record->language
                && mb_strtolower(trim((string) $translation->title)) === mb_strtolower(trim((string) $record->title))) {
                $issues[] = $this->violation('bib.translation.identical', $field.'.title', $translation->title, 'Human review of an intentionally identical title');
            }
            if ($translation->translation_status === 'needs_review') {
                $issues[] = $this->violation('bib.translation.needs_review', $field, $translation->translation_status, 'Reviewed editorial metadata');
            }
            foreach (['title', 'annotation'] as $translationField) {
                $value = (string) ($translation->{$translationField} ?? '');
                if ($value !== '' && ! mb_check_encoding($value, 'UTF-8')) {
                    $issues[] = $this->violation('bib.translation.encoding', $field.'.'.$translationField, $value, 'Valid UTF-8 text');
                }
            }
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
            $issues[] = $this->violation('copy.barcode.missing', 'barcode', $copy->barcode, 'A barcode when this legacy copy is next handled or inventoried');
        }
        if (! in_array($copy->status, BookCopy::STATUSES, true)) {
            $issues[] = $this->violation('copy.status.invalid', 'status', $copy->status, implode(', ', BookCopy::STATUSES));
        }
        if (! in_array($copy->condition, BookCopy::CONDITIONS, true)) {
            $issues[] = $this->violation('copy.condition.invalid', 'condition', $copy->condition, implode(', ', BookCopy::CONDITIONS));
        }
        $recordExists = array_key_exists('dq_record_exists', $copy->getAttributes())
            ? (bool) $copy->getAttribute('dq_record_exists')
            : $copy->bibliographicRecord()->exists();
        if (! $recordExists) {
            $issues[] = $this->violation('copy.record.missing', 'bibliographic_record_id', $copy->bibliographic_record_id, 'A valid linked bibliographic record');
        }
        // Recovered copies legitimately carry MARC-SQL sigla/shelf evidence
        // without a normalized branch/fund. That is not a missing location:
        // T090f and TRACKINDEX remain authoritative until staff classifies it.
        $hasStoragePosition = collect([
            $copy->storage_sigla,
            $copy->sigla_code,
            $copy->service_point_code,
            $copy->shelf_index,
            $copy->shelf_location,
        ])->contains(static fn (mixed $value): bool => trim((string) $value) !== '');
        if ($copy->branch_id === null && $copy->fund_id === null && ! $hasStoragePosition) {
            $issues[] = $this->violation('copy.location.missing', 'location', json_encode($copy->only(['branch_id', 'fund_id', 'storage_sigla', 'sigla_code', 'service_point_code', 'shelf_index', 'shelf_location'])), 'A branch, fund, source-backed sigla, service point or shelf position');
        }
        $branch = $copy->relationLoaded('branch') ? $copy->branch : $copy->branch()->first();
        $fund = $copy->relationLoaded('fund') ? $copy->fund : $copy->fund()->first();
        if (($branch && (! $branch->is_active || $branch->trashed())) || ($fund && (! $fund->is_active || $fund->trashed()))) {
            $issues[] = $this->violation('copy.location.inactive', 'location', json_encode($copy->only(['branch_id', 'fund_id'])), 'An active library point and fund');
        }
        if ($branch && $fund && $fund->branch_id !== null && (int) $fund->branch_id !== (int) $branch->getKey()) {
            $issues[] = $this->violation('copy.location.fund_branch_conflict', 'location', json_encode($copy->only(['branch_id', 'fund_id'])), 'The fund must belong to the selected library point');
        }
        if ($copy->price !== null && (float) $copy->price < 0) {
            $issues[] = $this->violation('copy.price.negative', 'price', $copy->price, 'A non-negative price');
        }
        $hasLoan = array_key_exists('dq_has_active_loan', $copy->getAttributes())
            ? (bool) $copy->getAttribute('dq_has_active_loan')
            : $copy->loans()->whereIn('status', ['active', 'overdue'])->whereNull('returned_at')->exists();
        if (in_array($copy->status, ['issued', 'overdue'], true) !== $hasLoan || (in_array($copy->status, ['lost', 'written_off', 'under_repair'], true) && $hasLoan)) {
            $issues[] = $this->violation('copy.loan_state.conflict', 'status', $copy->status, 'Copy status consistent with its open loan');
        }
        $hasReservation = array_key_exists('dq_has_active_reservation', $copy->getAttributes())
            ? (bool) $copy->getAttribute('dq_has_active_reservation')
            : $copy->reservations()->whereIn('status', ['confirmed', 'ready_for_pickup'])->exists();
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

    /** @return array{entity:string,category:string,severity:string,auto_fixable:bool,type:string} */
    private function definition(string $entity, string $category, string $severity, bool $autoFixable = false, string $type = 'warning'): array
    {
        return compact('entity', 'category', 'severity', 'type') + ['auto_fixable' => $autoFixable];
    }

    /** @param array{record:BibliographicRecord,score:float,level:string,details:array<string,mixed>} $match */
    public function duplicateViolation(array $match): array
    {
        $code = 'bib.duplicate.'.($match['level'] === 'exact' ? 'exact' : ($match['level'] === 'probable' ? 'probable' : 'possible'));

        return $this->violation(
            $code,
            'duplicate_candidate',
            (string) $match['record']->getKey(),
            'A librarian decision after side-by-side comparison',
            null,
            [
                'candidate_id' => $match['record']->getKey(),
                'candidate_title' => $match['record']->title,
                'score' => $match['score'],
                'match_level' => $match['level'],
                'match_details' => $match['details'],
            ],
        );
    }

    private function containsKazakhLetters(string $value): bool
    {
        return preg_match('/['.preg_quote(implode('', BibliographicRecord::KAZAKH_ONLY_LETTERS), '/').']/u', $value) === 1;
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
