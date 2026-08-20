@extends('layouts.librarian')

@section('title', __('external_resources.workflow.queue_title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />
    <x-admin.page-header
        :title="__('external_resources.workflow.queue_title')"
        :subtitle="__('external_resources.workflow.queue_subtitle')"
    />

    <div class="grid gap-5 lg:grid-cols-2">
        @forelse ($resources as $resource)
            @php($issues = $resource->publicationReadinessIssues())
            <article class="admin-card flex flex-col">
                <div class="flex flex-wrap items-center gap-2">
                    <x-admin.status-badge
                        :status="$resource->publication_status === 'published' ? ($resource->is_active ? 'active' : 'inactive') : $resource->publication_status"
                        :label="__('digital.external.publication_statuses.'.$resource->publication_status)"
                    />
                    <span class="rounded-full bg-surface-low px-3 py-1 text-xs font-bold text-slate-600">{{ __('external_resources.types.'.$resource->resource_type) }}</span>
                </div>
                <h2 class="mt-4 font-headline text-2xl text-primary">{{ $resource->title }}</h2>
                <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">{{ $resource->description }}</p>

                @if ($issues !== [])
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <strong>{{ __('external_resources.workflow.not_ready') }}</strong>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($issues as $issue)
                                <li>{{ __('external_resources.readiness.'.$issue) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-surface-low p-4 text-sm">
                    <div><dt class="text-xs text-slate-500">{{ __('external_resources.expiry.label') }}</dt><dd class="mt-1 font-semibold">{{ $resource->effectiveExpiryDate()?->format('d.m.Y') ?? __('external_resources.expiry.not_specified') }}</dd></div>
                    <div><dt class="text-xs text-slate-500">{{ __('external_resources.labels.audiences') }}</dt><dd class="mt-1 font-semibold">{{ collect($resource->normalisedAudiences())->map(fn ($audience) => __('external_resources.audiences.'.$audience))->join(', ') }}</dd></div>
                </dl>

                <div class="mt-auto flex flex-wrap gap-2 pt-5">
                    @if ($resource->publication_status === 'review')
                        <form method="POST" action="{{ route('librarian.external-resources.workflow', $resource) }}">@csrf<input type="hidden" name="action" value="publish"><button class="admin-btn admin-btn-primary" type="submit" @disabled($issues !== [])>{{ __('external_resources.workflow.actions.publish') }}</button></form>
                        <form method="POST" action="{{ route('librarian.external-resources.workflow', $resource) }}" class="flex gap-2">@csrf<input type="hidden" name="action" value="return_to_draft"><input class="admin-input" name="reason" required minlength="5" placeholder="{{ __('external_resources.workflow.reason') }}"><button class="admin-btn admin-btn-secondary" type="submit">{{ __('external_resources.workflow.actions.return_to_draft') }}</button></form>
                    @elseif ($resource->publication_status === 'published' && $resource->is_active)
                        <form method="POST" action="{{ route('librarian.external-resources.workflow', $resource) }}" class="flex gap-2">@csrf<input type="hidden" name="action" value="suspend"><input class="admin-input" name="reason" required minlength="5" placeholder="{{ __('external_resources.workflow.reason') }}"><button class="admin-btn admin-btn-secondary" type="submit">{{ __('external_resources.workflow.actions.suspend') }}</button></form>
                    @elseif ($resource->publication_status === 'published')
                        <form method="POST" action="{{ route('librarian.external-resources.workflow', $resource) }}">@csrf<input type="hidden" name="action" value="resume"><button class="admin-btn admin-btn-primary" type="submit" @disabled($issues !== [])>{{ __('external_resources.workflow.actions.resume') }}</button></form>
                    @elseif ($resource->publication_status === 'archived')
                        <form method="POST" action="{{ route('librarian.external-resources.workflow', $resource) }}" class="flex gap-2">@csrf<input type="hidden" name="action" value="return_to_draft"><input class="admin-input" name="reason" required minlength="5" placeholder="{{ __('external_resources.workflow.reason') }}"><button class="admin-btn admin-btn-secondary" type="submit">{{ __('external_resources.workflow.actions.return_to_draft') }}</button></form>
                    @endif
                    @if ($resource->publication_status === 'published')
                        <form method="POST" action="{{ route('librarian.external-resources.workflow', $resource) }}" class="flex gap-2">@csrf<input type="hidden" name="action" value="archive"><input class="admin-input" name="reason" required minlength="5" placeholder="{{ __('external_resources.workflow.reason') }}"><button class="admin-btn admin-btn-danger" type="submit">{{ __('external_resources.workflow.actions.archive') }}</button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="admin-card lg:col-span-2 py-14 text-center text-sm text-slate-500">{{ __('external_resources.workflow.empty') }}</div>
        @endforelse
    </div>

    <div class="mt-6"><x-admin.pagination :paginator="$resources" /></div>
@endsection
