@extends('layouts.admin')

@php
    $editing = $managedUser->exists;
    $selectedRole = old('role', $managedUser->getRoleNames()->first() ?: $managedUser->role);
    $selectedProvider = old('auth_provider', $managedUser->auth_provider ?: 'demo');
@endphp

@section('title', ($editing ? __('admin.users.edit') : __('admin.users.create')).' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :title="$editing ? __('admin.users.edit') : __('admin.users.create')"
        :subtitle="__('admin.users.manual_account_note')"
    >
        <a
            class="admin-btn admin-btn-secondary"
            href="{{ $editing ? route('admin.users.show', $managedUser) : route('admin.users.index') }}"
        >
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
    </x-admin.page-header>

    <form
        method="POST"
        action="{{ $editing ? route('admin.users.update', $managedUser) : route('admin.users.store') }}"
        class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <section class="admin-card">
            <div class="grid gap-5 md:grid-cols-2">
                <label>
                    <span class="admin-label">{{ __('admin.users.fields.full_name') }}</span>
                    <input
                        class="admin-input"
                        type="text"
                        name="name"
                        value="{{ old('name', $managedUser->name) }}"
                        maxlength="255"
                        autocomplete="name"
                        required
                    >
                </label>

                <label>
                    <span class="admin-label">{{ __('admin.users.fields.email') }}</span>
                    <input
                        class="admin-input"
                        type="email"
                        name="email"
                        value="{{ old('email', $managedUser->email) }}"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >
                </label>

                <label>
                    <span class="admin-label">{{ __('admin.users.fields.ad_login') }}</span>
                    <input
                        class="admin-input"
                        type="text"
                        name="ad_login"
                        value="{{ old('ad_login', $managedUser->ad_login) }}"
                        maxlength="255"
                        autocomplete="username"
                    >
                </label>

                <label>
                    <span class="admin-label">{{ __('admin.users.fields.department') }}</span>
                    <input
                        class="admin-input"
                        type="text"
                        name="department"
                        value="{{ old('department', $managedUser->department) }}"
                        maxlength="255"
                    >
                </label>

                <label>
                    <span class="admin-label">{{ __('admin.users.fields.auth_provider') }}</span>
                    <select id="auth-provider" class="admin-input" name="auth_provider" required>
                        @foreach (['demo', 'ldap'] as $provider)
                            <option value="{{ $provider }}" @selected($selectedProvider === $provider)>
                                {{ __('admin.users.providers.'.$provider) }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="admin-label">{{ __('admin.users.fields.external_id') }}</span>
                    <input
                        class="admin-input"
                        type="text"
                        name="external_id"
                        value="{{ old('external_id', $managedUser->external_id) }}"
                        maxlength="255"
                    >
                </label>

                <label>
                    <span class="admin-label">{{ __('admin.users.fields.role') }}</span>
                    <select class="admin-input" name="role" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected($selectedRole === $role->name)>
                                {{ \Illuminate\Support\Facades\Lang::has('roles.names.'.$role->name) ? __('roles.names.'.$role->name) : $role->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="admin-label">{{ __('admin.users.fields.locale') }}</span>
                    <select class="admin-input" name="locale" required>
                        @foreach (['ru', 'kk', 'en'] as $locale)
                            <option value="{{ $locale }}" @selected(old('locale', $managedUser->locale ?: 'kk') === $locale)>
                                {{ __('common.languages.'.$locale) }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="admin-card">
                <h2 class="mb-4 font-headline text-2xl text-primary">{{ __('admin.users.fields.password') }}</h2>
                <div class="space-y-5">
                    <label>
                        <span class="admin-label">{{ __('admin.users.fields.password') }}</span>
                        <input
                            id="user-password"
                            class="admin-input"
                            type="password"
                            name="password"
                            minlength="12"
                            autocomplete="new-password"
                        >
                    </label>
                    <label>
                        <span class="admin-label">{{ __('admin.users.fields.password_confirmation') }}</span>
                        <input
                            id="user-password-confirmation"
                            class="admin-input"
                            type="password"
                            name="password_confirmation"
                            minlength="12"
                            autocomplete="new-password"
                        >
                    </label>
                </div>
            </section>

            <section class="admin-card">
                <h2 class="mb-4 font-headline text-2xl text-primary">{{ __('common.fields.status') }}</h2>
                <input type="hidden" name="is_active" value="0">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
                    <input
                        id="account-active"
                        class="mt-0.5 rounded border-slate-300 text-secondary focus:ring-secondary"
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked((bool) old('is_active', $managedUser->is_active))
                    >
                    <span>
                        <strong class="block text-sm text-primary">{{ __('admin.users.fields.is_active') }}</strong>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">
                            {{ __('admin.users.statuses.active') }} / {{ __('admin.users.statuses.inactive') }}
                        </span>
                    </span>
                </label>

                @if ($editing)
                    <label class="mt-5 block">
                        <span class="admin-label">{{ __('common.fields.reason') }}</span>
                        <textarea
                            id="update-reason"
                            class="admin-input min-h-24 resize-y"
                            name="reason"
                            maxlength="1000"
                            placeholder="{{ __('common.validation.reason_required') }}"
                        >{{ old('reason') }}</textarea>
                    </label>
                @endif
            </section>

            <div class="flex flex-col gap-2 sm:flex-row xl:flex-col">
                <button class="admin-btn admin-btn-primary flex-1" type="submit">
                    <span class="material-symbols-outlined text-[19px]">save</span>
                    {{ $editing ? __('common.actions.save_changes') : __('common.actions.create') }}
                </button>
                <a
                    class="admin-btn admin-btn-secondary flex-1"
                    href="{{ $editing ? route('admin.users.show', $managedUser) : route('admin.users.index') }}"
                >
                    {{ __('common.actions.cancel') }}
                </a>
            </div>
        </aside>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const provider = document.getElementById('auth-provider');
            const password = document.getElementById('user-password');
            const confirmation = document.getElementById('user-password-confirmation');
            const active = document.getElementById('account-active');
            const reason = document.getElementById('update-reason');
            const editing = @json($editing);
            const initiallyActive = @json((bool) $managedUser->is_active);

            const synchronizePasswordRequirement = () => {
                const required = !editing && provider?.value === 'demo';
                if (password) password.required = required;
                if (confirmation) confirmation.required = required;
            };

            provider?.addEventListener('change', synchronizePasswordRequirement);
            synchronizePasswordRequirement();

            const synchronizeReasonRequirement = () => {
                if (!reason) return;
                reason.required = editing && initiallyActive && active?.checked === false;
                reason.minLength = reason.required ? 5 : 0;
            };

            active?.addEventListener('change', synchronizeReasonRequirement);
            synchronizeReasonRequirement();
        })();
    </script>
@endpush
