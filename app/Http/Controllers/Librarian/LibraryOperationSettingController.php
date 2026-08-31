<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class LibraryOperationSettingController extends Controller
{
    /** @var array<string,array{type:string,group:string}> */
    private const DEFINITIONS = [
        'max_active_reservations' => ['type' => 'integer', 'group' => 'reservations'],
        'reservation_hold_days' => ['type' => 'integer', 'group' => 'reservations'],
        'max_active_loans' => ['type' => 'integer', 'group' => 'circulation'],
        'standard_loan_period_days' => ['type' => 'integer', 'group' => 'circulation'],
        'renewal_allowed' => ['type' => 'boolean', 'group' => 'circulation'],
        'renewal_period_days' => ['type' => 'integer', 'group' => 'circulation'],
        'max_renewals' => ['type' => 'integer', 'group' => 'circulation'],
        'fine_per_overdue_day' => ['type' => 'integer', 'group' => 'circulation'],
        'inventory_batch_scan_limit' => ['type' => 'integer', 'group' => 'inventory'],
        'inventory_numbering_enabled' => ['type' => 'boolean', 'group' => 'library_operations'],
        'inventory_number_prefix' => ['type' => 'string', 'group' => 'library_operations'],
        'barcode_generation_enabled' => ['type' => 'boolean', 'group' => 'library_operations'],
        'barcode_prefix' => ['type' => 'string', 'group' => 'library_operations'],
        'ksu_numbering_enabled' => ['type' => 'boolean', 'group' => 'library_operations'],
        'ksu_yearly_reset' => ['type' => 'boolean', 'group' => 'library_operations'],
        'default_service_point' => ['type' => 'string', 'group' => 'library_operations'],
        'default_sigla' => ['type' => 'string', 'group' => 'library_operations'],
    ];

    public function index(): View
    {
        return view('librarian.settings.library-operations', [
            'settings' => Setting::query()
                ->whereIn('key', array_keys(self::DEFINITIONS))
                ->get()
                ->keyBy('key'),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $values = $request->validate([
            'max_active_reservations' => ['required', 'integer', 'min:1', 'max:100'],
            'reservation_hold_days' => ['required', 'integer', 'min:1', 'max:30'],
            'max_active_loans' => ['required', 'integer', 'min:1', 'max:100'],
            'standard_loan_period_days' => ['required', 'integer', 'min:1', 'max:365'],
            'renewal_allowed' => ['required', 'boolean'],
            'renewal_period_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_renewals' => ['required', 'integer', 'min:0', 'max:20'],
            'fine_per_overdue_day' => ['required', 'integer', 'min:0', 'max:100000'],
            'inventory_batch_scan_limit' => ['required', 'integer', 'min:10', 'max:100000'],
            'inventory_numbering_enabled' => ['required', 'boolean'],
            'inventory_number_prefix' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9_-]+$/'],
            'barcode_generation_enabled' => ['required', 'boolean'],
            'barcode_prefix' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9_-]+$/'],
            'ksu_numbering_enabled' => ['required', 'boolean'],
            'ksu_yearly_reset' => ['required', 'accepted'],
            'default_service_point' => ['nullable', 'string', 'max:64'],
            'default_sigla' => ['nullable', 'string', 'max:64'],
        ]);

        foreach ([
            'renewal_allowed',
            'inventory_numbering_enabled',
            'barcode_generation_enabled',
            'ksu_numbering_enabled',
        ] as $boolean) {
            $values[$boolean] = $request->boolean($boolean);
        }
        $values['ksu_yearly_reset'] = true;
        foreach ([
            'max_active_reservations',
            'reservation_hold_days',
            'max_active_loans',
            'standard_loan_period_days',
            'renewal_period_days',
            'max_renewals',
            'fine_per_overdue_day',
            'inventory_batch_scan_limit',
        ] as $integer) {
            $values[$integer] = (int) $values[$integer];
        }
        foreach (['inventory_number_prefix', 'barcode_prefix', 'default_service_point', 'default_sigla'] as $text) {
            $values[$text] = trim((string) ($values[$text] ?? ''));
        }

        DB::transaction(function () use ($values, $audit, $request): void {
            foreach ($values as $key => $value) {
                $definition = self::DEFINITIONS[$key];
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
                    'description' => __('library_settings.descriptions.'.$key),
                ])->save();
                $audit->logRequired(
                    'update',
                    'setting',
                    $setting->getKey(),
                    oldValues: ['key' => $key, 'value' => $old],
                    newValues: ['key' => $key, 'value' => $value],
                    scope: 'system',
                    actor: $request->user(),
                );
            }
        });

        return back()->with('success', __('library_settings.saved'));
    }

    private function sameValue(mixed $old, mixed $new): bool
    {
        return json_encode($old, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)
            === json_encode($new, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
