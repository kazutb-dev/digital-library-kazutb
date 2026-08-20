@extends('layouts.librarian')
@section('title', __('news.calendar.title').' — '.__('brand.library.name'))
@section('content')
<div class="flex flex-wrap justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-teal-700">{{ __('news.calendar.eyebrow') }}</p><h1 class="font-headline text-4xl text-primary">{{ __('news.calendar.title') }}</h1></div><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.news.index') }}">{{ __('common.actions.back') }}</a></div>
<form class="admin-card mt-6 flex flex-wrap gap-3" method="GET">
    <input class="admin-input max-w-52" type="month" name="month" value="{{ $month->format('Y-m') }}">
    <select class="admin-input max-w-52" name="status"><option value="">{{ __('news.filters.all_statuses') }}</option>@foreach(\App\Models\News::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ __('news.statuses.'.$status) }}</option>@endforeach</select>
    <button class="admin-btn admin-btn-primary">{{ __('common.actions.filter') }}</button>
</form>
<div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach($planItems as $item)<article class="admin-card border-l-4 border-l-slate-400"><p class="text-xs font-bold uppercase">{{ __('news.plan.planned') }}</p><time>{{ $item->planned_date->format('d.m.Y') }}</time><h2 class="mt-2 font-bold text-primary">{{ $item->title_kk }}</h2><p class="text-sm">{{ __('news.plan.statuses.'.$item->status) }}</p></article>@endforeach
    @foreach($items as $item)<article class="admin-card border-l-4 {{ $item->status === 'published' ? 'border-l-teal-500' : 'border-l-amber-500' }}"><p class="text-xs font-bold uppercase">{{ __('news.types.'.$item->type) }}</p><time>{{ ($item->starts_at ?? $item->scheduled_publish_at)?->format('d.m.Y H:i') }}</time><h2 class="mt-2 font-bold text-primary">{{ $item->localized('title') }}</h2><p class="text-sm">{{ __('news.statuses.'.$item->status) }}</p></article>@endforeach
</div>
@endsection
