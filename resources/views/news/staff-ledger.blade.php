<section class="admin-card mt-6">
    <form class="mb-5 grid gap-3 md:grid-cols-[1fr_200px_200px_auto]" method="GET">
        <input class="admin-input" name="search" value="{{ request('search') }}" placeholder="{{ __('news.search_placeholder') }}">
        <select class="admin-input" name="status">
            <option value="">{{ __('news.filters.all_statuses') }}</option>
            @foreach(\App\Models\News::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('news.statuses.'.$status) }}</option>
            @endforeach
        </select>
        <select class="admin-input" name="type">
            <option value="">{{ __('news.filters.all_types') }}</option>
            @foreach(\App\Models\News::TYPES as $type)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ __('news.types.'.$type) }}</option>
            @endforeach
        </select>
        <button class="admin-btn admin-btn-secondary">{{ __('common.actions.filter') }}</button>
    </form>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead><tr class="border-b text-xs uppercase tracking-wider text-slate-500"><th class="p-3">{{ __('news.fields.title') }}</th><th class="p-3">{{ __('news.fields.type') }}</th><th class="p-3">{{ __('news.fields.status') }}</th><th class="p-3">{{ __('news.fields.created_by') }}</th><th class="p-3"></th></tr></thead>
        <tbody>@forelse($items as $item)<tr class="border-b"><td class="p-3"><strong class="text-primary">{{ $item->localized('title') }}</strong><p class="line-clamp-1 text-xs text-slate-500">{{ $item->localized('excerpt') }}</p></td><td class="p-3">{{ __('news.types.'.$item->type) }}</td><td class="p-3">{{ __('news.statuses.'.$item->status) }}</td><td class="p-3">{{ $item->creator?->name ?? '—' }}</td><td class="p-3 text-right"><a class="font-bold text-teal-700" href="{{ route($editRouteName, $item) }}">{{ __('common.actions.edit') }}</a></td></tr>@empty<tr><td class="p-8 text-center text-slate-500" colspan="5">{{ __('news.messages.empty') }}</td></tr>@endforelse</tbody>
    </table></div>
    <div class="mt-5">{{ $items->links() }}</div>
</section>
