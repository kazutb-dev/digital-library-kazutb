@extends('layouts.admin')

@section('title', $message->subject.' — '.__('messages.title'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('messages.details')"
        :title="$message->subject"
        :subtitle="__('messages.history.audit_source')"
    >
        <a href="{{ route('admin.messages.index') }}" class="admin-btn admin-btn-secondary">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
    </x-admin.page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-6">
            <section class="admin-card">
                <div class="mb-6 flex flex-col justify-between gap-4 border-b border-slate-100 pb-6 sm:flex-row sm:items-start">
                    <div>
                        <p class="text-sm font-semibold text-primary">{{ $message->sender?->name ?? $message->sender_email }}</p>
                        <a class="mt-1 block text-sm text-secondary hover:underline" href="mailto:{{ $message->sender_email }}">{{ $message->sender_email }}</a>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-admin.status-badge :status="$message->status" :label="__('messages.statuses.'.$message->status)" />
                        <x-admin.status-badge :status="$message->priority" :label="__('messages.priorities.'.$message->priority)" />
                    </div>
                </div>

                <dl class="mb-6 grid gap-4 rounded-xl bg-surface-low p-4 sm:grid-cols-3">
                    <div>
                        <dt class="admin-label">{{ __('messages.fields.category') }}</dt>
                        <dd class="text-sm font-semibold">{{ \Illuminate\Support\Facades\Lang::has('messages.categories.'.$message->category) ? __('messages.categories.'.$message->category) : $message->category }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('messages.fields.received_at') }}</dt>
                        <dd class="text-sm">
                            <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('d.m.Y H:i') }}</time>
                        </dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('messages.fields.assigned_to') }}</dt>
                        <dd class="text-sm font-semibold">{{ $message->assignee?->name ?? __('messages.messages.unassigned') }}</dd>
                    </div>
                </dl>

                <h2 class="admin-label">{{ __('messages.fields.body') }}</h2>
                <div class="whitespace-pre-line break-words text-[15px] leading-7 text-slate-700">{{ $message->body }}</div>
            </section>

            <section class="admin-card">
                <h2 class="mb-4 font-headline text-2xl text-primary">{{ __('messages.fields.attachments') }}</h2>
                @if (filled($message->attachments))
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($message->attachments as $index => $attachment)
                            @php
                                $attachmentName = is_array($attachment)
                                    ? ($attachment['name'] ?? basename((string) ($attachment['path'] ?? '')))
                                    : basename((string) $attachment);
                            @endphp
                            <a href="{{ route('admin.messages.attachments', [$message, $index]) }}" class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm font-semibold text-primary hover:border-secondary hover:text-secondary">
                                <span class="material-symbols-outlined">attach_file</span>
                                <span class="min-w-0 flex-1 truncate">{{ $attachmentName }}</span>
                                <span class="material-symbols-outlined text-[19px]">download</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">{{ __('messages.messages.no_attachments') }}</p>
                @endif
            </section>

            @if ($message->resolution_comment || $message->resolved_at)
                <section class="admin-card border-l-4 border-l-secondary">
                    <h2 class="mb-3 font-headline text-2xl text-primary">{{ __('messages.fields.resolution_comment') }}</h2>
                    @if ($message->resolution_comment)
                        <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ $message->resolution_comment }}</p>
                    @endif
                    @if ($message->resolved_at)
                        <p class="mt-4 text-xs font-semibold text-secondary">
                            {{ __('messages.fields.resolved_at') }}:
                            <time datetime="{{ $message->resolved_at->toIso8601String() }}">{{ $message->resolved_at->format('d.m.Y H:i') }}</time>
                        </p>
                    @endif
                </section>
            @endif

            <section class="admin-card">
                <div class="mb-5">
                    <h2 class="font-headline text-2xl text-primary">{{ __('messages.history.title') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('messages.history.audit_source') }}</p>
                </div>

                <div class="space-y-4">
                    @forelse ($history as $event)
                        @php
                            $actionTranslationKey = match ($event->action_type) {
                                'status.update' => 'admin.audit.actions.status_change',
                                'create' => 'admin.audit.actions.create',
                                'update' => 'admin.audit.actions.update',
                                'receive' => 'admin.audit.actions.receive',
                                'resolve' => 'admin.audit.actions.resolve',
                                'delete' => 'admin.audit.actions.delete',
                                default => null,
                            };
                            $before = $event->old_values ?? [];
                            $after = $event->new_values ?? [];
                            $changedFields = array_unique(array_merge(array_keys($before), array_keys($after)));
                            $historyFields = ['status', 'priority', 'assigned_to', 'resolution_comment', 'resolved_at'];
                        @endphp
                        <article class="relative border-l-2 border-slate-200 pl-5">
                            <span class="absolute -left-[7px] top-1 h-3 w-3 rounded-full border-2 border-white bg-secondary"></span>
                            <div class="flex flex-col justify-between gap-1 sm:flex-row">
                                <strong class="text-sm text-primary">{{ $actionTranslationKey ? __($actionTranslationKey) : $event->action_type }}</strong>
                                <time class="text-xs text-slate-500" datetime="{{ $event->occurred_at?->toIso8601String() }}">
                                    {{ $event->occurred_at?->utc()->format('d.m.Y H:i') }} {{ __('common.time.utc') }}
                                </time>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ $event->actor_name ?? __('common.time.not_available') }}</p>

                            @if (count(array_intersect($changedFields, $historyFields)) > 0)
                                <div class="mt-3 overflow-hidden rounded-lg border border-slate-100">
                                    @foreach ($historyFields as $field)
                                        @if (array_key_exists($field, $before) || array_key_exists($field, $after))
                                            @php
                                                $oldValue = $before[$field] ?? null;
                                                $newValue = $after[$field] ?? null;
                                                if ($field === 'status') {
                                                    $oldValue = $oldValue ? __('messages.statuses.'.$oldValue) : __('common.fields.none');
                                                    $newValue = $newValue ? __('messages.statuses.'.$newValue) : __('common.fields.none');
                                                } elseif ($field === 'priority') {
                                                    $oldValue = $oldValue ? __('messages.priorities.'.$oldValue) : __('common.fields.none');
                                                    $newValue = $newValue ? __('messages.priorities.'.$newValue) : __('common.fields.none');
                                                } elseif ($field === 'assigned_to') {
                                                    $oldValue = $oldValue ? '#'.$oldValue : __('messages.messages.unassigned');
                                                    $newValue = $newValue ? '#'.$newValue : __('messages.messages.unassigned');
                                                } else {
                                                    $oldValue = filled($oldValue) ? $oldValue : __('common.fields.none');
                                                    $newValue = filled($newValue) ? $newValue : __('common.fields.none');
                                                }
                                            @endphp
                                            <div class="grid gap-2 border-b border-slate-100 px-3 py-2 text-xs last:border-b-0 sm:grid-cols-[150px_1fr_1fr]">
                                                <span class="font-semibold text-slate-600">{{ __('messages.fields.'.$field) }}</span>
                                                <span class="break-words text-slate-500">
                                                    <span class="block text-[10px] font-bold uppercase tracking-wide">{{ __('admin.audit.diff.before') }}</span>
                                                    {{ $oldValue }}
                                                </span>
                                                <span class="break-words text-primary">
                                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-secondary">{{ __('admin.audit.diff.after') }}</span>
                                                    {{ $newValue }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="rounded-xl bg-surface-low p-4 text-sm text-slate-500">{{ __('messages.history.no_events') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            @can('messages.resolve')
            <form method="POST" action="{{ route('admin.messages.update', $message) }}" class="admin-card xl:sticky xl:top-24">
                @csrf
                @method('PATCH')

                <h2 class="mb-5 font-headline text-2xl text-primary">{{ __('common.actions.manage') }}</h2>

                <div class="space-y-5">
                    <div>
                        <label for="message-status" class="admin-label">{{ __('messages.fields.status') }}</label>
                        <select id="message-status" class="admin-input" name="status" required>
                            @foreach (\App\Models\ContactMessage::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $message->status) === $status)>{{ __('messages.statuses.'.$status) }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="message-priority" class="admin-label">{{ __('messages.fields.priority') }}</label>
                        <select id="message-priority" class="admin-input" name="priority" required>
                            @foreach (\App\Models\ContactMessage::PRIORITIES as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', $message->priority) === $priority)>{{ __('messages.priorities.'.$priority) }}</option>
                            @endforeach
                        </select>
                        @error('priority')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="message-assignee" class="admin-label">{{ __('messages.fields.assigned_to') }}</label>
                        <select id="message-assignee" class="admin-input" name="assigned_to">
                            <option value="">{{ __('messages.messages.unassigned') }}</option>
                            @foreach ($staff as $staffMember)
                                <option value="{{ $staffMember->id }}" @selected((string) old('assigned_to', $message->assigned_to) === (string) $staffMember->id)>{{ $staffMember->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="message-resolution" class="admin-label">{{ __('messages.fields.resolution_comment') }}</label>
                        <textarea
                            id="message-resolution"
                            class="admin-input min-h-36 resize-y"
                            name="resolution_comment"
                            maxlength="5000"
                        >{{ old('resolution_comment', $message->resolution_comment) }}</textarea>
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('messages.messages.resolution_required') }}</p>
                        @error('resolution_comment')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="admin-btn admin-btn-primary w-full">
                        <span class="material-symbols-outlined text-[19px]">save</span>
                        {{ __('common.actions.save_changes') }}
                    </button>
                </div>
            </form>
            @endcan

            @can('messages.delete')
            <details class="admin-card border border-red-100">
                <summary class="flex cursor-pointer items-center justify-between gap-4 font-semibold text-red-800">
                    <span class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">delete</span>
                        {{ __('messages.actions.delete') }}
                    </span>
                    <span class="material-symbols-outlined">expand_more</span>
                </summary>
                <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" class="mt-5">
                    @csrf
                    @method('DELETE')
                    <p class="mb-4 text-sm leading-6 text-slate-600">{{ __('messages.messages.delete_confirm', ['subject' => $message->subject]) }}</p>
                    <label for="message-delete-reason" class="admin-label">{{ __('common.fields.reason') }}</label>
                    <textarea id="message-delete-reason" class="admin-input min-h-24" name="reason" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    <button type="submit" class="admin-btn admin-btn-danger mt-4">
                        <span class="material-symbols-outlined text-[19px]">delete_forever</span>
                        {{ __('common.actions.delete') }}
                    </button>
                </form>
            </details>
            @endcan
        </aside>
    </div>
@endsection
