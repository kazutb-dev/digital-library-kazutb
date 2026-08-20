<?php

namespace App\Services\Reports;

use App\Models\Catalog\BookCopy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validated, canonical filters shared by every operational library report.
 *
 * date_from/date_to remain accepted as transport aliases for the historical
 * report screen.  Once constructed, consumers only ever see from/to.
 */
final readonly class ReportFilters
{
    public const PRESETS = [
        'day', 'yesterday', 'week', 'previous_week', 'month', 'previous_month',
        'quarter', 'year', 'custom',
    ];

    public const USER_SEGMENTS = [
        'student', 'bachelor', 'master', 'doctoral', 'teacher', 'faculty',
        'researcher', 'staff', 'guest',
    ];

    public const ACCESS_TYPES = [
        'public', 'open', 'open_access', 'licensed', 'partner', 'internal',
        'authenticated', 'campus', 'remote_auth', 'campus_only', 'restricted', 'metadata_only',
        'student', 'faculty', 'staff', 'librarian', 'restricted_roles', 'embargoed',
        'free', 'reading_room', 'limited',
    ];

    public const OPERATIONS = [
        'issue', 'return', 'renewal', 'reservation', 'visit', 'view', 'stream', 'download', 'outbound_click',
        'access_denied', 'expired_click', 'unsafe_destination', 'login', 'error',
    ];

    public const ACQUISITION_SOURCES = BookCopy::ACQUISITION_SOURCES;

    public function __construct(
        public string $preset,
        public Carbon $from,
        public Carbon $to,
        public ?int $branchId = null,
        public ?int $fundId = null,
        public ?string $resourceType = null,
        public ?string $userSegment = null,
        public ?string $language = null,
        public ?string $udc = null,
        public ?string $status = null,
        public ?string $subject = null,
        public ?string $accessType = null,
        public ?string $operation = null,
        public ?string $acquisitionSource = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $input = $request->all();
        $input['from'] = $input['from'] ?? $input['date_from'] ?? null;
        $input['to'] = $input['to'] ?? $input['date_to'] ?? null;
        $input['preset'] = $input['preset'] ?? (($input['from'] || $input['to']) ? 'custom' : 'month');

        $validated = Validator::make($input, self::rules())->validate();
        $preset = $validated['preset'];
        [$from, $to] = self::period($preset, $validated['from'] ?? null, $validated['to'] ?? null);
        if ($preset === 'custom') {
            $timezone = (string) config('app.library_timezone', 'Asia/Almaty');
            $days = (int) $from->copy()->timezone($timezone)->startOfDay()
                ->diffInDays($to->copy()->timezone($timezone)->startOfDay()) + 1;
            $maximum = max(1, (int) config('library.reports.max_custom_period_days', 366));
            if ($days > $maximum) {
                throw ValidationException::withMessages([
                    'to' => trans()->has('analytics.validation.period_too_large')
                        ? __('analytics.validation.period_too_large', ['days' => $maximum])
                        : "The report period may not exceed {$maximum} days.",
                ]);
            }
        }

        return new self(
            preset: $preset,
            from: $from,
            to: $to,
            branchId: isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            fundId: isset($validated['fund_id']) ? (int) $validated['fund_id'] : null,
            resourceType: self::nullableString($validated['resource_type'] ?? null),
            userSegment: self::nullableString($validated['user_segment'] ?? null),
            language: self::nullableString($validated['language'] ?? null),
            udc: self::nullableString($validated['udc'] ?? null),
            status: self::nullableString($validated['status'] ?? null),
            subject: self::nullableString($validated['subject'] ?? null),
            accessType: self::nullableString($validated['access_type'] ?? null),
            operation: self::nullableString($validated['operation'] ?? null),
            acquisitionSource: self::nullableString($validated['acquisition_source'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'preset' => ['required', Rule::in(self::PRESETS)],
            'from' => ['nullable', 'required_if:preset,custom', 'date'],
            'to' => ['nullable', 'required_if:preset,custom', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'fund_id' => ['nullable', 'integer', 'min:1'],
            'resource_type' => ['nullable', 'string', 'max:64', 'regex:/^[\pL\pN_.:\/-]+$/u'],
            'user_segment' => ['nullable', Rule::in(self::USER_SEGMENTS)],
            'language' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z-]+$/'],
            'udc' => ['nullable', 'string', 'max:64', 'regex:/^[\pL\pN.:\/()\- ]+$/u'],
            'status' => ['nullable', 'string', 'max:64', 'regex:/^[\pL\pN_.:\/-]+$/u'],
            'subject' => ['nullable', 'string', 'max:160'],
            'access_type' => ['nullable', Rule::in(self::ACCESS_TYPES)],
            'operation' => ['nullable', Rule::in(self::OPERATIONS)],
            'acquisition_source' => ['nullable', Rule::in(self::ACQUISITION_SOURCES)],
        ];
    }

    /** @return array<string, scalar|null> */
    public function toArray(): array
    {
        $timezone = (string) config('app.library_timezone', 'Asia/Almaty');

        return [
            'preset' => $this->preset,
            // Human-facing report dates follow the library business day. The
            // Carbon properties themselves remain UTC SQL boundaries.
            'from' => $this->from->copy()->timezone($timezone)->toDateString(),
            'to' => $this->to->copy()->timezone($timezone)->toDateString(),
            'branch_id' => $this->branchId,
            'fund_id' => $this->fundId,
            'resource_type' => $this->resourceType,
            'user_segment' => $this->userSegment,
            'language' => $this->language,
            'udc' => $this->udc,
            'status' => $this->status,
            'subject' => $this->subject,
            'access_type' => $this->accessType,
            'operation' => $this->operation,
            'acquisition_source' => $this->acquisitionSource,
        ];
    }

    /** @return array<string, scalar> */
    public function query(): array
    {
        return array_filter($this->toArray(), static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function permitsOperation(string ...$operations): bool
    {
        return $this->operation === null || in_array($this->operation, $operations, true);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function period(string $preset, ?string $from, ?string $to): array
    {
        $timezone = (string) config('app.library_timezone', 'Asia/Almaty');
        $now = now($timezone);

        return match ($preset) {
            'day' => [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
            'yesterday' => [
                $now->copy()->subDay()->startOfDay()->utc(),
                $now->copy()->subDay()->endOfDay()->utc(),
            ],
            'week' => [$now->copy()->startOfWeek()->utc(), $now->copy()->endOfWeek()->utc()],
            'previous_week' => [
                $now->copy()->subWeek()->startOfWeek()->utc(),
                $now->copy()->subWeek()->endOfWeek()->utc(),
            ],
            'month' => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
            'previous_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->utc(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->utc(),
            ],
            'quarter' => [$now->copy()->startOfQuarter()->utc(), $now->copy()->endOfQuarter()->utc()],
            'year' => [$now->copy()->startOfYear()->utc(), $now->copy()->endOfYear()->utc()],
            default => [
                Carbon::parse((string) $from, $timezone)->startOfDay()->utc(),
                Carbon::parse((string) $to, $timezone)->endOfDay()->utc(),
            ],
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
