@props(['paginator'])
@if ($paginator->hasPages() || $paginator->total() > 0)
    <div class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-4 py-4 text-sm text-slate-600 sm:flex-row">
        <span>{{ __('common.pagination.showing', ['from' => $paginator->firstItem() ?? 0, 'to' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}</span>
        <div class="flex items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="admin-btn cursor-not-allowed border border-slate-200 px-3 py-2 opacity-50">{{ __('common.pagination.previous') }}</span>
            @else
                <a class="admin-btn border border-slate-200 px-3 py-2 hover:bg-slate-50" href="{{ $paginator->previousPageUrl() }}">{{ __('common.pagination.previous') }}</a>
            @endif
            <span class="px-2">{{ __('common.pagination.page', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
            @if ($paginator->hasMorePages())
                <a class="admin-btn border border-slate-200 px-3 py-2 hover:bg-slate-50" href="{{ $paginator->nextPageUrl() }}">{{ __('common.pagination.next') }}</a>
            @else
                <span class="admin-btn cursor-not-allowed border border-slate-200 px-3 py-2 opacity-50">{{ __('common.pagination.next') }}</span>
            @endif
        </div>
    </div>
@endif
