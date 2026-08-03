@extends('layouts.admin')

@section('title', __('admin.branches.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :title="__('admin.branches.title')"
        :subtitle="__('admin.branches.subtitle')"
    />

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-2">
        <section>
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="font-headline text-3xl text-primary">{{ __('admin.branches.title') }}</h2>
                <x-admin.status-badge status="active" :label="(string) $branches->count()" />
            </div>

            <details class="admin-card mb-5" @if($errors->hasAny(['name', 'code', 'type', 'address'])) open @endif>
                <summary class="flex cursor-pointer items-center justify-between gap-3 font-bold text-primary">
                    <span class="flex items-center gap-2"><span class="material-symbols-outlined text-secondary">add_business</span>{{ __('admin.branches.create') }}</span>
                    <span class="material-symbols-outlined">expand_more</span>
                </summary>
                <form method="POST" action="{{ route('admin.branches.store') }}" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="sort_order" value="0">
                    <div class="sm:col-span-2">
                        <label class="admin-label" for="branch-new-name">{{ __('admin.branches.fields.name') }}</label>
                        <input class="admin-input" id="branch-new-name" name="name" required value="{{ old('name') }}">
                    </div>
                    <div>
                        <label class="admin-label" for="branch-new-code">{{ __('admin.branches.fields.code') }}</label>
                        <input class="admin-input uppercase" id="branch-new-code" name="code" required value="{{ old('code') }}">
                    </div>
                    <div>
                        <label class="admin-label" for="branch-new-type">{{ __('admin.branches.fields.type') }}</label>
                        <select class="admin-input" id="branch-new-type" name="type" required>
                            @foreach (['library', 'circulation_desk', 'reading_room', 'service_point'] as $type)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ __('admin.branches.types.'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="admin-label" for="branch-new-address">{{ __('admin.branches.fields.address') }}</label>
                        <input class="admin-input" id="branch-new-address" name="address" value="{{ old('address') }}">
                    </div>
                    <div>
                        <label class="admin-label" for="branch-new-phone">{{ __('admin.branches.fields.phone') }}</label>
                        <input class="admin-input" id="branch-new-phone" name="phone" value="{{ old('phone') }}">
                    </div>
                    <div>
                        <label class="admin-label" for="branch-new-email">{{ __('admin.branches.fields.email') }}</label>
                        <input class="admin-input" id="branch-new-email" type="email" name="email" value="{{ old('email') }}">
                    </div>
                    <div>
                        <label class="admin-label" for="branch-new-weekdays">{{ __('admin.branches.fields.opening_hours') }} · 1–5</label>
                        <input class="admin-input" id="branch-new-weekdays" name="opening_hours[weekdays]" value="{{ old('opening_hours.weekdays') }}">
                    </div>
                    <div>
                        <label class="admin-label" for="branch-new-weekend">{{ __('admin.branches.fields.opening_hours') }} · 6–7</label>
                        <input class="admin-input" id="branch-new-weekend" name="opening_hours[weekend]" value="{{ old('opening_hours.weekend') }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="admin-label" for="branch-new-description">{{ __('common.fields.description') }}</label>
                        <textarea class="admin-input min-h-24" id="branch-new-description" name="description">{{ old('description') }}</textarea>
                    </div>
                    <label class="flex items-center gap-3 text-sm sm:col-span-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        {{ __('admin.branches.fields.is_active') }}
                    </label>
                    <div class="sm:col-span-2">
                        <button class="admin-btn admin-btn-primary" type="submit"><span class="material-symbols-outlined text-[19px]">save</span>{{ __('common.actions.create') }}</button>
                    </div>
                </form>
            </details>

            <div class="space-y-4">
                @forelse ($branches as $branch)
                    <details class="admin-card group">
                        <summary class="flex cursor-pointer items-start justify-between gap-4">
                            <span class="min-w-0">
                                <span class="mb-2 flex flex-wrap items-center gap-2">
                                    <x-admin.status-badge :status="$branch->is_active ? 'active' : 'inactive'" :label="__('common.status.'.($branch->is_active ? 'active' : 'inactive'))" />
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ __('admin.branches.types.'.$branch->type) }}</span>
                                </span>
                                <strong class="block font-headline text-2xl text-primary">{{ $branch->name }}</strong>
                                <small class="mt-1 block text-slate-500">{{ $branch->code }} · {{ $branch->funds_count }} · {{ __('admin.funds.title') }}</small>
                            </span>
                            <span class="material-symbols-outlined transition group-open:rotate-180">expand_more</span>
                        </summary>

                        <form method="POST" action="{{ route('admin.branches.update', $branch) }}" class="mt-6 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="sort_order" value="{{ $branch->sort_order }}">
                            <div class="sm:col-span-2">
                                <label class="admin-label" for="branch-{{ $branch->id }}-name">{{ __('admin.branches.fields.name') }}</label>
                                <input class="admin-input" id="branch-{{ $branch->id }}-name" name="name" required value="{{ $branch->name }}">
                            </div>
                            <div>
                                <label class="admin-label" for="branch-{{ $branch->id }}-code">{{ __('admin.branches.fields.code') }}</label>
                                <input class="admin-input uppercase" id="branch-{{ $branch->id }}-code" name="code" required value="{{ $branch->code }}">
                            </div>
                            <div>
                                <label class="admin-label" for="branch-{{ $branch->id }}-type">{{ __('admin.branches.fields.type') }}</label>
                                <select class="admin-input" id="branch-{{ $branch->id }}-type" name="type" required>
                                    @foreach (['library', 'circulation_desk', 'reading_room', 'service_point'] as $type)
                                        <option value="{{ $type }}" @selected($branch->type === $type)>{{ __('admin.branches.types.'.$type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="admin-label" for="branch-{{ $branch->id }}-address">{{ __('admin.branches.fields.address') }}</label>
                                <input class="admin-input" id="branch-{{ $branch->id }}-address" name="address" value="{{ $branch->address }}">
                            </div>
                            <div>
                                <label class="admin-label" for="branch-{{ $branch->id }}-phone">{{ __('admin.branches.fields.phone') }}</label>
                                <input class="admin-input" id="branch-{{ $branch->id }}-phone" name="phone" value="{{ $branch->phone }}">
                            </div>
                            <div>
                                <label class="admin-label" for="branch-{{ $branch->id }}-email">{{ __('admin.branches.fields.email') }}</label>
                                <input class="admin-input" id="branch-{{ $branch->id }}-email" type="email" name="email" value="{{ $branch->email }}">
                            </div>
                            <div>
                                <label class="admin-label" for="branch-{{ $branch->id }}-weekdays">{{ __('admin.branches.fields.opening_hours') }} · 1–5</label>
                                <input class="admin-input" id="branch-{{ $branch->id }}-weekdays" name="opening_hours[weekdays]" value="{{ data_get($branch->opening_hours, 'weekdays') }}">
                            </div>
                            <div>
                                <label class="admin-label" for="branch-{{ $branch->id }}-weekend">{{ __('admin.branches.fields.opening_hours') }} · 6–7</label>
                                <input class="admin-input" id="branch-{{ $branch->id }}-weekend" name="opening_hours[weekend]" value="{{ data_get($branch->opening_hours, 'weekend') }}">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="admin-label" for="branch-{{ $branch->id }}-description">{{ __('common.fields.description') }}</label>
                                <textarea class="admin-input min-h-24" id="branch-{{ $branch->id }}-description" name="description">{{ $branch->description }}</textarea>
                            </div>
                            <label class="flex items-center gap-3 text-sm sm:col-span-2">
                                <input type="hidden" name="is_active" value="0">
                                <input class="rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="is_active" value="1" @checked($branch->is_active)>
                                {{ __('admin.branches.fields.is_active') }}
                            </label>
                            <div class="sm:col-span-2">
                                <button class="admin-btn admin-btn-primary" type="submit">{{ __('common.actions.save_changes') }}</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}" class="mt-5 flex flex-col gap-3 rounded-xl border border-red-100 bg-red-50/50 p-4">
                            @csrf
                            @method('DELETE')
                            <label class="admin-label" for="branch-{{ $branch->id }}-reason">{{ __('common.validation.reason_required') }}</label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input class="admin-input flex-1" id="branch-{{ $branch->id }}-reason" name="reason" required minlength="5" placeholder="{{ __('common.fields.reason') }}">
                                <button class="admin-btn admin-btn-danger" type="submit">{{ __('admin.branches.delete') }}</button>
                            </div>
                        </form>
                    </details>
                @empty
                    <div class="admin-card text-center text-sm text-slate-500">{{ __('admin.branches.empty') }}</div>
                @endforelse
            </div>
        </section>

        <section>
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="font-headline text-3xl text-primary">{{ __('admin.funds.title') }}</h2>
                <x-admin.status-badge status="active" :label="(string) $funds->count()" />
            </div>

            <details class="admin-card mb-5">
                <summary class="flex cursor-pointer items-center justify-between gap-3 font-bold text-primary">
                    <span class="flex items-center gap-2"><span class="material-symbols-outlined text-secondary">library_add</span>{{ __('admin.funds.create') }}</span>
                    <span class="material-symbols-outlined">expand_more</span>
                </summary>
                <form method="POST" action="{{ route('admin.funds.store') }}" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="sort_order" value="0">
                    <div class="sm:col-span-2">
                        <label class="admin-label" for="fund-new-name">{{ __('admin.funds.fields.name') }}</label>
                        <input class="admin-input" id="fund-new-name" name="name" required>
                    </div>
                    <div>
                        <label class="admin-label" for="fund-new-code">{{ __('admin.funds.fields.code') }}</label>
                        <input class="admin-input uppercase" id="fund-new-code" name="code" required>
                    </div>
                    <div>
                        <label class="admin-label" for="fund-new-branch">{{ __('admin.funds.fields.branch') }}</label>
                        <select class="admin-input" id="fund-new-branch" name="branch_id">
                            <option value="">{{ __('common.fields.none') }}</option>
                            @foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="admin-label" for="fund-new-type">{{ __('admin.funds.fields.type') }}</label>
                        <select class="admin-input" id="fund-new-type" name="fund_type" required>
                            @foreach (['main', 'educational', 'research', 'periodicals', 'electronic'] as $type)
                                <option value="{{ $type }}">{{ __('admin.funds.types.'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="admin-label" for="fund-new-scope">{{ __('admin.funds.fields.scope') }}</label>
                        <select class="admin-input" id="fund-new-scope" name="institutional_scope" required>
                            @foreach (['general', 'college', 'university_economic', 'university_technology'] as $scope)
                                <option value="{{ $scope }}">{{ __('admin.funds.scopes.'.$scope) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="admin-label" for="fund-new-location">{{ __('admin.funds.fields.location') }}</label>
                        <input class="admin-input" id="fund-new-location" name="location">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="admin-label" for="fund-new-description">{{ __('admin.funds.fields.description') }}</label>
                        <textarea class="admin-input min-h-24" id="fund-new-description" name="description"></textarea>
                    </div>
                    <label class="flex items-center gap-3 text-sm sm:col-span-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="is_active" value="1" checked>
                        {{ __('admin.funds.fields.is_active') }}
                    </label>
                    <div class="sm:col-span-2">
                        <button class="admin-btn admin-btn-primary" type="submit">{{ __('common.actions.create') }}</button>
                    </div>
                </form>
            </details>

            <div class="space-y-4">
                @forelse ($funds as $fund)
                    <details class="admin-card group">
                        <summary class="flex cursor-pointer items-start justify-between gap-4">
                            <span class="min-w-0">
                                <span class="mb-2 flex flex-wrap gap-2">
                                    <x-admin.status-badge :status="$fund->is_active ? 'active' : 'inactive'" :label="__('common.status.'.($fund->is_active ? 'active' : 'inactive'))" />
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ __('admin.funds.types.'.$fund->fund_type) }}</span>
                                </span>
                                <strong class="block font-headline text-2xl text-primary">{{ $fund->name }}</strong>
                                <small class="mt-1 block text-slate-500">{{ $fund->code }} · {{ $fund->branch?->name ?? __('common.fields.none') }}</small>
                            </span>
                            <span class="material-symbols-outlined transition group-open:rotate-180">expand_more</span>
                        </summary>

                        <form method="POST" action="{{ route('admin.funds.update', $fund) }}" class="mt-6 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="sort_order" value="{{ $fund->sort_order }}">
                            <div class="sm:col-span-2">
                                <label class="admin-label" for="fund-{{ $fund->id }}-name">{{ __('admin.funds.fields.name') }}</label>
                                <input class="admin-input" id="fund-{{ $fund->id }}-name" name="name" required value="{{ $fund->name }}">
                            </div>
                            <div>
                                <label class="admin-label" for="fund-{{ $fund->id }}-code">{{ __('admin.funds.fields.code') }}</label>
                                <input class="admin-input uppercase" id="fund-{{ $fund->id }}-code" name="code" required value="{{ $fund->code }}">
                            </div>
                            <div>
                                <label class="admin-label" for="fund-{{ $fund->id }}-branch">{{ __('admin.funds.fields.branch') }}</label>
                                <select class="admin-input" id="fund-{{ $fund->id }}-branch" name="branch_id">
                                    <option value="">{{ __('common.fields.none') }}</option>
                                    @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($fund->branch_id === $branch->id)>{{ $branch->name }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="admin-label" for="fund-{{ $fund->id }}-type">{{ __('admin.funds.fields.type') }}</label>
                                <select class="admin-input" id="fund-{{ $fund->id }}-type" name="fund_type" required>
                                    @foreach (['main', 'educational', 'research', 'periodicals', 'electronic'] as $type)
                                        <option value="{{ $type }}" @selected($fund->fund_type === $type)>{{ __('admin.funds.types.'.$type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="admin-label" for="fund-{{ $fund->id }}-scope">{{ __('admin.funds.fields.scope') }}</label>
                                <select class="admin-input" id="fund-{{ $fund->id }}-scope" name="institutional_scope" required>
                                    @foreach (['general', 'college', 'university_economic', 'university_technology'] as $scope)
                                        <option value="{{ $scope }}" @selected($fund->institutional_scope === $scope)>{{ __('admin.funds.scopes.'.$scope) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="admin-label" for="fund-{{ $fund->id }}-location">{{ __('admin.funds.fields.location') }}</label>
                                <input class="admin-input" id="fund-{{ $fund->id }}-location" name="location" value="{{ $fund->location }}">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="admin-label" for="fund-{{ $fund->id }}-description">{{ __('admin.funds.fields.description') }}</label>
                                <textarea class="admin-input min-h-24" id="fund-{{ $fund->id }}-description" name="description">{{ $fund->description }}</textarea>
                            </div>
                            <label class="flex items-center gap-3 text-sm sm:col-span-2">
                                <input type="hidden" name="is_active" value="0">
                                <input class="rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="is_active" value="1" @checked($fund->is_active)>
                                {{ __('admin.funds.fields.is_active') }}
                            </label>
                            <div class="sm:col-span-2">
                                <button class="admin-btn admin-btn-primary" type="submit">{{ __('common.actions.save_changes') }}</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.funds.destroy', $fund) }}" class="mt-5 flex flex-col gap-3 rounded-xl border border-red-100 bg-red-50/50 p-4">
                            @csrf
                            @method('DELETE')
                            <label class="admin-label" for="fund-{{ $fund->id }}-reason">{{ __('common.validation.reason_required') }}</label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input class="admin-input flex-1" id="fund-{{ $fund->id }}-reason" name="reason" required minlength="5" placeholder="{{ __('common.fields.reason') }}">
                                <button class="admin-btn admin-btn-danger" type="submit">{{ __('admin.funds.delete') }}</button>
                            </div>
                        </form>
                    </details>
                @empty
                    <div class="admin-card text-center text-sm text-slate-500">{{ __('admin.funds.empty') }}</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
