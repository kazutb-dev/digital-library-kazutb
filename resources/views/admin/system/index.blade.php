@extends('layouts.admin')

@php
    $c = match(app()->getLocale()) {
        'ru' => ['title'=>'Система и резервные копии','sub'=>'Безопасный технический контроль без отображения секретов','health'=>'Состояние системы','backups'=>'Проверенные резервные копии','create'=>'Создать backup','restore'=>'Восстановить в изолированную test DB','confirm'=>'Введите RESTORE TO TEST','empty'=>'Резервных копий, созданных из панели, пока нет','verified'=>'Checksum подтверждён','not_verified'=>'Не проверен'],
        'en' => ['title'=>'System & backups','sub'=>'Safe technical control without exposing secrets','health'=>'System health','backups'=>'Verified backups','create'=>'Create backup','restore'=>'Restore into isolated test DB','confirm'=>'Type RESTORE TO TEST','empty'=>'No control-plane backups yet','verified'=>'Checksum verified','not_verified'=>'Not verified'],
        default => ['title'=>'Жүйе және сақтық көшірмелер','sub'=>'Құпияларды көрсетпейтін қауіпсіз техникалық бақылау','health'=>'Жүйе күйі','backups'=>'Тексерілген сақтық көшірмелер','create'=>'Backup жасау','restore'=>'Оқшауланған test DB-ге қалпына келтіру','confirm'=>'RESTORE TO TEST енгізіңіз','empty'=>'Панель арқылы жасалған көшірмелер әзірге жоқ','verified'=>'Checksum расталды','not_verified'=>'Тексерілмеген'],
    };
@endphp

@section('title', $c['title'].' — Kazakh University of Technology and Business named after K. Kulazhanov')
@section('content')
<x-admin.page-header :title="$c['title']" :subtitle="$c['sub']" />
@if(session('success'))<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>@endif

<section class="admin-card mb-8"><h2 class="font-headline text-3xl text-primary">{{ $c['health'] }}</h2><div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
@foreach($health as $key => $value)<div class="rounded-xl bg-surface-low p-4"><small class="block text-slate-500">{{ str($key)->replace('_',' ')->headline() }}</small><strong class="mt-1 block break-all">@if(is_bool($value)){{ $value ? 'OK' : 'Unavailable' }}@elseif($key === 'filesystem_free'){{ number_format($value / 1024 / 1024 / 1024, 1) }} GB free @else{{ number_format($value) }}@endif</strong></div>@endforeach
</div><p class="mt-4 text-xs text-slate-500">Queue and scheduler are supervised outside this page; this surface never starts, stops, or retries jobs automatically.</p></section>

<section class="admin-card"><div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="font-headline text-3xl text-primary">{{ $c['backups'] }}</h2><p class="mt-1 text-sm text-slate-500">pg_dump custom format · SHA-256 · readable TOC · isolated restore only</p></div><form method="POST" action="{{ route('admin.system.backups.create') }}">@csrf<button class="admin-btn admin-btn-primary"><span class="material-symbols-outlined">backup</span>{{ $c['create'] }}</button></form></div>
<div class="mt-6 space-y-4">@forelse($backups as $backup)<article class="rounded-xl border border-slate-200 p-5"><div class="flex flex-wrap justify-between gap-4"><div><strong class="font-mono text-sm">{{ $backup['name'] }}</strong><p class="mt-1 text-xs text-slate-500">{{ number_format($backup['size']) }} bytes · {{ $backup['modified_at'] }}</p><p class="mt-1 break-all font-mono text-[11px] text-slate-500">SHA-256 {{ $backup['sha256'] }}</p></div><x-admin.status-badge :status="$backup['verified'] ? 'verified' : 'failed'" :label="$backup['verified'] ? $c['verified'] : $c['not_verified']" /></div>
@if($backup['restore_test'])<p class="mt-3 rounded-lg bg-emerald-50 p-3 text-xs text-emerald-900">Restore test: {{ $backup['restore_test']['database'] }} · {{ $backup['restore_test']['verified_at'] }}</p>@endif
<form method="POST" action="{{ route('admin.system.backups.restore-test', $backup['name']) }}" class="mt-4 flex flex-wrap gap-3">@csrf<label class="sr-only" for="confirmation-{{ $loop->index }}">{{ $c['confirm'] }}</label><input id="confirmation-{{ $loop->index }}" name="confirmation" class="admin-input max-w-xs" required placeholder="RESTORE TO TEST" autocomplete="off"><button class="admin-btn admin-btn-secondary"><span class="material-symbols-outlined">restore</span>{{ $c['restore'] }}</button></form></article>@empty<p class="py-8 text-center text-slate-500">{{ $c['empty'] }}</p>@endforelse</div>
<p class="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Live database promotion is intentionally not available from the web interface.</p></section>
@endsection
