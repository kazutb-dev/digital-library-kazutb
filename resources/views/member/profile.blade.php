@extends('layouts.member', ['title' => __('librarian.member_portal.profile.title')])
@section('content')
@include('member.partials.flash')
<div class="mx-auto max-w-5xl space-y-8">
  <header><p class="text-xs font-bold uppercase tracking-[.2em] text-secondary">{{ __('librarian.member_portal.profile.eyebrow') }}</p><h1 class="mt-2 font-headline text-4xl text-primary">{{ __('librarian.member_portal.profile.title') }}</h1></header>
  <section class="grid gap-4 rounded-2xl bg-white p-6 md:grid-cols-3" aria-label="{{ __('librarian.member_portal.profile.identity') }}">
    <div><span class="text-xs text-slate-500">{{ __('librarian.member_portal.profile.name') }}</span><p class="font-semibold">{{ auth()->user()->name }}</p></div>
    <div><span class="text-xs text-slate-500">{{ __('librarian.member_portal.card.ticket') }}</span><p class="font-mono font-semibold">{{ $profile->ticket_number }}</p></div>
    <div><span class="text-xs text-slate-500">{{ __('librarian.member_portal.profile.category') }}</span><p>{{ __('librarian.member_portal.categories.'.$profile->category) }}</p></div>
    <div><span class="text-xs text-slate-500">{{ __('common.fields.email') }}</span><p>{{ auth()->user()->email }}</p></div>
    <div><span class="text-xs text-slate-500">{{ __('librarian.member.profile.faculty') }}</span><p>{{ $profile->faculty ?: '—' }}</p></div>
    <div><span class="text-xs text-slate-500">{{ __('librarian.member.profile.department') }}</span><p>{{ $profile->department ?: '—' }}</p></div>
    <div><span class="text-xs text-slate-500">{{ __('librarian.member.profile.group') }}</span><p>{{ $profile->study_group ?: '—' }}</p></div>
    <div><span class="text-xs text-slate-500">{{ __('librarian.member.card.status') }}</span><p>{{ __('librarian.circulation.reader_statuses.'.$profile->status) }}</p></div>
    <div><span class="text-xs text-slate-500">{{ __('librarian.member.profile.registered') }}</span><p>{{ ($profile->registered_at ?? $profile->created_at)?->format('d.m.Y') }}</p></div>
  </section>
  <form method="POST" action="{{ route('member.profile.update') }}" class="space-y-6 rounded-2xl bg-white p-6">@csrf @method('PATCH')
    <h2 class="font-headline text-2xl">{{ __('librarian.member.profile.editable') }}</h2>
    <div class="grid gap-5 md:grid-cols-2">
      <label class="text-sm">{{ __('librarian.member.profile.phone') }}<input class="mt-1 w-full rounded-lg border-slate-300" name="phone" value="{{ old('phone',$profile->phone) }}" autocomplete="tel"></label>
      <label class="text-sm">{{ __('librarian.member.profile.additional_email') }}<input class="mt-1 w-full rounded-lg border-slate-300" type="email" name="additional_email" value="{{ old('additional_email',$profile->additional_email) }}"></label>
      <label class="text-sm">{{ __('librarian.member.profile.language') }}<select class="mt-1 w-full rounded-lg border-slate-300" name="locale">@foreach(\App\Support\LocaleResolver::SUPPORTED as $code)<option value="{{ $code }}" @selected(auth()->user()->locale===$code)>{{ __('common.languages.'.$code) }}</option>@endforeach</select></label>
      <label class="text-sm">{{ __('librarian.member.profile.preferred_branch') }}<select class="mt-1 w-full rounded-lg border-slate-300" name="preferred_branch_id"><option value="">—</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)$profile->preferred_branch_id===(int)$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
    </div>
    <fieldset><legend class="font-semibold">{{ __('librarian.member.profile.notifications') }}</legend><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach(['email','reservations','news','messages','digital'] as $preference)<label><input type="hidden" name="notification_preferences[{{ $preference }}]" value="0"><input type="checkbox" name="notification_preferences[{{ $preference }}]" value="1" @checked(data_get($profile->notification_preferences,$preference,true))> {{ __('librarian.member.profile.preference_'.$preference) }}</label>@endforeach</div><p class="mt-2 text-xs text-slate-500">{{ __('librarian.member.profile.critical_notice') }}</p></fieldset>
    <fieldset><legend class="font-semibold">{{ __('librarian.member.profile.accessibility') }}</legend><div class="mt-3 flex flex-wrap gap-5">@foreach(['high_contrast','large_text'] as $preference)<label><input type="hidden" name="accessibility_preferences[{{ $preference }}]" value="0"><input type="checkbox" name="accessibility_preferences[{{ $preference }}]" value="1" @checked(data_get($profile->accessibility_preferences,$preference,false))> {{ __('librarian.member.profile.'.$preference) }}</label>@endforeach</div></fieldset>
    <button class="rounded-lg bg-primary px-6 py-3 text-white">{{ __('librarian.member.profile.save') }}</button>
  </form>
  <aside class="rounded-xl bg-amber-50 p-5 text-sm text-amber-900">{{ __('librarian.member.profile.protected_hint') }} <a class="font-semibold underline" href="{{ route('member.messages') }}">{{ __('librarian.member.nav.messages') }}</a>.</aside>
</div>
@endsection
