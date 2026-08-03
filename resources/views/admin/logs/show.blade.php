@extends('layouts.admin')

@section('title', __('admin.audit.diff.title').' — #'.$activityLog->getKey())

@section('content')
    @php
        $action = (string) $activityLog->action_type;
        $actionLabel = $action;
        $fullAction = str($action)->replace(['.', '-'], '_')->value();
        $shortAction = str($action)->afterLast('.')->replace('-', '_')->value();
        foreach (array_unique([$fullAction, $shortAction]) as $normalizedAction) {
            if (\Illuminate\Support\Facades\Lang::has('admin.audit.actions.'.$normalizedAction)) {
                $actionLabel = __('admin.audit.actions.'.$normalizedAction);
                break;
            }
        }

        $entity = (string) $activityLog->entity_type;
        $normalizedEntity = str($entity)->afterLast('\\')->snake()->value();
        $entityLabel = \Illuminate\Support\Facades\Lang::has('admin.audit.entities.'.$normalizedEntity)
            ? __('admin.audit.entities.'.$normalizedEntity)
            : $entity;

        $actorRole = (string) $activityLog->actor_role;
        $actorRoleLabel = \Illuminate\Support\Facades\Lang::has('roles.names.'.$actorRole)
            ? __('roles.names.'.$actorRole)
            : $actorRole;

        $flatten = function (mixed $value, string $prefix = '') use (&$flatten): array {
            if (! is_array($value)) {
                return $prefix === '' ? [] : [$prefix => $value];
            }

            if ($value === []) {
                return $prefix === '' ? [] : [$prefix => []];
            }

            $flat = [];
            foreach ($value as $key => $child) {
                $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                $flat = array_merge($flat, is_array($child) ? $flatten($child, $path) : [$path => $child]);
            }

            return $flat;
        };

        $oldValues = $flatten($activityLog->old_values ?? []);
        $newValues = $flatten($activityLog->new_values ?? []);
        $diffFields = array_values(array_unique(array_merge(array_keys($oldValues), array_keys($newValues))));
        sort($diffFields, SORT_NATURAL | SORT_FLAG_CASE);

        $displayValue = static function (mixed $value): string {
            if ($value === null || $value === '') {
                return '—';
            }

            if (is_bool($value)) {
                return $value ? __('common.boolean.yes') : __('common.boolean.no');
            }

            if (is_array($value)) {
                return $value === [] ? __('common.fields.none') : implode(', ', array_map('strval', $value));
            }

            return (string) $value;
        };
    @endphp

    <x-admin.page-header
        :eyebrow="__('admin.audit.eyebrow')"
        :title="__('admin.audit.diff.title')"
        :subtitle="$actionLabel.' · '.$entityLabel.' #'.$activityLog->entity_id"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.logs.index') }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
    </x-admin.page-header>

    <section class="admin-card mb-6">
        <dl class="grid gap-x-8 gap-y-6 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <dt class="admin-label">{{ __('admin.audit.fields.timestamp') }}</dt>
                <dd class="font-mono text-sm text-slate-700">
                    {{ $activityLog->occurred_at?->utc()->format('Y-m-d H:i:s') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="admin-label">{{ __('admin.audit.fields.action') }}</dt>
                <dd><x-admin.status-badge status="event" :label="$actionLabel" /></dd>
            </div>
            <div>
                <dt class="admin-label">{{ __('admin.audit.fields.entity_type') }}</dt>
                <dd class="text-sm font-semibold text-primary">{{ $entityLabel }}</dd>
            </div>
            <div>
                <dt class="admin-label">{{ __('admin.audit.fields.entity_id') }}</dt>
                <dd class="font-mono text-sm text-slate-700">#{{ $activityLog->entity_id }}</dd>
            </div>
            <div>
                <dt class="admin-label">{{ __('admin.audit.fields.actor') }}</dt>
                <dd class="text-sm text-slate-700">
                    {{ $activityLog->actor_name ?: '—' }}
                    @if ($activityLog->actor_id)
                        <span class="font-mono text-xs text-slate-400">#{{ $activityLog->actor_id }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="admin-label">{{ __('admin.audit.fields.actor_role') }}</dt>
                <dd class="text-sm text-slate-700">{{ $actorRoleLabel ?: '—' }}</dd>
            </div>
            <div>
                <dt class="admin-label">{{ __('admin.audit.fields.ip_address') }}</dt>
                <dd class="font-mono text-sm text-slate-700">{{ $activityLog->ip_address ?: '—' }}</dd>
            </div>
            <div>
                <dt class="admin-label">{{ __('admin.audit.fields.reason') }}</dt>
                <dd class="text-sm leading-6 text-slate-700">
                    {{ $activityLog->reason ?: __('admin.audit.reason_missing') }}
                </dd>
            </div>
        </dl>
    </section>

    <section class="admin-card overflow-hidden p-0">
        <div class="border-b border-slate-100 px-5 py-5">
            <h2 class="font-headline text-2xl text-primary">{{ __('admin.audit.diff.title') }}</h2>
        </div>

        @if ($diffFields !== [])
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>{{ __('admin.audit.diff.field') }}</th>
                            <th>{{ __('admin.audit.diff.before') }}</th>
                            <th>{{ __('admin.audit.diff.after') }}</th>
                            <th>{{ __('common.fields.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($diffFields as $field)
                            @php
                                $hasOld = array_key_exists($field, $oldValues);
                                $hasNew = array_key_exists($field, $newValues);
                                $oldValue = $oldValues[$field] ?? null;
                                $newValue = $newValues[$field] ?? null;

                                if (! $hasOld && $hasNew) {
                                    $changeState = 'added';
                                } elseif ($hasOld && ! $hasNew) {
                                    $changeState = 'removed';
                                } elseif ($oldValue === $newValue) {
                                    $changeState = 'unchanged';
                                } else {
                                    $changeState = 'changed';
                                }
                            @endphp
                            <tr @class(['opacity-60' => $changeState === 'unchanged'])>
                                <td>
                                    <code class="rounded bg-slate-100 px-2 py-1 text-xs text-primary">{{ $field }}</code>
                                </td>
                                <td>
                                    <div @class([
                                        'max-w-xl whitespace-pre-wrap break-words rounded-lg px-3 py-2 text-sm leading-6',
                                        'bg-red-50 text-red-900' => in_array($changeState, ['removed', 'changed'], true),
                                        'bg-slate-50 text-slate-600' => ! in_array($changeState, ['removed', 'changed'], true),
                                    ])>{{ $hasOld ? $displayValue($oldValue) : '—' }}</div>
                                </td>
                                <td>
                                    <div @class([
                                        'max-w-xl whitespace-pre-wrap break-words rounded-lg px-3 py-2 text-sm leading-6',
                                        'bg-emerald-50 text-emerald-900' => in_array($changeState, ['added', 'changed'], true),
                                        'bg-slate-50 text-slate-600' => ! in_array($changeState, ['added', 'changed'], true),
                                    ])>{{ $hasNew ? $displayValue($newValue) : '—' }}</div>
                                </td>
                                <td>
                                    @if ($changeState === 'changed')
                                        <x-admin.status-badge status="event" :label="__('admin.audit.actions.update')" />
                                    @else
                                        <x-admin.status-badge
                                            :status="$changeState === 'removed' ? 'failed' : ($changeState === 'added' ? 'active' : 'inactive')"
                                            :label="__('admin.audit.diff.'.$changeState)"
                                        />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-16 text-center text-sm text-slate-500">
                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">difference</span>
                {{ __('admin.audit.diff.no_changes') }}
            </div>
        @endif
    </section>
@endsection
