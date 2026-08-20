@extends('layouts.librarian')

@php
    $isEditing = $item->exists;
    $canEditMetadata = $isEditing
        ? (auth()->user()?->can('edit', $item) ?? false)
        : (auth()->user()?->can('create', \App\Models\Catalog\RepositoryItem::class) ?? false);
    $isStateLocked = in_array($item->status, ['published', 'embargoed', 'withdrawn', 'archived'], true);
    $isLocked = $isStateLocked || ! $canEditMetadata;

    $statusTone = static fn (?string $status): string => match ((string) $status) {
        'draft' => 'inactive',
        'metadata_review', 'author_verification', 'rights_review', 'quality_review', 'pending_approval' => 'pending',
        'rejected' => 'failed',
        'published' => 'published',
        'archived' => 'archived',
        default => 'approved',
    };

    $formatBytes = static function (?int $bytes): string {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1, ',', ' ').' '.$units[$power];
    };

    $authorsValue = old('authors', collect($item->authors ?? [])->filter()->implode("\n"));
    $keywordsValue = old('keywords', collect($item->keywords ?? [])->filter()->implode("\n"));
@endphp

@section('title', ($isEditing ? __('librarian.repository.edit') : __('librarian.repository.create')).' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <x-admin.page-header
        :eyebrow="__('librarian.repository.eyebrow')"
        :title="$isEditing ? __('librarian.repository.edit') : __('librarian.repository.create')"
        :subtitle="$isEditing ? $item->title : __('librarian.repository.subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.repository') }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
    </x-admin.page-header>

    @if ($isLocked)
        <div role="status" class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span class="material-symbols-outlined text-[20px]">lock</span>
            <span>{{ $isStateLocked ? __('librarian.repository.locked_after_publish') : __('librarian.repository.approval_read_only') }}</span>
        </div>
    @endif

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <form
            method="POST"
            action="{{ $isEditing ? route('librarian.repository.update', $item) : route('librarian.repository.store') }}"
            enctype="multipart/form-data"
            class="space-y-6"
        >
            @csrf
            @if ($isEditing)
                @method('PATCH')
            @endif

            <section class="admin-card">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="repository-title" class="admin-label">{{ __('librarian.repository.fields.title') }}</label>
                        <input
                            id="repository-title"
                            class="admin-input"
                            type="text"
                            name="title"
                            value="{{ old('title', $item->title) }}"
                            maxlength="1000"
                            required
                            @disabled($isLocked)
                        >
                        @error('title')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div><label class="admin-label" for="repository-source">{{ __('repository.fields.source') }}</label><input class="admin-input" id="repository-source" name="source" value="{{ old('source', $item->source) }}" @disabled($isLocked)></div>
                    <div><label class="admin-label" for="repository-rights-holder">{{ __('repository.fields.rights_holder') }}</label><input class="admin-input" id="repository-rights-holder" name="rights_holder" value="{{ old('rights_holder', $item->rights_holder) }}" @disabled($isLocked)></div>
                    <div><label class="admin-label" for="repository-copyright">{{ __('repository.fields.copyright_status') }}</label><select class="admin-input" id="repository-copyright" name="copyright_status" @disabled($isLocked)>@foreach(['unknown','public_domain','permission_granted','university_owned','licensed','restricted'] as $status)<option value="{{ $status }}" @selected(old('copyright_status', $item->copyright_status ?: 'unknown') === $status)>{{ __('repository.copyright.'.$status) }}</option>@endforeach</select></div>
                    <div><label class="admin-label" for="repository-licence">{{ __('repository.fields.licence_type') }}</label><input class="admin-input" id="repository-licence" name="licence_type" value="{{ old('licence_type', $item->licence_type) }}" @disabled($isLocked)></div>
                    <div><label class="admin-label" for="repository-permission-date">{{ __('repository.fields.permission_date') }}</label><input class="admin-input" id="repository-permission-date" type="date" name="permission_date" value="{{ old('permission_date', $item->permission_date?->format('Y-m-d')) }}" @disabled($isLocked)></div>
                    <div class="sm:col-span-2"><label class="admin-label" for="repository-licence-text">{{ __('repository.fields.licence_text') }}</label><textarea class="admin-input min-h-24" id="repository-licence-text" name="licence_text" maxlength="5000" @disabled($isLocked)>{{ old('licence_text', $item->licence_text) }}</textarea></div>
                    <div><label class="admin-label" for="repository-access">{{ __('repository.fields.access_policy') }}</label><select class="admin-input" id="repository-access" name="access_policy" @disabled($isLocked)>@foreach(\App\Models\Catalog\RepositoryItem::ACCESS_POLICIES as $policy)<option value="{{ $policy }}" @selected(old('access_policy', $item->access_policy ?: 'metadata_only') === $policy)>{{ __('repository.access.'.$policy) }}</option>@endforeach</select></div>
                    <div><label class="admin-label" for="repository-embargo">{{ __('repository.fields.embargo_until') }}</label><input class="admin-input" id="repository-embargo" type="date" name="embargo_until" value="{{ old('embargo_until', $item->embargo_until?->format('Y-m-d')) }}" @disabled($isLocked)></div>
                    <div><label class="admin-label" for="repository-post-embargo-access">{{ __('repository.fields.post_embargo_access_policy') }}</label><select class="admin-input" id="repository-post-embargo-access" name="post_embargo_access_policy" @disabled($isLocked)><option value="">—</option>@foreach(\App\Models\Catalog\RepositoryItem::POST_EMBARGO_ACCESS_POLICIES as $policy)<option value="{{ $policy }}" @selected(old('post_embargo_access_policy', $item->post_embargo_access_policy) === $policy)>{{ __('repository.access.'.$policy) }}</option>@endforeach</select><p class="mt-2 text-xs leading-5 text-slate-500">{{ __('repository.post_embargo_help') }}</p></div>

                    <div class="sm:col-span-2">
                        <label for="repository-authors" class="admin-label">{{ __('librarian.repository.fields.authors') }}</label>
                        <textarea
                            id="repository-authors"
                            class="admin-input min-h-24 resize-y leading-6"
                            name="authors"
                            maxlength="2000"
                            required
                            @disabled($isLocked)
                        >{{ $authorsValue }}</textarea>
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('librarian.repository.fields.authors_help') }}</p>
                        @error('authors')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div><label class="admin-label" for="repository-orcid">{{ __('repository.fields.primary_author_orcid') }}</label><input class="admin-input" id="repository-orcid" name="author_orcid" value="{{ old('author_orcid', $item->authorsList->first()?->orcid) }}" placeholder="0000-0000-0000-0000" @disabled($isLocked)></div>

                    <div>
                        <label for="repository-work-type" class="admin-label">{{ __('librarian.repository.fields.work_type') }}</label>
                        <select id="repository-work-type" class="admin-input" name="work_type" required @disabled($isLocked)>
                            @foreach (\App\Models\Catalog\RepositoryItem::WORK_TYPES as $typeKey)
                                <option value="{{ $typeKey }}" @selected(old('work_type', $item->work_type) === $typeKey)>{{ __('librarian.repository.work_types.'.$typeKey) }}</option>
                            @endforeach
                        </select>
                        @error('work_type')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="repository-year" class="admin-label">{{ __('librarian.repository.fields.year') }}</label>
                        <input
                            id="repository-year"
                            class="admin-input"
                            type="number"
                            name="year"
                            value="{{ old('year', $item->year) }}"
                            min="1950"
                            max="2100"
                            step="1"
                            inputmode="numeric"
                            @disabled($isLocked)
                        >
                        @error('year')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="repository-department" class="admin-label">{{ __('librarian.repository.fields.department') }}</label>
                        <input
                            id="repository-department"
                            class="admin-input"
                            type="text"
                            name="department"
                            value="{{ old('department', $item->department) }}"
                            maxlength="255"
                            @disabled($isLocked)
                        >
                        @error('department')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="repository-udc" class="admin-label">{{ __('librarian.repository.fields.udc_code') }}</label>
                        <input
                            id="repository-udc"
                            class="admin-input"
                            type="text"
                            name="udc_code"
                            value="{{ old('udc_code', $item->udc_code) }}"
                            maxlength="64"
                            @disabled($isLocked)
                        >
                        @error('udc_code')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="repository-language" class="admin-label">{{ __('librarian.repository.fields.language') }}</label>
                        <select id="repository-language" class="admin-input" name="language" required @disabled($isLocked)>
                            @foreach (['ru', 'kk', 'en'] as $languageKey)
                                <option value="{{ $languageKey }}" @selected(old('language', $item->language) === $languageKey)>{{ __('common.languages.'.$languageKey) }}</option>
                            @endforeach
                        </select>
                        @error('language')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="repository-abstract" class="admin-label">{{ __('librarian.repository.fields.abstract') }}</label>
                        <textarea
                            id="repository-abstract"
                            class="admin-input min-h-40 resize-y leading-6"
                            name="abstract"
                            maxlength="10000"
                            @disabled($isLocked)
                        >{{ old('abstract', $item->abstract) }}</textarea>
                        @error('abstract')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="repository-keywords" class="admin-label">{{ __('librarian.repository.fields.keywords') }}</label>
                        <textarea
                            id="repository-keywords"
                            class="admin-input min-h-24 resize-y leading-6"
                            name="keywords"
                            maxlength="2000"
                            @disabled($isLocked)
                        >{{ $keywordsValue }}</textarea>
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('librarian.catalog.fields.keywords_help') }}</p>
                        @error('keywords')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="admin-card">
                <h2 class="mb-5 font-headline text-2xl text-primary">{{ __('librarian.repository.file') }}</h2>

                <div class="mb-5 flex items-start gap-3 rounded-xl bg-surface-container-low px-4 py-3 text-sm">
                    @if ($isEditing && $item->file_name)
                        <span class="material-symbols-outlined text-[22px] text-secondary">picture_as_pdf</span>
                        <span>
                            <span class="block text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('librarian.repository.file_current') }}</span>
                            <strong class="mt-1 block break-all text-primary">{{ $item->file_name }}</strong>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $formatBytes($item->file_size) }}</span>
                            @can('readFull', $item)
                                <a class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-secondary hover:underline" href="{{ route('librarian.repository.file', $item) }}">
                                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                    {{ __('librarian.repository.open_file') }}
                                </a>
                            @endcan
                        </span>
                    @else
                        <span class="material-symbols-outlined text-[22px] text-slate-400">upload_file</span>
                        <span class="text-slate-500">{{ __('librarian.repository.no_file') }}</span>
                    @endif
                </div>

                <div>
                    <label for="repository-file" class="admin-label">{{ __('librarian.repository.file') }}</label>
                    <input
                        id="repository-file"
                        class="admin-input file:mr-3 file:rounded-md file:border-0 file:bg-primary-container file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                        type="file"
                        name="file"
                        accept=".pdf,application/pdf"
                        @disabled($isLocked)
                    >
                    @error('file')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                @if($isEditing && $item->file_path)
                    <div class="mt-4"><label class="admin-label" for="repository-version-reason">{{ __('repository.fields.version_reason') }}</label><textarea class="admin-input" id="repository-version-reason" name="version_reason" maxlength="2000"></textarea>@error('version_reason')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                @endif
            </section>

            @unless ($isLocked)
                <div class="flex flex-wrap justify-end gap-2">
                    <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.repository') }}">{{ __('common.actions.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <span class="material-symbols-outlined text-[19px]">save</span>
                        {{ $isEditing ? __('common.actions.save_changes') : __('common.actions.save') }}
                    </button>
                </div>
            @endunless
        </form>

        <aside class="space-y-6">
            @if ($isEditing)
                <section class="admin-card">
                    <h2 class="font-headline text-xl text-primary">{{ __('librarian.repository.workflow') }}</h2>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('librarian.repository.workflow_hint') }}</p>

                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('common.fields.status') }}</span>
                        <x-admin.status-badge
                            :status="$statusTone($item->status)"
                            :label="__('librarian.repository.statuses.'.$item->status)"
                        />
                    </div>

                    <dl class="mt-4 space-y-3 border-t border-slate-100 pt-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('librarian.repository.fields.uploaded_by') }}</dt>
                            <dd class="text-right font-semibold text-primary">{{ $item->uploadedBy?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('librarian.repository.fields.reviewed_by') }}</dt>
                            <dd class="text-right font-semibold text-primary">{{ $item->reviewedBy?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('librarian.repository.fields.approved_by') }}</dt>
                            <dd class="text-right font-semibold text-primary">{{ $item->approvedBy?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('librarian.repository.fields.published_at') }}</dt>
                            <dd class="text-right">{{ $item->published_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('common.fields.updated_at') }}</dt>
                            <dd class="text-right">{{ $item->updated_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if (trim((string) $item->review_notes) !== '')
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <span class="admin-label">{{ __('librarian.repository.review_notes') }}</span>
                            <p class="whitespace-pre-line rounded-xl bg-surface-container-low px-3 py-2.5 text-sm leading-6 text-slate-700">{{ $item->review_notes }}</p>
                        </div>
                    @endif
                    @if ($item->reviews->isNotEmpty())
                        <div class="mt-4 border-t border-slate-100 pt-4" data-section="repository-workflow-audit">
                            <span class="admin-label">{{ __('librarian.repository.workflow_history') }}</span>
                            <ol class="mt-2 space-y-2 text-xs leading-5 text-slate-600">
                                @foreach($item->reviews as $review)
                                    <li>
                                        <strong>{{ __('librarian.repository.statuses.'.$review->decision) }}</strong>
                                        · {{ $review->reviewer?->name ?? '—' }} · {{ $review->created_at?->format('d.m.Y H:i') }}
                                        @if(filled($review->comment))<span class="block whitespace-pre-line">{{ $review->comment }}</span>@endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                </section>

                @if ($item->versions->isNotEmpty())
                    <section class="admin-card" data-section="repository-version-history">
                        <h2 class="font-headline text-xl text-primary">{{ __('librarian.repository.version_history') }}</h2>
                        <ol class="mt-4 space-y-3">
                            @foreach ($item->versions as $version)
                                <li class="rounded-xl border border-slate-100 bg-surface-container-low px-3 py-3 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <strong class="text-primary">v{{ $version->version_number }} · {{ $version->file_name }}</strong>
                                        @if($version->is_active)<span class="text-xs font-bold uppercase text-teal-700">{{ __('librarian.repository.version_active') }}</span>@endif
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $formatBytes($version->file_size) }} · {{ $version->created_at?->format('d.m.Y H:i') }}</p>
                                    <p class="mt-2 text-xs leading-5 text-slate-600">{{ $version->change_reason }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if (in_array($item->status, ['published', 'embargoed'], true) && auth()->user()?->can('manageVersions', $item))
                    <form method="POST" action="{{ route('librarian.repository.revisions.store', $item) }}" enctype="multipart/form-data" class="admin-card space-y-4" data-section="repository-new-version">
                        @csrf
                        <h2 class="font-headline text-xl text-primary">{{ __('librarian.repository.new_version') }}</h2>
                        <p class="text-xs leading-5 text-slate-500">{{ __('librarian.repository.new_version_help') }}</p>
                        <div><label class="admin-label" for="repository-new-version-file">{{ __('librarian.repository.file') }}</label><input class="admin-input" id="repository-new-version-file" type="file" name="file" accept=".pdf,application/pdf" required></div>
                        <div><label class="admin-label" for="repository-new-version-reason">{{ __('repository.fields.version_reason') }}</label><textarea class="admin-input" id="repository-new-version-reason" name="version_reason" minlength="5" maxlength="2000" required></textarea></div>
                        <button class="admin-btn admin-btn-primary w-full" type="submit"><span class="material-symbols-outlined">upload_file</span>{{ __('librarian.repository.create_new_version') }}</button>
                    </form>
                @endif

                @php
                    $transitions = collect(\App\Services\Repository\RepositoryWorkflow::TRANSITIONS[$item->status] ?? [])->map(function (string $target) use ($item): array {
                        $allowed = match ($target) {
                            'approved' => auth()->user()?->can('approve', $item),
                            'scheduled', 'published', 'embargoed' => auth()->user()?->can('publish', $item),
                            'metadata_review', 'author_verification', 'quality_review', 'pending_approval', 'rejected' => auth()->user()?->can('reviewMetadata', $item),
                            'rights_review' => auth()->user()?->can('reviewRights', $item),
                            'changes_requested' => auth()->user()?->can('requestChanges', $item),
                            'withdrawn', 'archived' => auth()->user()?->can('withdraw', $item),
                            default => auth()->user()?->can('edit', $item),
                        };

                        return [
                            'action' => $target, 'icon' => in_array($target, ['rejected','withdrawn','archived'], true) ? 'block' : 'arrow_forward',
                            'class' => in_array($target, ['rejected','withdrawn','archived'], true) ? 'admin-btn-danger' : 'admin-btn-primary',
                            'allowed' => $allowed,
                            'required' => in_array($target, ['changes_requested','rejected','withdrawn'], true),
                        ];
                    })->filter(fn (array $transition): bool => (bool) $transition['allowed'])->values();
                @endphp

                @foreach ($transitions as $transition)
                    <form method="POST" action="{{ route('librarian.repository.transition', $item) }}" class="admin-card space-y-3">
                        @csrf
                        <input type="hidden" name="action" value="{{ $transition['action'] }}">

                        @if ($transition['action'] === 'scheduled')
                            <div>
                                <label for="repository-scheduled-for" class="admin-label">{{ __('repository.fields.scheduled_for') }}</label>
                                <input id="repository-scheduled-for" class="admin-input" type="datetime-local" name="scheduled_for" min="{{ now()->addMinute()->format('Y-m-d\\TH:i') }}" required>
                                @error('scheduled_for')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        <div>
                            <label for="repository-comment-{{ $transition['action'] }}" class="admin-label">{{ __('librarian.repository.comment') }}</label>
                            <textarea
                                id="repository-comment-{{ $transition['action'] }}"
                                class="admin-input min-h-20 resize-y leading-6"
                                name="comment"
                                maxlength="2000"
                                @required($transition['required'])
                            >{{ old('comment') }}</textarea>
                            @if ($transition['required'])
                                <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('librarian.repository.reject_needs_comment') }}</p>
                            @endif
                            @error('comment')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="admin-btn {{ $transition['class'] }} w-full">
                            <span class="material-symbols-outlined text-[19px]">{{ $transition['icon'] }}</span>
                            {{ __('librarian.repository.statuses.'.$transition['action']) }}
                        </button>
                    </form>
                @endforeach
            @endif
        </aside>
    </div>
@endsection
