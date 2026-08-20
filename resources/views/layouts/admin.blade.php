@php
    $pageLang = in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'ru';
    $eloquentAdmin = auth()->user();
    $legacyAdmin = is_array(session('library.user')) ? session('library.user') : [];
    $userName = trim((string) ($eloquentAdmin?->name ?? $legacyAdmin['name'] ?? __('roles.names.admin'))) ?: __('roles.names.admin');
    $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
    $adminNav = [
        ['label' => __('admin.nav.dashboard'), 'icon' => 'dashboard', 'href' => route('admin.overview'), 'active' => request()->routeIs('admin.overview'), 'permissions' => []],
        ['label' => __('admin.users.title'), 'icon' => 'group', 'href' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*'), 'permissions' => ['users.manage']],
        ['label' => __('roles.title'), 'icon' => 'shield_person', 'href' => route('admin.roles.index'), 'active' => request()->routeIs('admin.roles.*'), 'permissions' => ['roles.manage']],
        ['label' => __('admin.nav.audit_logs'), 'icon' => 'gavel', 'href' => route('admin.logs.index'), 'active' => request()->routeIs('admin.logs.*'), 'permissions' => ['system.logs']],
        ['label' => __('admin.nav.news'), 'icon' => 'campaign', 'href' => route('admin.news.index'), 'active' => request()->routeIs('admin.news.*'), 'permissions' => ['news.edit_any', 'news.edit_own']],
        ['label' => __('admin.nav.feedback'), 'icon' => 'inbox', 'href' => route('admin.messages.index'), 'active' => request()->routeIs('admin.messages.*', 'admin.feedback'), 'permissions' => ['messages.view_assigned']],
        ['label' => __('admin.nav.reports'), 'icon' => 'analytics', 'href' => route('admin.reports.index'), 'active' => request()->routeIs('admin.reports.*'), 'permissions' => ['reports.view_full']],
        ['label' => __('admin.nav.integrations'), 'icon' => 'hub', 'href' => route('admin.integrations.index'), 'active' => request()->routeIs('admin.integrations.*'), 'permissions' => ['integrations.view']],
        ['label' => match($pageLang) { 'ru' => 'Система и резервные копии', 'en' => 'System & backups', default => 'Жүйе және сақтық көшірмелер' }, 'icon' => 'monitor_heart', 'href' => route('admin.system.index'), 'active' => request()->routeIs('admin.system.*'), 'permissions' => ['system.settings']],
        ['label' => __('admin.nav.branches_funds'), 'icon' => 'account_balance', 'href' => route('admin.branches.index'), 'active' => request()->routeIs('admin.branches.*', 'admin.funds.*'), 'permissions' => ['branches.manage']],
        ['label' => __('admin.nav.external_resources'), 'icon' => 'language', 'href' => route('admin.external-resources.index'), 'active' => request()->routeIs('admin.external-resources.*'), 'permissions' => ['external_resources.manage']],
        ['label' => __('admin.nav.settings'), 'icon' => 'settings_suggest', 'href' => route('admin.settings.index'), 'active' => request()->routeIs('admin.settings.*'), 'permissions' => ['system.settings']],
    ];
    $adminNav = array_values(array_filter(
        $adminNav,
        static fn (array $item): bool => $item['permissions'] === [] || ($eloquentAdmin?->canAny($item['permissions']) ?? false),
    ));
@endphp
<!DOCTYPE html>
<html lang="{{ $pageLang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('brand.workspace.admin').' — '.__('brand.library.name'))</title>
    @include('partials.favicons')
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="stylesheet" href="/fonts/fonts.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        surface: '#f8f9fa',
                        'surface-low': '#f3f4f5',
                        'surface-high': '#e7e8e9',
                        'surface-highest': '#e1e3e4',
                        primary: '#000613',
                        'primary-container': '#001f3f',
                        secondary: '#006a6a',
                        'secondary-soft': '#d8f4f1',
                        outline: '#74777f',
                        danger: '#ba1a1a'
                    },
                    fontFamily: {
                        headline: ['Newsreader', 'serif'],
                        body: ['Manrope', 'sans-serif']
                    },
                    borderRadius: {
                        xl: '0.75rem',
                        '2xl': '1rem'
                    }
                }
            }
        };
    </script>
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .font-headline { font-family: 'Newsreader', serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        [x-cloak] { display: none !important; }
        .admin-input {
            width: 100%; border: 1px solid #d8dade; background: #fff; border-radius: .5rem;
            padding: .68rem .8rem; font-size: .875rem; color: #191c1d;
        }
        .admin-input:focus { border-color: #006a6a; box-shadow: 0 0 0 2px rgba(0,106,106,.12); outline: none; }
        .admin-label { display: block; margin-bottom: .4rem; color: #43474e; font-size: .72rem; font-weight: 700; letter-spacing: .055em; text-transform: uppercase; }
        .admin-card { border-radius: .75rem; background: #fff; padding: 1.5rem; box-shadow: 0 12px 35px rgba(0,6,19,.035); }
        .admin-btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; border-radius: .5rem; padding: .68rem 1rem; font-size: .875rem; font-weight: 700; transition: .2s ease; }
        .admin-btn-primary { color: #fff; background: #001f3f; }
        .admin-btn-primary:hover { background: #000613; }
        .admin-btn-secondary { color: #006a6a; background: #fff; border: 1px solid #d8dade; }
        .admin-btn-secondary:hover { background: #f3f4f5; }
        .admin-btn-danger { color: #93000a; background: #fff; border: 1px solid #ffc7c2; }
        .admin-btn-danger:hover { background: #fff0ee; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { padding: .85rem 1rem; text-align: left; background: #f3f4f5; color: #43474e; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
        .admin-table td { padding: 1rem; border-bottom: 1px solid #edeeef; vertical-align: top; font-size: .875rem; }
        .admin-table tbody tr:hover { background: rgba(243,244,245,.65); }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen bg-surface text-slate-900 antialiased">
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col bg-white px-3 py-5 md:flex">
        <div class="mb-7 px-2">
            <x-library-brand variant="sidebar" :href="route('admin.overview')" />
            <div class="mt-4 border-t border-slate-100 pt-3 pl-1" data-workspace-role>
                <div class="text-[10px] font-bold uppercase tracking-[.15em] text-secondary">{{ __('brand.workspace.system') }}</div>
                <div class="mt-1 text-sm font-bold text-primary-container">{{ __('brand.workspace.admin') }}</div>
            </div>
        </div>

        <nav aria-label="{{ __('admin.layout.main_navigation') }}" class="flex-1 space-y-1 overflow-y-auto">
            @foreach ($adminNav as $item)
                <a href="{{ $item['href'] }}" @class([
                    'flex items-center gap-3 rounded-lg px-4 py-3 text-sm transition',
                    'bg-primary text-white' => $item['active'],
                    'text-slate-600 hover:bg-slate-100 hover:text-primary' => ! $item['active'],
                ])>
                    <span class="material-symbols-outlined text-[21px]" @if($item['active']) style="font-variation-settings:'FILL' 1" @endif>{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="mt-5 border-t border-slate-100 pt-4">
            @can('news.create')
                <a href="{{ route('admin.news.create') }}" class="admin-btn admin-btn-primary mb-3 w-full">
                    <span class="material-symbols-outlined text-[19px]">add</span>{{ __('news.create') }}
                </a>
            @endcan
            <a href="/librarian" class="flex items-center gap-3 rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">
                <span class="material-symbols-outlined text-[20px]">open_in_new</span>{{ __('admin.nav.librarian_console') }}
            </a>
            <form method="POST" action="{{ url('/logout') }}">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-lg px-4 py-2 text-left text-sm text-slate-600 hover:bg-slate-100" type="submit">
                    <span class="material-symbols-outlined text-[20px]">logout</span>{{ __('admin.nav.logout') }}
                </button>
            </form>
        </div>
    </aside>

    <div class="min-h-screen md:ml-72">
        <header class="sticky top-0 z-30 border-b border-slate-100 bg-white/90 px-4 py-3 backdrop-blur-xl sm:px-8">
            <div class="mx-auto flex max-w-[1480px] items-center justify-between gap-4">
                <details class="relative md:hidden">
                    <summary class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg hover:bg-slate-100">
                        <span class="material-symbols-outlined">menu</span>
                    </summary>
                    <div class="absolute left-0 top-12 w-72 rounded-xl border border-slate-100 bg-white p-2 shadow-xl">
                        <div class="border-b border-slate-100 px-2 pb-3 pt-1">
                            <x-library-brand variant="sidebar" :href="route('admin.overview')" />
                            <div class="mt-3 text-xs font-bold text-primary-container">{{ __('brand.workspace.system') }} · {{ __('brand.workspace.admin') }}</div>
                        </div>
                        @foreach ($adminNav as $item)
                            <a href="{{ $item['href'] }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm', 'bg-primary text-white' => $item['active'], 'hover:bg-slate-100' => ! $item['active']])>
                                <span class="material-symbols-outlined text-[20px]">{{ $item['icon'] }}</span>{{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </details>

                <x-library-brand variant="compact" :href="route('admin.overview')" class="md:hidden" />

                <div class="hidden min-w-0 items-center gap-3 text-sm text-slate-500 sm:flex">
                    <span class="material-symbols-outlined text-[20px] text-secondary">verified_user</span>
                    <span class="truncate">{{ $userName }}</span>
                </div>

                <div class="relative hidden w-full max-w-md flex-1 sm:block" id="admin-global-search">
                    <form method="GET" action="{{ route('admin.search') }}" role="search">
                        <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                        <input
                            class="w-full rounded-lg border border-slate-200 bg-surface-low py-2 pl-10 pr-3 text-sm focus:border-secondary focus:outline-none focus:ring-2 focus:ring-secondary/20"
                            type="search"
                            name="q"
                            minlength="2"
                            autocomplete="off"
                            placeholder="{{ __('admin.search.placeholder') }}"
                            aria-label="{{ __('admin.search.title') }}"
                            data-search-input
                        >
                    </form>
                    <div
                        class="absolute left-0 right-0 top-12 z-40 hidden max-h-[70vh] overflow-y-auto rounded-xl border border-slate-100 bg-white p-2 shadow-xl"
                        data-search-results
                    ></div>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <x-locale-switcher variant="light" />
                    <a
                        href="{{ route('admin.profile.edit') }}"
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-container text-sm font-bold text-white transition hover:opacity-80"
                        title="{{ __('admin.profile.title') }}"
                        aria-label="{{ __('admin.profile.title') }}"
                    >{{ $userInitial }}</a>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1480px] px-4 py-8 sm:px-8 lg:px-12 lg:py-12">
            <x-admin.flash />
            @yield('content')
        </main>
    </div>

    <script>
        (function () {
            const root = document.getElementById('admin-global-search');
            if (!root) return;
            const input = root.querySelector('[data-search-input]');
            const results = root.querySelector('[data-search-results]');
            const endpoint = @json(route('admin.search'));
            const emptyLabel = @json(__('admin.search.no_results_short'));
            let timer = null;
            let controller = null;

            function hide() { results.classList.add('hidden'); }
            function show() { results.classList.remove('hidden'); }

            function render(data) {
                if (!data.groups.length) {
                    results.innerHTML = '<p class="px-3 py-4 text-center text-sm text-slate-500"></p>';
                    results.querySelector('p').textContent = emptyLabel;
                    show();
                    return;
                }
                results.innerHTML = '';
                data.groups.forEach(function (group) {
                    const heading = document.createElement('p');
                    heading.className = 'px-3 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wider text-slate-400';
                    heading.textContent = group.label;
                    results.appendChild(heading);
                    group.items.forEach(function (item) {
                        const link = document.createElement('a');
                        link.className = 'block rounded-lg px-3 py-2 hover:bg-slate-100';
                        link.href = item.url;
                        const title = document.createElement('span');
                        title.className = 'block truncate text-sm font-semibold text-slate-800';
                        title.textContent = item.title;
                        link.appendChild(title);
                        if (item.subtitle) {
                            const subtitle = document.createElement('span');
                            subtitle.className = 'block truncate text-xs text-slate-500';
                            subtitle.textContent = item.subtitle;
                            link.appendChild(subtitle);
                        }
                        results.appendChild(link);
                    });
                });
                show();
            }

            input.addEventListener('input', function () {
                clearTimeout(timer);
                const query = input.value.trim();
                if (query.length < 2) { hide(); return; }
                timer = setTimeout(function () {
                    if (controller) controller.abort();
                    controller = new AbortController();
                    fetch(endpoint + '?format=json&q=' + encodeURIComponent(query), {
                        headers: { 'Accept': 'application/json' },
                        signal: controller.signal,
                    })
                        .then(function (response) { return response.ok ? response.json() : null; })
                        .then(function (data) { if (data) render(data); })
                        .catch(function () {});
                }, 250);
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') hide();
            });
            document.addEventListener('click', function (event) {
                if (!root.contains(event.target)) hide();
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
