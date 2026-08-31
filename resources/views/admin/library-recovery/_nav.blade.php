<nav class="mb-7 flex flex-wrap gap-2" aria-label="{{ __('library_recovery.title') }}">
    @foreach (['index' => 'dashboard', 'raw_records' => 'raw', 'quarantine' => 'quarantine', 'conflicts' => 'conflicts'] as $key => $label)
        <a href="{{ $links[$key] }}"
           class="admin-btn {{ request()->url() === $links[$key] ? 'admin-btn-primary' : 'admin-btn-secondary' }}">
            {{ __('library_recovery.nav.'.$label) }}
        </a>
    @endforeach
    @if (($canManage ?? false) && filled($links['review'] ?? null))
        <a href="{{ $links['review'] }}" class="admin-btn admin-btn-secondary">
            <span class="material-symbols-outlined text-[18px]">fact_check</span>
            {{ __('library_recovery.nav.review') }}
        </a>
    @endif
</nav>
