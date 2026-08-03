@extends('layouts.admin')

@section('title', __('admin.profile.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('admin.profile.eyebrow')"
        :title="__('admin.profile.title')"
        :subtitle="__('admin.profile.subtitle')"
    />

    <div class="grid grid-cols-1 gap-7 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.profile.update') }}" class="admin-card">
            @csrf
            @method('PATCH')
            <h2 class="mb-5 font-headline text-2xl text-primary">{{ __('admin.profile.details_title') }}</h2>

            <label class="block">
                <span class="admin-label">{{ __('admin.users.fields.full_name') }}</span>
                <input class="admin-input" type="text" name="name" required maxlength="255" value="{{ old('name', $profileUser->name) }}">
            </label>
            @error('name')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror

            <label class="mt-4 block">
                <span class="admin-label">{{ __('admin.users.fields.email') }}</span>
                <input class="admin-input" type="email" name="email" required maxlength="255" value="{{ old('email', $profileUser->email) }}">
            </label>
            @error('email')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror

            <label class="mt-4 block">
                <span class="admin-label">{{ __('admin.users.fields.locale') }}</span>
                <select class="admin-input" name="locale" required>
                    @foreach (['ru', 'kk', 'en'] as $locale)
                        <option value="{{ $locale }}" @selected(old('locale', $profileUser->locale) === $locale)>{{ __('common.languages.'.$locale) }}</option>
                    @endforeach
                </select>
            </label>
            @error('locale')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror

            <button class="admin-btn admin-btn-primary mt-6" type="submit">
                <span class="material-symbols-outlined text-[19px]">save</span>{{ __('common.actions.save') }}
            </button>
        </form>

        <form method="POST" action="{{ route('admin.profile.password') }}" class="admin-card">
            @csrf
            @method('PATCH')
            <h2 class="mb-2 font-headline text-2xl text-primary">{{ __('admin.profile.password_title') }}</h2>
            <p class="mb-5 text-sm leading-6 text-slate-500">{{ __('admin.profile.password_hint') }}</p>

            <label class="block">
                <span class="admin-label">{{ __('admin.profile.current_password') }}</span>
                <input class="admin-input" type="password" name="current_password" required autocomplete="current-password">
            </label>
            @error('current_password')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror

            <label class="mt-4 block">
                <span class="admin-label">{{ __('admin.profile.new_password') }}</span>
                <input class="admin-input" type="password" name="password" required minlength="12" autocomplete="new-password">
            </label>
            @error('password')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror

            <label class="mt-4 block">
                <span class="admin-label">{{ __('admin.profile.confirm_password') }}</span>
                <input class="admin-input" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
            </label>

            <button class="admin-btn admin-btn-primary mt-6" type="submit">
                <span class="material-symbols-outlined text-[19px]">lock_reset</span>{{ __('admin.profile.change_password_action') }}
            </button>
        </form>
    </div>
@endsection
