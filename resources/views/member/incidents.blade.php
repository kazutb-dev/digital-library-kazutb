@extends('layouts.member')
@section('content')
<h1 class="font-headline text-4xl text-primary">{{ __('incidents.member.title') }}</h1>
<p class="mt-2 text-on-surface-variant">{{ __('incidents.member.subtitle') }}</p>
<div class="mt-8 space-y-4">@forelse($cases as $case)
<a href="{{ route('member.incidents.show',$case) }}" class="block rounded-xl bg-white p-6 shadow-sm transition hover:shadow-md"><div class="flex flex-wrap justify-between gap-4"><div><strong>{{ $case->case_number }}</strong><p class="mt-1">{{ $case->originalCopy?->bibliographicRecord?->title }}</p></div><div class="text-right"><span class="rounded-full bg-surface-container px-3 py-1 text-sm">{{ __('incidents.statuses.'.$case->status) }}</span><p class="mt-2 text-xs text-on-surface-variant">{{ $case->resolution_due_at?->format('d.m.Y') }}</p></div></div></a>
@empty<p class="rounded-xl bg-white p-8 text-on-surface-variant">{{ __('incidents.member.empty') }}</p>@endforelse</div>
<div class="mt-6">{{ $cases->links() }}</div>
@endsection
