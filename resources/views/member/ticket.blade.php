@extends('layouts.member')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
  <header><p class="text-sm font-bold uppercase tracking-[.2em] text-secondary">Kazakh University of Technology and Business named after K. Kulazhanov</p><h1 class="mt-2 font-headline text-4xl text-primary">{{ __('librarian.member_portal.card.title') }}</h1><p class="mt-2 text-slate-600">{{ __('librarian.member_portal.card.instructions') }}</p></header>
  <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm">
    <div class="flex flex-col gap-8 md:flex-row md:items-center">
      <div class="min-w-0 flex-1"><p class="text-sm text-slate-500">{{ __('librarian.member_portal.card.ticket') }}</p><p class="mt-1 font-mono text-2xl font-bold text-primary">{{ $profile->ticket_number }}</p><p class="mt-2 font-semibold text-primary">{{ auth()->user()->name }}</p><p class="text-sm text-slate-500">{{ __('librarian.member_portal.categories.'.$profile->category) }}</p><p class="mt-4 text-sm text-slate-500">{{ __('librarian.member_portal.card.status') }}</p><p class="font-semibold text-secondary">{{ __('librarian.circulation.reader_statuses.'.$profile->status) }}</p><div class="mt-7 [&_svg]:h-24 [&_svg]:w-full">{!! $code128Svg !!}</div><p class="mt-2 text-center font-mono text-sm">{{ $profile->barcode ?: $profile->ticket_number }}</p></div>
      <button type="button" class="mx-auto w-48 shrink-0 [&_svg]:h-48 [&_svg]:w-48" onclick="this.classList.toggle('scale-150')" aria-label="{{ __('librarian.member_portal.card.enlarge') }}">{!! $qrSvg !!}</button>
    </div>
    <p class="mt-5 text-xs text-slate-500">{{ __('librarian.member_portal.card.updated', ['time' => now()->format('d.m.Y H:i')]) }}</p>
    <form method="POST" action="{{ route('member.card.printed') }}" class="mt-4" onsubmit="window.print()">@csrf<button class="rounded-lg bg-primary px-5 py-2.5 text-white" type="submit">{{ __('librarian.member_portal.card.print') }}</button></form>
  </section>
</div>
@endsection
