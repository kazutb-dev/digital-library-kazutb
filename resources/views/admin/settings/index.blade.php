@extends('layouts.admin')

@php
    $value = static fn (string $key, mixed $default = null): mixed => old($key, $settings->get($key)?->value ?? $default);
    $channels = (array) $value('notification_channels', ['in_app', 'email']);
    $newsCategories = $value('news_categories', ['event', 'announcement', 'update', 'schedule']);
    $messageCategories = $value('message_categories', ['request', 'complaint', 'suggestion', 'question', 'other']);
@endphp

@section('title', __('settings.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('settings.title')" :subtitle="__('settings.subtitle')">
        <a href="{{ route('admin.integrations.index') }}" class="admin-btn admin-btn-secondary">
            <span class="material-symbols-outlined text-[19px]">hub</span>{{ __('admin.nav.integrations') }}
        </a>
    </x-admin.page-header>

    <div class="mb-6 flex items-start gap-3 rounded-xl border border-teal-100 bg-teal-50/60 p-4 text-sm text-teal-900">
        <span class="material-symbols-outlined text-[20px]">history</span>
        <span>{{ __('settings.audit_notice') }}</span>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-7">
        @csrf
        @method('PATCH')

        <div class="grid grid-cols-1 gap-7 xl:grid-cols-2">
            <section class="admin-card">
                <div class="mb-6 flex items-start gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-low text-secondary"><span class="material-symbols-outlined">swap_horiz</span></span>
                    <div>
                        <h2 class="font-headline text-3xl text-primary">{{ __('settings.circulation.title') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('settings.circulation.description') }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @foreach ([
                        'max_active_loans' => 5,
                        'standard_loan_period_days' => 14,
                        'reference_loan_period_days' => 1,
                        'renewal_period_days' => 14,
                    ] as $key => $default)
                        <div>
                            <label class="admin-label" for="setting-{{ $key }}">{{ __('settings.circulation.'.$key) }}</label>
                            <input class="admin-input" id="setting-{{ $key }}" type="number" min="1" max="365" name="{{ $key }}" required value="{{ $value($key, $default) }}">
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('settings.circulation.'.$key.'_help') }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="admin-label" for="setting-fine_per_overdue_day">{{ __('settings.circulation.fine_per_overdue_day') }}</label>
                        <input class="admin-input" id="setting-fine_per_overdue_day" type="number" min="0" max="100000" name="fine_per_overdue_day" required value="{{ $value('fine_per_overdue_day', 100) }}">
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('settings.circulation.fine_per_overdue_day_help') }}</p>
                    </div>
                </div>
                {{-- ДИР §9.3 — динамический срок выдачи по числу экземпляров.
                     Ступени и пороги настраиваются здесь, в коде их нет. --}}
                <div class="mt-7 border-t border-slate-100 pt-6">
                    <h3 class="font-headline text-2xl text-primary">{{ __('settings.circulation.loan_scale_title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('settings.circulation.loan_scale_description') }}</p>

                    <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                        @foreach ([
                            'loan_period_scarce_max_copies' => 2,
                            'loan_period_scarce_days' => 3,
                            'loan_period_standard_max_copies' => 5,
                            'loan_period_standard_days' => 5,
                            'loan_period_abundant_days' => 7,
                        ] as $key => $default)
                            <div>
                                <label class="admin-label" for="setting-{{ $key }}">{{ __('settings.circulation.'.$key) }}</label>
                                <input
                                    class="admin-input"
                                    id="setting-{{ $key }}"
                                    type="number"
                                    min="1"
                                    max="{{ str_contains($key, 'max_copies') ? 1000 : 365 }}"
                                    name="{{ $key }}"
                                    required
                                    value="{{ $value($key, $default) }}"
                                >
                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('settings.circulation.'.$key.'_help') }}</p>
                                @error($key)
                                    <p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    @php $scale = app(\App\Services\Catalog\LoanPeriodPolicy::class)->scaleRows(); @endphp
                    <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        <strong class="block">{{ __('settings.circulation.loan_scale_current') }}</strong>
                        @foreach ($scale as $row)
                            <span class="mt-1 block">
                                {{ __('settings.circulation.loan_scale_row', [
                                    'range' => $row['to'] === null ? $row['from'].'+' : $row['from'].'–'.$row['to'],
                                    'days' => $row['days'],
                                ]) }}
                            </span>
                        @endforeach
                        <span class="mt-2 block text-xs text-slate-500">{{ __('settings.circulation.loan_scale_note') }}</span>
                    </p>
                </div>

                <div class="mt-5 space-y-4">
                    @foreach (['renewal_allowed' => true, 'overdue_blocking_enabled' => true] as $key => $default)
                        <label class="flex items-start gap-3 rounded-xl border border-slate-100 p-4">
                            <input type="hidden" name="{{ $key }}" value="0">
                            <input class="mt-1 rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="{{ $key }}" value="1" @checked((bool) $value($key, $default))>
                            <span>
                                <strong class="block text-sm">{{ __('settings.circulation.'.$key) }}</strong>
                                <small class="mt-1 block leading-5 text-slate-500">{{ __('settings.circulation.'.$key.'_help') }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="admin-card">
                <div class="mb-6"><h2 class="font-headline text-3xl text-primary">{{ __('settings.incidents.title') }}</h2><p class="mt-1 text-sm text-slate-500">{{ __('settings.incidents.description') }}</p></div>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach(['incident_resolution_days'=>30,'replacement_year_tolerance'=>5] as $key=>$default)
                    <label><span class="admin-label">{{ __('settings.incidents.'.$key) }}</span><input class="admin-input" type="number" min="{{ $key==='replacement_year_tolerance' ? 0 : 1 }}" max="{{ $key==='replacement_year_tolerance' ? 50 : 365 }}" name="{{ $key }}" value="{{ $value($key,$default) }}" required><span class="mt-1 block text-xs text-slate-500">{{ __('settings.incidents.'.$key.'_help') }}</span></label>
                    @endforeach
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach(['replacement_requires_senior_approval'=>true,'replacement_exception_requires_director'=>true,'monetary_compensation_allowed'=>false,'incident_blocks_issues'=>true,'replacement_required_severe'=>true,'replacement_required_irreparable'=>true] as $key=>$default)
                    <label class="flex items-start gap-3 rounded-xl border p-4"><input type="hidden" name="{{ $key }}" value="0"><input class="mt-1 rounded border-slate-300 text-secondary" type="checkbox" name="{{ $key }}" value="1" @checked((bool)$value($key,$default))><span class="text-sm">{{ __('settings.incidents.'.$key) }}</span></label>
                    @endforeach
                </div>
                <fieldset class="mt-5"><legend class="admin-label">{{ __('settings.incidents.resolution_types') }}</legend><div class="flex flex-wrap gap-3">@foreach(\App\Models\Catalog\CirculationIncidentCase::RESOLUTIONS as $resolution)<label class="rounded-lg border px-3 py-2 text-sm"><input type="checkbox" name="incident_resolution_types[]" value="{{ $resolution }}" @checked(in_array($resolution,(array)$value('incident_resolution_types',['replacement','fine','fine_and_replacement','repair','write_off']),true))> {{ __('incidents.resolutions.'.$resolution) }}</label>@endforeach</div></fieldset>
            </section>

            <section class="admin-card xl:col-span-2">
                <div class="mb-6">
                    <h2 class="font-headline text-3xl text-primary">{{ __('settings.data_quality.title') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('settings.data_quality.description') }}</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        'data_quality_scan_chunk_size' => [500, 50, 5000],
                        'data_quality_bulk_batch_limit' => [1000, 1, 10000],
                        'data_quality_duplicate_exact_threshold' => [90, 70, 100],
                        'data_quality_duplicate_probable_threshold' => [65, 1, 99],
                        'data_quality_min_publication_year' => [1450, 1, 2000],
                        'data_quality_max_future_years' => [1, 0, 20],
                        'data_quality_rescan_days' => [7, 1, 365],
                        'data_quality_staging_retention_days' => [90, 1, 3650],
                        'data_quality_sla_critical_hours' => [24, 1, 8760],
                        'data_quality_sla_high_hours' => [72, 1, 8760],
                        'data_quality_sla_medium_hours' => [168, 1, 8760],
                    ] as $key => [$default, $min, $max])
                        <label>
                            <span class="admin-label">{{ __('settings.data_quality.'.$key) }}</span>
                            <input class="admin-input" type="number" name="{{ $key }}" min="{{ $min }}" max="{{ $max }}" value="{{ $value($key, $default) }}">
                        </label>
                    @endforeach
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach (['data_quality_bulk_approval_required' => true, 'data_quality_merge_approval_required' => true] as $key => $default)
                        <label class="flex items-start gap-3 rounded-xl border p-4">
                            <input type="hidden" name="{{ $key }}" value="0">
                            <input class="mt-1 rounded border-slate-300 text-secondary" type="checkbox" name="{{ $key }}" value="1" @checked((bool) $value($key, $default))>
                            <span class="text-sm">{{ __('settings.data_quality.'.$key) }}</span>
                        </label>
                    @endforeach
                </div>
                <fieldset class="mt-5">
                    <legend class="admin-label">{{ __('settings.data_quality.data_quality_import_encodings') }}</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach (['UTF-8', 'Windows-1251', 'ISO-8859-5'] as $encoding)
                            <label class="rounded-lg border px-3 py-2 text-sm">
                                <input type="checkbox" name="data_quality_import_encodings[]" value="{{ $encoding }}" @checked(in_array($encoding, (array) $value('data_quality_import_encodings', ['UTF-8', 'Windows-1251']), true))>
                                {{ $encoding }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ __('settings.data_quality.encodings_help') }}</p>
                </fieldset>
            </section>

            <section class="admin-card">
                <div class="mb-6 flex items-start gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-low text-secondary"><span class="material-symbols-outlined">event_available</span></span>
                    <div>
                        <h2 class="font-headline text-3xl text-primary">{{ __('settings.reservations.title') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('settings.reservations.description') }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @foreach (['max_active_reservations' => 3, 'reservation_lifespan_days' => 1, 'reservation_hold_days'=>1, 'reservation_max_extensions'=>1, 'reservation_extension_hours'=>24, 'reservation_expiry_reminder_hours'=>24] as $key => $default)
                        <div>
                            <label class="admin-label" for="setting-{{ $key }}">{{ __('settings.reservations.'.$key) }}</label>
                            <input class="admin-input" id="setting-{{ $key }}" type="number" min="1" max="365" name="{{ $key }}" required value="{{ $value($key, $default) }}">
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('settings.reservations.'.$key.'_help') }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach(['reservation_queue_enabled'=>true,'reservation_manual_confirmation_required'=>false,'reservation_interbranch_transfer_enabled'=>true,'reservation_queue_override_enabled'=>true,'reservation_blocking_on_fines'=>true] as $key=>$default)
                    <label class="flex items-start gap-3 rounded-xl border p-4"><input type="hidden" name="{{ $key }}" value="0"><input class="mt-1 rounded border-slate-300 text-secondary" type="checkbox" name="{{ $key }}" value="1" @checked((bool)$value($key,$default))><span class="text-sm">{{ __('settings.reservations.'.$key) }}</span></label>
                    @endforeach
                </div>

                <div class="mt-7 border-t border-slate-100 pt-6">
                    <h3 class="font-headline text-2xl text-primary">{{ __('settings.notifications.title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('settings.notifications.description') }}</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach (['in_app', 'email'] as $channel)
                            <label class="flex items-start gap-3 rounded-xl border border-slate-100 p-4">
                                <input class="mt-1 rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="notification_channels[]" value="{{ $channel }}" @checked(in_array($channel, $channels, true))>
                                <span>
                                    <strong class="block text-sm">{{ __('settings.notifications.'.$channel) }}</strong>
                                    <small class="mt-1 block leading-5 text-slate-500">{{ __('settings.notifications.'.$channel.'_help') }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        <section class="admin-card">
            <div class="mb-6">
                <h2 class="font-headline text-3xl text-primary">{{ __('settings.content.title') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('settings.content.description') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <label class="admin-label" for="setting-news-categories">{{ __('settings.content.news_categories') }}</label>
                    <textarea class="admin-input min-h-36" id="setting-news-categories" name="news_categories" required>{{ is_array($newsCategories) ? implode("\n", $newsCategories) : $newsCategories }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">{{ __('settings.content.news_categories_help') }}</p>
                </div>
                <div>
                    <label class="admin-label" for="setting-message-categories">{{ __('settings.content.message_categories') }}</label>
                    <textarea class="admin-input min-h-36" id="setting-message-categories" name="message_categories" required>{{ is_array($messageCategories) ? implode("\n", $messageCategories) : $messageCategories }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">{{ __('settings.content.message_categories_help') }}</p>
                </div>
            </div>
        </section>

        <section class="admin-card">
            <div class="mb-6">
                <h2 class="font-headline text-3xl text-primary">{{ __('settings.localization.title') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('settings.localization.description') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="admin-label" for="setting-language">{{ __('settings.localization.default_ui_language') }}</label>
                    <select class="admin-input" id="setting-language" name="default_ui_language" required>
                        @foreach (['ru', 'kk', 'en'] as $locale)
                            <option value="{{ $locale }}" @selected($value('default_ui_language', 'kk') === $locale)>{{ __('common.languages.'.$locale) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">{{ __('settings.localization.default_ui_language_help') }}</p>
                </div>
                <div>
                    <label class="admin-label" for="setting-per-page">{{ __('settings.localization.results_per_page') }}</label>
                    <input class="admin-input" id="setting-per-page" type="number" min="10" max="100" name="results_per_page" required value="{{ $value('results_per_page', 20) }}">
                    <p class="mt-1 text-xs text-slate-500">{{ __('settings.localization.results_per_page_help') }}</p>
                </div>
                <div>
                    <label class="admin-label" for="setting-catalog-page-size">{{ __('settings.localization.catalog_page_size') }}</label>
                    <input class="admin-input" id="setting-catalog-page-size" type="number" min="6" max="60" name="catalog_page_size" required value="{{ $value('catalog_page_size', 12) }}">
                    <p class="mt-1 text-xs text-slate-500">{{ __('settings.localization.catalog_page_size_help') }}</p>
                </div>
            </div>
        </section>

        <div class="sticky bottom-4 z-20 flex justify-end">
            <button class="admin-btn admin-btn-primary px-6 shadow-xl" type="submit">
                <span class="material-symbols-outlined text-[19px]">save</span>{{ __('settings.save') }}
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.settings.notifications') }}" class="admin-card mt-7 overflow-hidden p-0">
        @csrf
        @method('PATCH')
        <div class="border-b border-slate-100 px-6 py-5">
            <h2 class="font-headline text-3xl text-primary">{{ __('settings.notification_matrix.title') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('settings.notification_matrix.description') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>{{ __('settings.notification_matrix.event') }}</th>
                        <th class="text-center">{{ __('settings.notifications.in_app') }}</th>
                        <th class="text-center">{{ __('settings.notifications.email') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($notificationSettings as $notificationSetting)
                        <tr>
                            <td>
                                <strong class="block text-sm text-slate-800">{{ __('settings.notification_matrix.events.'.$notificationSetting->event_type) }}</strong>
                                <span class="mt-0.5 block font-mono text-xs text-slate-400">{{ $notificationSetting->event_type }}</span>
                            </td>
                            <td class="text-center">
                                <input
                                    class="h-4 w-4 rounded border-slate-300 text-secondary focus:ring-secondary"
                                    type="checkbox"
                                    name="events[{{ $notificationSetting->event_type }}][in_app]"
                                    value="1"
                                    aria-label="{{ __('settings.notifications.in_app') }} — {{ __('settings.notification_matrix.events.'.$notificationSetting->event_type) }}"
                                    @checked($notificationSetting->in_app_enabled)
                                >
                            </td>
                            <td class="text-center">
                                <input
                                    class="h-4 w-4 rounded border-slate-300 text-secondary focus:ring-secondary"
                                    type="checkbox"
                                    name="events[{{ $notificationSetting->event_type }}][email]"
                                    value="1"
                                    aria-label="{{ __('settings.notifications.email') }} — {{ __('settings.notification_matrix.events.'.$notificationSetting->event_type) }}"
                                    @checked($notificationSetting->email_enabled)
                                >
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between gap-4 border-t border-slate-100 px-6 py-4">
            <p class="text-xs leading-5 text-slate-500">{{ __('settings.notification_matrix.infrastructure_note') }}</p>
            <button class="admin-btn admin-btn-primary shrink-0" type="submit">
                <span class="material-symbols-outlined text-[19px]">save</span>{{ __('settings.save') }}
            </button>
        </div>
    </form>

    <section class="admin-card mt-7 !bg-primary text-white">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.14em] text-cyan-200">{{ __('common.status.read_only') }}</p>
                <h2 class="mt-2 font-headline text-3xl">{{ __('settings.security.title') }}</h2>
                <p class="mt-1 max-w-3xl text-sm text-white/65">{{ __('settings.security.description') }}</p>
            </div>
            <span class="material-symbols-outlined text-3xl text-cyan-200">shield_lock</span>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-white/8 p-4">
                <small class="text-white/55">{{ __('settings.security.demo_login_enabled') }}</small>
                <strong class="mt-2 block text-sm">{{ $demoLoginEnabled ? __('admin.security.enabled_in_environment') : __('admin.security.disabled_in_environment') }}</strong>
            </div>
            <div class="rounded-xl bg-white/8 p-4">
                <small class="text-white/55">{{ __('settings.security.rbac') }}</small>
                <strong class="mt-2 block text-sm">{{ $security['rbac_engine'] }}</strong>
            </div>
            <div class="rounded-xl bg-white/8 p-4">
                <small class="text-white/55">{{ __('settings.security.passwords') }}</small>
                <strong class="mt-2 block text-sm">{{ $security['password_hashing'] }}</strong>
            </div>
            <div class="rounded-xl bg-white/8 p-4">
                <small class="text-white/55">{{ __('settings.security.environment_value') }}</small>
                <strong class="mt-2 block text-sm">{{ $security['environment'] }} · {{ $security['session_driver'] }}</strong>
            </div>
        </div>
        <p class="mt-5 text-xs leading-5 text-white/65">{{ __('settings.security.demo_login_help') }}</p>
    </section>
@endsection
