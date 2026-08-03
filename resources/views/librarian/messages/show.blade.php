@extends('layouts.librarian')

@section('title', $message->subject.' — '.__('librarian.messages.title'))

@section('content')
    <x-admin.flash />

    @php
        $attachments = is_array($message->attachments) ? $message->attachments : [];
        $canDownloadAttachments = auth()->user()?->can('messages.view_all')
            && ! auth()->user()?->hasRole('bibliographer')
            && \Illuminate\Support\Facades\Route::has('admin.messages.attachments');
        $processStatuses = ['open', 'in_review', 'resolved'];
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.messages.eyebrow')"
        :title="$message->subject"
        :subtitle="__('librarian.messages.subtitle')"
    >
        <a href="{{ route('librarian.messages.index') }}" class="admin-btn admin-btn-secondary">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
    </x-admin.page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
        <div class="space-y-6">
            <section class="admin-card">
                <div class="mb-6 flex flex-col justify-between gap-4 border-b border-slate-100 pb-6 sm:flex-row sm:items-start">
                    <div class="min-w-0">
                        <p class="admin-label mb-1">{{ __('librarian.messages.sender') }}</p>
                        <p class="text-sm font-semibold text-primary">{{ $message->sender?->name ?? $message->sender_email ?? '—' }}</p>
                        @if ($message->sender_email)
                            <a class="mt-1 block break-all text-sm text-secondary hover:underline" href="mailto:{{ $message->sender_email }}">{{ $message->sender_email }}</a>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-admin.status-badge :status="$message->status" :label="__('messages.statuses.'.$message->status)" />
                        <x-admin.status-badge :status="$message->priority" :label="__('messages.priorities.'.$message->priority)" />
                    </div>
                </div>

                <dl class="mb-6 grid gap-4 rounded-xl bg-surface-container-low p-4 sm:grid-cols-3">
                    <div>
                        <dt class="admin-label">{{ __('messages.fields.category') }}</dt>
                        <dd class="text-sm font-semibold text-primary">
                            {{ \Illuminate\Support\Facades\Lang::has('messages.categories.'.$message->category)
                                ? __('messages.categories.'.$message->category)
                                : $message->category }}
                        </dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.messages.received') }}</dt>
                        <dd class="text-sm text-slate-600">
                            <time datetime="{{ $message->created_at?->toIso8601String() }}">
                                {{ $message->created_at?->format('d.m.Y H:i') ?? '—' }}
                            </time>
                        </dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('messages.fields.assigned_to') }}</dt>
                        <dd class="text-sm font-semibold text-primary">{{ $message->assignee?->name ?? __('messages.messages.unassigned') }}</dd>
                    </div>
                </dl>

                <h2 class="admin-label">{{ __('messages.fields.body') }}</h2>
                <div class="whitespace-pre-line break-words text-[15px] leading-7 text-slate-700">{{ $message->body }}</div>
            </section>

            <section class="admin-card">
                <h2 class="mb-4 font-headline text-2xl text-primary">{{ __('messages.fields.attachments') }}</h2>
                @if ($attachments !== [])
                    <ul class="grid gap-3 sm:grid-cols-2">
                        @foreach ($attachments as $index => $attachment)
                            @php
                                $attachmentName = is_array($attachment)
                                    ? ($attachment['name'] ?? basename((string) ($attachment['path'] ?? '')))
                                    : basename((string) $attachment);
                                $attachmentName = $attachmentName !== '' ? $attachmentName : '—';
                            @endphp
                            <li>
                                @if ($canDownloadAttachments)
                                    <a
                                        href="{{ route('admin.messages.attachments', [$message, $index]) }}"
                                        class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm font-semibold text-primary hover:border-secondary hover:text-secondary"
                                        title="{{ __('messages.actions.download_attachment') }}"
                                    >
                                        <span class="material-symbols-outlined text-[20px]">attach_file</span>
                                        <span class="min-w-0 flex-1 truncate">{{ $attachmentName }}</span>
                                        <span class="material-symbols-outlined text-[19px]">download</span>
                                    </a>
                                @else
                                    <span class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm font-semibold text-slate-600">
                                        <span class="material-symbols-outlined text-[20px] text-slate-400">attach_file</span>
                                        <span class="min-w-0 flex-1 truncate">{{ $attachmentName }}</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-slate-500">{{ __('messages.messages.no_attachments') }}</p>
                @endif
            </section>

            @if ($message->resolution_comment || $message->resolved_at)
                <section class="admin-card border-l-4 border-l-secondary">
                    <h2 class="mb-3 font-headline text-2xl text-primary">{{ __('librarian.messages.resolution_comment') }}</h2>
                    @if ($message->resolution_comment)
                        <p class="whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $message->resolution_comment }}</p>
                    @endif
                    @if ($message->resolved_at)
                        <p class="mt-4 text-xs font-semibold text-secondary">
                            {{ __('messages.fields.resolved_at') }}:
                            <time datetime="{{ $message->resolved_at->toIso8601String() }}">{{ $message->resolved_at->format('d.m.Y H:i') }}</time>
                        </p>
                    @endif
                    @if ($message->assignee)
                        <p class="mt-1 text-xs text-slate-500">
                            {{ __('messages.fields.assigned_to') }}: {{ $message->assignee->name }}
                        </p>
                    @endif
                </section>
            @endif
        </div>

        <aside>
            @can('messages.resolve')
                <form method="POST" action="{{ route('librarian.messages.update', $message) }}" class="admin-card xl:sticky xl:top-24">
                    @csrf
                    @method('PATCH')

                    <h2 class="mb-5 font-headline text-2xl text-primary">{{ __('librarian.messages.process') }}</h2>

                    <div class="space-y-5">
                        <div>
                            <label for="librarian-message-status" class="admin-label">{{ __('messages.fields.status') }}</label>
                            <select id="librarian-message-status" class="admin-input" name="status" required>
                                @foreach ($processStatuses as $status)
                                    <option value="{{ $status }}" @selected(old('status', $message->status) === $status)>{{ __('messages.statuses.'.$status) }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="librarian-message-resolution" class="admin-label">{{ __('librarian.messages.resolution_comment') }}</label>
                            <textarea
                                id="librarian-message-resolution"
                                class="admin-input min-h-36 resize-y"
                                name="resolution_comment"
                                maxlength="5000"
                            >{{ old('resolution_comment', $message->resolution_comment) }}</textarea>
                            <p id="librarian-message-resolution-hint" class="mt-2 hidden text-xs leading-5 text-slate-500">
                                {{ __('messages.messages.resolution_required') }}
                            </p>
                            @error('resolution_comment')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="admin-btn admin-btn-primary w-full">
                            <span class="material-symbols-outlined text-[19px]">save</span>
                            {{ __('common.actions.save_changes') }}
                        </button>
                    </div>
                </form>

                @push('scripts')
                    <script>
                      (function () {
                        const statusSelect = document.getElementById('librarian-message-status');
                        const comment = document.getElementById('librarian-message-resolution');
                        const hint = document.getElementById('librarian-message-resolution-hint');

                        if (!statusSelect || !comment) {
                          return;
                        }

                        const sync = () => {
                          const required = statusSelect.value === 'resolved';
                          comment.required = required;
                          comment.setAttribute('aria-required', required ? 'true' : 'false');
                          hint?.classList.toggle('hidden', !required);
                        };

                        statusSelect.addEventListener('change', sync);
                        sync();
                      })();
                    </script>
                @endpush
            @endcan
        </aside>
    </div>
@endsection
