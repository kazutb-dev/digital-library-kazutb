<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\NotificationSetting;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $notificationSettings = NotificationSetting::query()->get()->keyBy('event_type');

        return view('admin.settings.index', [
            'settings' => Setting::query()->orderBy('group')->orderBy('key')->get()->keyBy('key'),
            'notificationSettings' => collect(NotificationSetting::EVENT_TYPES)
                ->map(fn (string $eventType): NotificationSetting => $notificationSettings[$eventType]
                    ?? new NotificationSetting(['event_type' => $eventType, 'in_app_enabled' => true, 'email_enabled' => true])),
            'demoLoginEnabled' => (bool) config('demo_users.enabled'),
            'legacyDemoLoginEnabled' => (bool) config('demo_auth.enabled'),
            'security' => [
                'password_hashing' => (string) config('hashing.driver', 'bcrypt'),
                'rbac_engine' => 'spatie/laravel-permission',
                'session_driver' => (string) config('session.driver'),
                'environment' => (string) config('app.env'),
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'max_active_reservations' => ['required', 'integer', 'min:1', 'max:100'],
            'reservation_lifespan_days' => ['required', 'integer', 'min:1', 'max:365'],
            'reservation_queue_enabled' => ['sometimes', 'boolean'],
            'reservation_hold_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'reservation_max_extensions' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'reservation_extension_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'reservation_manual_confirmation_required' => ['sometimes', 'boolean'],
            'reservation_interbranch_transfer_enabled' => ['sometimes', 'boolean'],
            'reservation_expiry_reminder_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'reservation_queue_override_enabled' => ['sometimes', 'boolean'],
            'reservation_blocking_on_fines' => ['sometimes', 'boolean'],
            'max_active_loans' => ['required', 'integer', 'min:1', 'max:100'],
            'standard_loan_period_days' => ['required', 'integer', 'min:1', 'max:365'],
            'reference_loan_period_days' => ['required', 'integer', 'min:1', 'max:365'],
            // §9.3 dynamic loan-period scale. The two ceilings must ascend so
            // the tiers stay meaningful.
            'loan_period_scarce_max_copies' => ['required', 'integer', 'min:1', 'max:1000'],
            'loan_period_scarce_days' => ['required', 'integer', 'min:1', 'max:365'],
            'loan_period_standard_max_copies' => ['required', 'integer', 'min:1', 'max:1000', 'gt:loan_period_scarce_max_copies'],
            'loan_period_standard_days' => ['required', 'integer', 'min:1', 'max:365'],
            'loan_period_abundant_days' => ['required', 'integer', 'min:1', 'max:365'],
            'renewal_allowed' => ['required', 'boolean'],
            'renewal_period_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_renewals' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'manual_due_date_max_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'inventory_batch_scan_limit' => ['sometimes', 'integer', 'min:10', 'max:100000'],
            'barcode_format' => ['sometimes', Rule::in(['code128'])],
            'barcode_label_size' => ['sometimes', 'string', 'max:20'],
            'overdue_blocking_enabled' => ['required', 'boolean'],
            'fine_per_overdue_day' => ['required', 'integer', 'min:0', 'max:100000'],
            'incident_resolution_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'replacement_year_tolerance' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'replacement_requires_senior_approval' => ['sometimes', 'boolean'],
            'replacement_exception_requires_director' => ['sometimes', 'boolean'],
            'monetary_compensation_allowed' => ['sometimes', 'boolean'],
            'incident_blocks_issues' => ['sometimes', 'boolean'],
            'replacement_required_severe' => ['sometimes', 'boolean'],
            'replacement_required_irreparable' => ['sometimes', 'boolean'],
            'incident_resolution_types' => ['sometimes', 'array', 'min:1'],
            'incident_resolution_types.*' => [Rule::in(CirculationIncidentCase::RESOLUTIONS)],
            'notification_channels' => ['required', 'array', 'min:1'],
            'notification_channels.*' => [Rule::in(['in_app', 'email'])],
            'news_categories' => ['required', 'string', 'max:2000'],
            'message_categories' => ['required', 'string', 'max:2000'],
            'default_ui_language' => ['required', Rule::in(['ru', 'kk', 'en'])],
            'results_per_page' => ['required', 'integer', 'min:10', 'max:100'],
            'catalog_page_size' => ['required', 'integer', 'min:6', 'max:60'],
            'data_quality_scan_chunk_size' => ['sometimes', 'integer', 'min:50', 'max:5000'],
            'data_quality_bulk_batch_limit' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'data_quality_duplicate_exact_threshold' => ['sometimes', 'numeric', 'min:70', 'max:100'],
            'data_quality_duplicate_probable_threshold' => ['sometimes', 'numeric', 'min:1', 'lt:data_quality_duplicate_exact_threshold'],
            'data_quality_min_publication_year' => ['sometimes', 'integer', 'min:1', 'max:2000'],
            'data_quality_max_future_years' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'data_quality_rescan_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'data_quality_staging_retention_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'data_quality_sla_critical_hours' => ['sometimes', 'integer', 'min:1', 'max:8760'],
            'data_quality_sla_high_hours' => ['sometimes', 'integer', 'min:1', 'max:8760'],
            'data_quality_sla_medium_hours' => ['sometimes', 'integer', 'min:1', 'max:8760'],
            'data_quality_bulk_approval_required' => ['sometimes', 'boolean'],
            'data_quality_merge_approval_required' => ['sometimes', 'boolean'],
            'data_quality_import_encodings' => ['sometimes', 'array', 'min:1'],
            'data_quality_import_encodings.*' => [Rule::in(['UTF-8', 'Windows-1251', 'ISO-8859-5'])],
        ]);

        $validated['renewal_allowed'] = $request->boolean('renewal_allowed');
        $validated['overdue_blocking_enabled'] = $request->boolean('overdue_blocking_enabled');
        foreach (['reservation_queue_enabled', 'reservation_manual_confirmation_required', 'reservation_interbranch_transfer_enabled', 'reservation_queue_override_enabled', 'reservation_blocking_on_fines', 'replacement_requires_senior_approval', 'replacement_exception_requires_director', 'monetary_compensation_allowed', 'incident_blocks_issues', 'replacement_required_severe', 'replacement_required_irreparable', 'data_quality_bulk_approval_required', 'data_quality_merge_approval_required'] as $booleanKey) {
            if ($request->has($booleanKey)) {
                $validated[$booleanKey] = $request->boolean($booleanKey);
            }
        }
        foreach ([
            'max_active_reservations',
            'reservation_lifespan_days',
            'reservation_hold_days', 'reservation_max_extensions', 'reservation_extension_hours', 'reservation_expiry_reminder_hours',
            'max_active_loans',
            'standard_loan_period_days',
            'reference_loan_period_days',
            'loan_period_scarce_max_copies',
            'loan_period_scarce_days',
            'loan_period_standard_max_copies',
            'loan_period_standard_days',
            'loan_period_abundant_days',
            'renewal_period_days',
            'max_renewals', 'manual_due_date_max_days', 'inventory_batch_scan_limit',
            'fine_per_overdue_day',
            'incident_resolution_days',
            'replacement_year_tolerance',
            'results_per_page',
            'catalog_page_size',
            'data_quality_scan_chunk_size',
            'data_quality_bulk_batch_limit',
            'data_quality_min_publication_year',
            'data_quality_max_future_years',
            'data_quality_rescan_days',
            'data_quality_staging_retention_days',
            'data_quality_sla_critical_hours',
            'data_quality_sla_high_hours',
            'data_quality_sla_medium_hours',
        ] as $integerKey) {
            if (array_key_exists($integerKey, $validated)) {
                $validated[$integerKey] = (int) $validated[$integerKey];
            }
        }
        $validated['news_categories'] = $this->listValue($validated['news_categories'], 'news_categories');
        $validated['message_categories'] = $this->listValue($validated['message_categories'], 'message_categories');

        $definitions = $this->definitions();

        DB::transaction(function () use ($validated, $definitions, $audit): void {
            foreach ($validated as $key => $value) {
                $definition = $definitions[$key];
                $setting = Setting::query()->where('key', $key)->lockForUpdate()->first()
                    ?? new Setting(['key' => $key]);
                $old = $setting->exists ? $setting->value : null;

                if ($setting->exists && $this->sameValue($old, $value)) {
                    continue;
                }

                $setting->fill([
                    'value' => $value,
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'description' => $definition['description'],
                ])->save();

                $audit->logRequired(
                    actionType: 'update',
                    entityType: 'setting',
                    entityId: $setting->getKey(),
                    oldValues: ['key' => $key, 'value' => $old],
                    newValues: ['key' => $key, 'value' => $value],
                    scope: 'system',
                );
            }
        });

        return back()->with('success', __('settings.saved'));
    }

    /**
     * Per-event notification channel matrix. Unchecked boxes are simply
     * absent from the payload, so each known event type is re-evaluated
     * explicitly against the submitted matrix.
     */
    public function updateNotifications(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'events' => ['nullable', 'array'],
            'events.*' => ['array'],
            'events.*.in_app' => ['nullable', 'in:1'],
            'events.*.email' => ['nullable', 'in:1'],
        ]);

        $matrix = (array) $request->input('events', []);

        DB::transaction(function () use ($matrix, $audit): void {
            foreach (NotificationSetting::EVENT_TYPES as $eventType) {
                $setting = NotificationSetting::query()
                    ->where('event_type', $eventType)
                    ->lockForUpdate()
                    ->first() ?? new NotificationSetting(['event_type' => $eventType]);

                $next = [
                    'in_app_enabled' => isset($matrix[$eventType]['in_app']),
                    'email_enabled' => isset($matrix[$eventType]['email']),
                ];
                $old = [
                    'in_app_enabled' => (bool) $setting->in_app_enabled,
                    'email_enabled' => (bool) $setting->email_enabled,
                ];

                if ($setting->exists && $old === $next) {
                    continue;
                }

                $setting->fill($next)->save();

                $audit->logRequired(
                    actionType: 'update',
                    entityType: 'setting',
                    entityId: 'notification:'.$eventType,
                    oldValues: ['event_type' => $eventType, ...$old],
                    newValues: ['event_type' => $eventType, ...$next],
                    scope: 'system',
                );
            }
        });

        return back()->with('success', __('settings.saved'));
    }

    /**
     * @return array<string, array{type: string, group: string, description: string}>
     */
    private function definitions(): array
    {
        return [
            'max_active_reservations' => ['type' => 'integer', 'group' => 'reservations', 'description' => 'Maximum active reservations per reader.'],
            'reservation_lifespan_days' => ['type' => 'integer', 'group' => 'reservations', 'description' => 'Reservation hold period in days.'],
            'reservation_queue_enabled' => ['type'=>'boolean','group'=>'reservations','description'=>'Enable unavailable-edition queue.'],
            'reservation_hold_days' => ['type'=>'integer','group'=>'reservations','description'=>'Pickup hold in days.'],
            'reservation_max_extensions' => ['type'=>'integer','group'=>'reservations','description'=>'Maximum pickup hold extensions.'],
            'reservation_extension_hours' => ['type'=>'integer','group'=>'reservations','description'=>'Hours added per hold extension.'],
            'reservation_manual_confirmation_required' => ['type'=>'boolean','group'=>'reservations','description'=>'Require manual confirmation.'],
            'reservation_interbranch_transfer_enabled' => ['type'=>'boolean','group'=>'reservations','description'=>'Enable interbranch transfers.'],
            'reservation_expiry_reminder_hours' => ['type'=>'integer','group'=>'reservations','description'=>'Hours before hold expiry reminder.'],
            'reservation_queue_override_enabled' => ['type'=>'boolean','group'=>'reservations','description'=>'Enable reasoned queue overrides.'],
            'reservation_blocking_on_fines' => ['type'=>'boolean','group'=>'reservations','description'=>'Block reservations while fines are pending.'],
            'max_active_loans' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Maximum active loans per reader.'],
            'standard_loan_period_days' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Standard loan period in days.'],
            'reference_loan_period_days' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Reference material loan period in days.'],
            'loan_period_scarce_max_copies' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Copy count up to which an edition counts as scarce (ДИР §9.3).'],
            'loan_period_scarce_days' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Loan period in days for a scarce edition.'],
            'loan_period_standard_max_copies' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Copy count up to which the ordinary loan period applies.'],
            'loan_period_standard_days' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Ordinary loan period in days.'],
            'loan_period_abundant_days' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Loan period in days for an edition held in many copies.'],
            'renewal_allowed' => ['type' => 'boolean', 'group' => 'circulation', 'description' => 'Whether readers can renew eligible loans.'],
            'renewal_period_days' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Loan renewal period in days.'],
            'max_renewals' => ['type'=>'integer','group'=>'circulation','description'=>'Maximum renewals per loan.'],
            'manual_due_date_max_days' => ['type'=>'integer','group'=>'circulation','description'=>'Maximum manual due-date horizon.'],
            'inventory_batch_scan_limit' => ['type'=>'integer','group'=>'inventory','description'=>'Maximum scans per inventory session.'],
            'barcode_format' => ['type'=>'string','group'=>'barcodes','description'=>'Linear barcode format.'],
            'barcode_label_size' => ['type'=>'string','group'=>'barcodes','description'=>'Printable label size.'],
            'overdue_blocking_enabled' => ['type' => 'boolean', 'group' => 'circulation', 'description' => 'Whether overdue loans block new operations.'],
            'fine_per_overdue_day' => ['type' => 'integer', 'group' => 'circulation', 'description' => 'Fine per overdue day in KZT; 0 disables overdue fines.'],
            'incident_resolution_days' => ['type' => 'integer', 'group' => 'incidents', 'description' => 'Configurable incident resolution deadline.'],
            'replacement_year_tolerance' => ['type' => 'integer', 'group' => 'incidents', 'description' => 'Proposed configurable publication-year tolerance.'],
            'replacement_requires_senior_approval' => ['type' => 'boolean', 'group' => 'incidents', 'description' => 'Require senior librarian approval.'],
            'replacement_exception_requires_director' => ['type' => 'boolean', 'group' => 'incidents', 'description' => 'Require director approval for exceptions.'],
            'monetary_compensation_allowed' => ['type' => 'boolean', 'group' => 'incidents', 'description' => 'Allow authorized monetary compensation decisions.'],
            'incident_blocks_issues' => ['type' => 'boolean', 'group' => 'incidents', 'description' => 'Block new issues while a case is open.'],
            'replacement_required_severe' => ['type' => 'boolean', 'group' => 'incidents', 'description' => 'Suggest replacement for severe damage.'],
            'replacement_required_irreparable' => ['type' => 'boolean', 'group' => 'incidents', 'description' => 'Suggest replacement for irreparable damage.'],
            'incident_resolution_types' => ['type' => 'array', 'group' => 'incidents', 'description' => 'Enabled incident resolution types.'],
            'notification_channels' => ['type' => 'array', 'group' => 'notifications', 'description' => 'Enabled notification delivery channels.'],
            'news_categories' => ['type' => 'array', 'group' => 'content', 'description' => 'Managed news category dictionary.'],
            'message_categories' => ['type' => 'array', 'group' => 'content', 'description' => 'Managed contact message category dictionary.'],
            'default_ui_language' => ['type' => 'string', 'group' => 'localization', 'description' => 'Default interface language.'],
            'results_per_page' => ['type' => 'integer', 'group' => 'localization', 'description' => 'Default table page size.'],
            'catalog_page_size' => ['type' => 'integer', 'group' => 'localization', 'description' => 'Cards per page in the public catalogue.'],
            'data_quality_scan_chunk_size' => ['type' => 'integer', 'group' => 'data_quality', 'description' => 'Records processed per scanner chunk.'],
            'data_quality_bulk_batch_limit' => ['type' => 'integer', 'group' => 'data_quality', 'description' => 'Hard limit for one correction batch.'],
            'data_quality_duplicate_exact_threshold' => ['type' => 'decimal', 'group' => 'data_quality', 'description' => 'Advisory exact-duplicate score threshold.'],
            'data_quality_duplicate_probable_threshold' => ['type' => 'decimal', 'group' => 'data_quality', 'description' => 'Advisory probable-duplicate score threshold.'],
            'data_quality_min_publication_year' => ['type' => 'integer', 'group' => 'data_quality', 'description' => 'Proposed configurable lower publication-year boundary.'],
            'data_quality_max_future_years' => ['type' => 'integer', 'group' => 'data_quality', 'description' => 'Allowed future publication-year offset.'],
            'data_quality_rescan_days' => ['type' => 'integer', 'group' => 'data_quality', 'description' => 'Recommended interval between full scans.'],
            'data_quality_staging_retention_days' => ['type' => 'integer', 'group' => 'data_quality', 'description' => 'Staging data retention period.'],
            'data_quality_sla_critical_hours' => ['type' => 'integer', 'group' => 'data_quality', 'description' => 'Critical issue SLA in hours.'],
            'data_quality_sla_high_hours' => ['type' => 'integer', 'group' => 'data_quality', 'description' => 'High issue SLA in hours.'],
            'data_quality_sla_medium_hours' => ['type' => 'integer', 'group' => 'data_quality', 'description' => 'Medium issue SLA in hours.'],
            'data_quality_bulk_approval_required' => ['type' => 'boolean', 'group' => 'data_quality', 'description' => 'Require an independent approval for bulk correction.'],
            'data_quality_merge_approval_required' => ['type' => 'boolean', 'group' => 'data_quality', 'description' => 'Require an independent approval for record merge.'],
            'data_quality_import_encodings' => ['type' => 'array', 'group' => 'data_quality', 'description' => 'Explicitly allowed legacy import encodings.'],
        ];
    }

    /**
     * @return list<string>
     */
    private function listValue(string $value, string $field): array
    {
        $items = collect(preg_split('/[\\r\\n,]+/', $value) ?: [])
            ->map(fn (string $item): string => mb_strtolower(trim($item)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($items === [] || collect($items)->contains(
            fn (string $item): bool => mb_strlen($item) > 32 || preg_match('/^[\pL\pN][\pL\pN_-]*$/u', $item) !== 1
        )) {
            throw ValidationException::withMessages([
                $field => __('settings.content.category_format'),
            ]);
        }

        return $items;
    }

    private function sameValue(mixed $old, mixed $new): bool
    {
        return json_encode($old, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)
            === json_encode($new, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
