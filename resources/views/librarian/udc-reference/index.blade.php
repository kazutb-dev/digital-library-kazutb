@extends('layouts.librarian')

@section('title', 'Справочник УДК — '.__('common.app_name'))

@section('content')
    <x-admin.flash />
    <x-admin.page-header
        eyebrow="Каталогизация"
        title="Справочник УДК"
        subtitle="Непроверенные описания требуют уточнения каталогизатором: {{ number_format($unverifiedCount, 0, ',', ' ') }}"
    />

    <form class="admin-card mb-6 flex gap-3" method="GET">
        <input class="admin-input" name="search" value="{{ $search }}" placeholder="Код или описание">
        <button class="admin-btn admin-btn-primary" type="submit">Найти</button>
    </form>

    <div class="space-y-4">
        @foreach ($codes as $code)
            <form class="admin-card" method="POST" action="{{ route('librarian.udc-reference.update', $code) }}">
                @csrf
                @method('PATCH')
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <strong class="font-mono text-xl text-primary">{{ $code->code }}</strong>
                    @if ($code->parent)
                        <span class="text-xs text-slate-500">родитель: {{ $code->parent->code }}</span>
                    @endif
                    <span class="rounded px-2 py-1 text-xs {{ $code->is_verified ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">
                        {{ $code->is_verified ? 'проверено' : 'требует проверки' }}
                    </span>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <label>
                        <span class="admin-label">Описание (RU)</span>
                        <input class="admin-input" name="description" required value="{{ $code->description }}">
                    </label>
                    <label>
                        <span class="admin-label">Направление / факультет</span>
                        <input class="admin-input" name="department" value="{{ $code->department }}">
                    </label>
                    <label>
                        <span class="admin-label">Описание (KK)</span>
                        <input class="admin-input" name="description_kk" value="{{ $code->description_kk }}">
                    </label>
                    <label>
                        <span class="admin-label">Описание (EN)</span>
                        <input class="admin-input" name="description_en" value="{{ $code->description_en }}">
                    </label>
                </div>
                <div class="mt-4 flex items-center justify-between gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_verified" value="1" @checked($code->is_verified)>
                        Описание проверено каталогизатором
                    </label>
                    <button class="admin-btn admin-btn-primary" type="submit">Сохранить</button>
                </div>
            </form>
        @endforeach
    </div>

    <div class="mt-6">{{ $codes->links() }}</div>
@endsection
