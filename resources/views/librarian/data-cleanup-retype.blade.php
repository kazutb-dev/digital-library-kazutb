@extends('layouts.librarian')

@section('title', __('librarian.data_cleanup.retype.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <div class="mx-auto w-full max-w-5xl">
        <x-admin.page-header
            :eyebrow="__('librarian.data_cleanup.eyebrow')"
            :title="__('librarian.data_cleanup.retype.title')"
            :subtitle="__('librarian.data_cleanup.retype.subtitle')"
        >
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-cleanup', ['issue' => 'glyph_substitution']) }}">
                <span class="material-symbols-outlined text-[19px]">arrow_back</span>
                {{ __('common.actions.back') }}
            </a>
        </x-admin.page-header>

        @if ($record === null)
            <section class="admin-card text-center">
                <span class="material-symbols-outlined mb-2 block text-5xl text-emerald-400">verified</span>
                <h2 class="font-headline text-2xl text-primary">{{ __('librarian.data_cleanup.retype.all_done') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('librarian.data_cleanup.retype.all_done_hint') }}</p>
            </section>
        @else
            <section class="admin-card">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <span class="text-sm font-semibold text-outline">
                        {{ __('librarian.data_cleanup.retype.position', ['position' => $position ?? 1, 'total' => $total]) }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-600">
                        <span class="material-symbols-outlined text-[16px]">tag</span>
                        #{{ $record->getKey() }}
                    </span>
                </div>

                {{-- The damaged strings, with every corrupted glyph marked so the
                     operator never has to hunt for it. Substitution candidates are
                     shown as a hint only — "Делµз" is Deleuze, where µ means ё, so
                     applying the usual µ—ө automatically would be wrong. --}}
                @foreach ([['label' => 'title', 'segments' => $titleSegments], ['label' => 'primary_author', 'segments' => $authorSegments]] as $field)
                    @if (collect($field['segments'])->contains('glyph', true))
                        <div class="mb-5">
                            <span class="admin-label">{{ __('librarian.catalog.fields.'.$field['label']) }} — {{ __('librarian.data_cleanup.retype.damaged') }}</span>
                            <p class="mt-1 rounded-xl border border-red-200 bg-red-50 px-4 py-3 font-mono text-base leading-8 text-slate-800">
                                @foreach ($field['segments'] as $segment)
                                    @if ($segment['glyph'])
                                        <span
                                            class="rounded bg-red-500 px-1 font-bold text-white"
                                            title="{{ $segment['text'] }} — {{ implode(' / ', $segment['options']) }}?"
                                        >{{ $segment['text'] }}</span><sup class="ml-0.5 text-[10px] font-bold text-red-700">{{ implode('/', $segment['options']) }}</sup>@else{{ $segment['text'] }}@endif
                                @endforeach
                            </p>
                        </div>
                    @endif
                @endforeach

                <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">
                    <div class="mb-1 flex items-center gap-1 font-bold">
                        <span class="material-symbols-outlined text-[18px]">lightbulb</span>
                        {{ __('librarian.data_cleanup.retype.legend_title') }}
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 font-mono">
                        @foreach ($substitutions as $glyph => $options)
                            <span><strong class="text-red-700">{{ $glyph }}</strong> — {{ implode(' / ', $options) }}</span>
                        @endforeach
                    </div>
                    <p class="mt-2 font-sans">{{ __('librarian.data_cleanup.retype.legend_warning') }}</p>
                </div>

                <form method="POST" action="{{ route('librarian.data-cleanup.retype.store', $record) }}" id="retype-form">
                    @csrf
                    <input type="hidden" name="next" value="{{ $nextId }}">

                    <label class="mb-4 block">
                        <span class="admin-label">{{ __('librarian.catalog.fields.title') }} *</span>
                        <input class="admin-input font-mono" type="text" name="title" id="retype-title" required maxlength="1000" value="{{ old('title', $record->title) }}" autofocus>
                        @error('title')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.data_cleanup.retype.preview') }}</span>
                        <output class="mt-1 block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" id="retype-title-preview"></output>
                    </label>

                    <label class="mb-6 block">
                        <span class="admin-label">{{ __('librarian.catalog.fields.primary_author') }}</span>
                        <input class="admin-input font-mono" type="text" name="primary_author" id="retype-author" maxlength="255" value="{{ old('primary_author', $record->primary_author) }}">
                        @error('primary_author')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        <output class="mt-1 block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" id="retype-author-preview"></output>
                    </label>

                    <div class="flex flex-wrap items-center gap-3">
                        <button class="admin-btn admin-btn-primary" type="submit">
                            <span class="material-symbols-outlined text-[19px]">save</span>
                            {{ $nextId ? __('librarian.data_cleanup.retype.save_next') : __('common.actions.save_changes') }}
                        </button>

                        @if ($previousId)
                            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-cleanup.retype', ['record' => $previousId]) }}">
                                <span class="material-symbols-outlined text-[19px]">arrow_back</span>
                                {{ __('librarian.data_cleanup.retype.previous') }}
                            </a>
                        @endif
                        @if ($nextId)
                            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-cleanup.retype', ['record' => $nextId]) }}">
                                {{ __('librarian.data_cleanup.retype.skip') }}
                                <span class="material-symbols-outlined text-[19px]">arrow_forward</span>
                            </a>
                        @endif

                        <a
                            class="ml-auto inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary hover:underline"
                            href="{{ '/book/'.rawurlencode($record->isbn ?: (string) $record->getKey()) }}"
                            target="_blank"
                            rel="noopener"
                        >
                            <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                            {{ __('librarian.data_cleanup.open_public') }}
                        </a>
                    </div>
                </form>

                <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                    <span class="material-symbols-outlined align-middle text-[16px]">keyboard</span>
                    {{ __('librarian.data_cleanup.retype.shortcuts') }}
                </p>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var title = document.getElementById('retype-title');
        var author = document.getElementById('retype-author');
        var form = document.getElementById('retype-form');

        function bindPreview(input, outputId) {
            var output = document.getElementById(outputId);
            if (!input || !output) {
                return;
            }
            var render = function () {
                output.textContent = input.value;
            };
            input.addEventListener('input', render);
            render();
        }

        bindPreview(title, 'retype-title-preview');
        bindPreview(author, 'retype-author-preview');

        // Keyboard flow for the monotonous pass. Ctrl/Cmd is required for the
        // arrows so plain arrow keys still move the caret inside the field.
        var prevUrl = @js($previousId ? route('librarian.data-cleanup.retype', ['record' => $previousId]) : null);
        var nextUrl = @js($nextId ? route('librarian.data-cleanup.retype', ['record' => $nextId]) : null);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey) && form) {
                event.preventDefault();
                form.submit();
                return;
            }
            if (event.key === 'ArrowRight' && (event.ctrlKey || event.metaKey) && nextUrl) {
                event.preventDefault();
                window.location.href = nextUrl;
                return;
            }
            if (event.key === 'ArrowLeft' && (event.ctrlKey || event.metaKey) && prevUrl) {
                event.preventDefault();
                window.location.href = prevUrl;
                return;
            }
            if (event.key === 'Escape' && nextUrl) {
                event.preventDefault();
                window.location.href = nextUrl;
            }
        });
    })();
</script>
@endpush
