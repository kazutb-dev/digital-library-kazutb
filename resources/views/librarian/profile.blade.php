@extends('layouts.librarian')

@section('title', __('librarian.staff_profile.title').' — '.__('common.app_name'))

@section('content')
  <x-admin.flash />

  <div class="mx-auto max-w-6xl space-y-7" data-staff-profile>
    <header class="max-w-3xl">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-secondary">{{ __('librarian.staff_profile.eyebrow') }}</p>
      <h1 class="mt-2 font-headline text-4xl leading-tight text-primary md:text-5xl">{{ __('librarian.staff_profile.title') }}</h1>
      <p class="mt-3 text-sm leading-6 text-slate-600 md:text-base">{{ __('librarian.staff_profile.subtitle') }}</p>
    </header>

    <section class="admin-card" aria-labelledby="staff-identity-title">
      <div class="flex flex-col gap-5 border-b border-slate-100 pb-6 sm:flex-row sm:items-center">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary-container text-2xl font-bold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($profileUser->name, 0, 1)) }}</div>
        <div class="min-w-0">
          <p class="text-xs font-bold uppercase tracking-[.15em] text-secondary">{{ __('librarian.staff_profile.identity') }}</p>
          <h2 id="staff-identity-title" class="mt-1 break-words font-headline text-3xl leading-tight text-primary">{{ $profileUser->name }}</h2>
          <p class="mt-1 font-semibold text-primary-container" data-profile-role>{{ __('brand.workspace.'.$profileRole) }}</p>
        </div>
        <span class="sm:ml-auto inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800">
          <span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ __('librarian.staff_profile.status_active') }}
        </span>
      </div>

      <dl class="mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('librarian.staff_profile.role') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ __('brand.workspace.'.$profileRole) }}</dd></div>
        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('librarian.staff_profile.account') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ __('librarian.staff_profile.corporate_account') }}</dd></div>
        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('librarian.staff_profile.login') }}</dt><dd class="mt-1 break-all text-sm font-semibold text-slate-900">{{ $profileUser->ad_samaccountname ?: $profileUser->ad_login }}</dd></div>
        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('librarian.staff_profile.email') }}</dt><dd class="mt-1 break-all text-sm text-slate-900">{{ $profileUser->email ?: '—' }}</dd></div>
        @if($profileUser->department)<div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('librarian.staff_profile.department') }}</dt><dd class="mt-1 text-sm text-slate-900">{{ $profileUser->department }}</dd></div>@endif
        @if($profileUser->job_title)<div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('librarian.staff_profile.position') }}</dt><dd class="mt-1 text-sm text-slate-900">{{ $profileUser->job_title }}</dd></div>@endif
        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('librarian.staff_profile.last_login') }}</dt><dd class="mt-1 text-sm text-slate-900">{{ $profileUser->last_login_at?->timezone(config('app.library_timezone', 'Asia/Almaty'))->format('d.m.Y H:i') ?: '—' }}</dd></div>
      </dl>

      <p class="mt-6 flex items-start gap-2 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">
        <span class="material-symbols-outlined mt-0.5 text-[19px] text-secondary" aria-hidden="true">verified_user</span>
        <span>{{ __('librarian.staff_profile.synced_hint') }}</span>
      </p>
    </section>

    <div class="grid gap-7 lg:grid-cols-5">
      <section class="admin-card lg:col-span-3" aria-labelledby="staff-directions-title">
        <h2 id="staff-directions-title" class="font-headline text-2xl text-primary">{{ __('librarian.staff_profile.work_directions') }}</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('roles.descriptions.'.$profileRole) }}</p>
        <div class="mt-5 flex flex-wrap gap-2">
          @foreach($workDirections as $direction)
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-semibold text-slate-700">{{ __('librarian.staff_profile.directions.'.$direction) }}</span>
          @endforeach
        </div>
      </section>

      <section class="admin-card lg:col-span-2" aria-labelledby="staff-settings-title">
        <h2 id="staff-settings-title" class="font-headline text-2xl text-primary">{{ __('librarian.staff_profile.settings') }}</h2>
        <form method="POST" action="{{ route('librarian.profile.preferences') }}" class="mt-5 space-y-4">
          @csrf @method('PATCH')
          <label for="staff-profile-locale" class="admin-label">{{ __('librarian.staff_profile.language') }}</label>
          <select id="staff-profile-locale" class="admin-input" name="locale">
            @foreach(\App\Support\LocaleResolver::SUPPORTED as $locale)
              <option value="{{ $locale }}" @selected($profileUser->locale === $locale)>{{ __('common.languages.'.$locale) }}</option>
            @endforeach
          </select>
          <button class="admin-btn admin-btn-primary w-full" type="submit">{{ __('librarian.staff_profile.save') }}</button>
        </form>
      </section>
    </div>
  </div>
@endsection
