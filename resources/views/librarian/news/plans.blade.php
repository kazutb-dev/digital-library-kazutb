@extends('layouts.librarian')
@section('title', __('news.plan.title').' — '.__('brand.library.name'))
@section('content')
<div class="flex justify-between gap-4"><div><h1 class="font-headline text-4xl text-primary">{{ __('news.plan.title') }}</h1><p class="mt-2 text-slate-600">{{ __('news.plan.help') }}</p></div><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.news.index') }}">{{ __('common.actions.back') }}</a></div>
<div class="mt-6 grid gap-6 lg:grid-cols-[360px_1fr]">
  <form class="admin-card space-y-4" method="POST" action="{{ route('librarian.news.plans.store') }}">@csrf<h2 class="text-xl font-bold">{{ __('news.plan.create') }}</h2><label class="admin-label">{{ __('news.plan.year') }}<input class="admin-input mt-1" type="number" name="year" min="2020" max="2100" required></label><label class="admin-label">{{ __('news.plan.name') }}<input class="admin-input mt-1" name="title" required></label><label class="admin-label">{{ __('news.plan.notes') }}<textarea class="admin-input mt-1" name="notes"></textarea></label><button class="admin-btn admin-btn-primary w-full">{{ __('common.actions.save') }}</button></form>
  <div class="space-y-3">@forelse($plans as $plan)<a class="admin-card flex items-center justify-between" href="{{ route('librarian.news.plans.show',$plan) }}"><span><strong class="text-xl text-primary">{{ $plan->year }} · {{ $plan->title }}</strong><small class="block text-slate-500">{{ __('news.plan.statuses.'.$plan->status) }}</small></span><span>{{ $plan->items_count }}</span></a>@empty<p class="admin-card text-slate-500">{{ __('news.plan.empty') }}</p>@endforelse</div>
</div>
@endsection
