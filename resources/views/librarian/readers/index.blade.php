@extends('layouts.librarian')

@php
    $c = match(app()->getLocale()) {
        'ru' => ['title'=>'Читатели','sub'=>'Читательские профили и регистрация','search'=>'Поиск','placeholder'=>'ФИО, email или логин','local'=>'Зарегистрированные читатели','directory'=>'Результаты Active Directory','empty'=>'Ничего не найдено','register'=>'Зарегистрировать','category'=>'Категория','status'=>'Статус','ticket'=>'Читательский билет','not_configured'=>'Автоматическая синхронизация пользователей временно недоступна. Локальная регистрация работает.','categories'=>['student'=>'Студент','faculty'=>'Преподаватель','staff'=>'Сотрудник'],'statuses'=>['active'=>'Активен','blocked'=>'Заблокирован','expired'=>'Истёк']],
        'en' => ['title'=>'Readers','sub'=>'Reader profiles and registration','search'=>'Search','placeholder'=>'Name, email, or login','local'=>'Registered readers','directory'=>'Active Directory results','empty'=>'No results','register'=>'Register','category'=>'Category','status'=>'Status','ticket'=>'Library card','not_configured'=>'Automatic user synchronization is temporarily unavailable. Local registration works.','categories'=>['student'=>'Student','faculty'=>'Faculty','staff'=>'Staff'],'statuses'=>['active'=>'Active','blocked'=>'Blocked','expired'=>'Expired']],
        default => ['title'=>'Оқырмандар','sub'=>'Оқырман профильдері және тіркеу','search'=>'Іздеу','placeholder'=>'Аты-жөні, email немесе логин','local'=>'Тіркелген оқырмандар','directory'=>'Active Directory нәтижелері','empty'=>'Нәтиже жоқ','register'=>'Тіркеу','category'=>'Санат','status'=>'Күйі','ticket'=>'Оқырман билеті','not_configured'=>'Пайдаланушыларды автоматты синхрондау уақытша қолжетімсіз. Жергілікті тіркеу жұмыс істейді.','categories'=>['student'=>'Студент','faculty'=>'Оқытушы','staff'=>'Қызметкер'],'statuses'=>['active'=>'Белсенді','blocked'=>'Бұғатталған','expired'=>'Мерзімі өткен']],
    };
@endphp

@section('title', $c['title'].' — Kazakh University of Technology and Business named after K. Kulazhanov')
@section('content')
<div class="mx-auto max-w-7xl space-y-7" data-reader-directory>
    <header><p class="text-xs font-bold uppercase tracking-[.14em] text-cyan-700">Kazakh University of Technology and Business named after K. Kulazhanov</p><h1 class="mt-2 font-headline text-4xl text-slate-900">{{ $c['title'] }}</h1><p class="mt-2 text-slate-600">{{ $c['sub'] }}</p></header>
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>@endif
    <form method="GET" class="flex gap-3" role="search"><label class="sr-only" for="reader-q">{{ $c['search'] }}</label><input id="reader-q" name="q" value="{{ $term }}" minlength="2" maxlength="100" class="w-full rounded-xl border-slate-300" placeholder="{{ $c['placeholder'] }}"><button class="rounded-xl bg-slate-900 px-5 py-3 font-bold text-white">{{ $c['search'] }}</button></form>
    @unless(config('active_directory.enabled'))<p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ $c['not_configured'] }}</p>@endunless

    @if($term !== '' && config('active_directory.enabled'))
    <section class="rounded-2xl bg-white p-6 shadow-sm"><h2 class="font-headline text-2xl">{{ $c['directory'] }}</h2>
        @if($directoryError)<p class="mt-4 rounded-xl bg-red-50 p-4 text-red-900">{{ __('auth.provider_unavailable') }}</p>@endif
        <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">{{ __('common.fields.name') }}</th><th class="p-3">Login</th><th class="p-3">Email</th><th class="p-3">{{ $c['status'] }}</th><th class="p-3">{{ $c['category'] }}</th><th></th></tr></thead><tbody>
        @forelse($directoryUsers as $directoryUser)<tr class="border-b"><td class="p-3">{{ $directoryUser->displayName }}</td><td class="p-3">{{ $directoryUser->samAccountName }}</td><td class="p-3">{{ $directoryUser->mail }}</td><td class="p-3">{{ $directoryUser->enabled ? __('common.status.active') : __('common.status.disabled') }}</td><td colspan="2" class="p-3">@if($directoryUser->enabled)<form method="POST" action="{{ route('librarian.readers.active-directory.provision') }}" class="flex gap-2">@csrf<input type="hidden" name="login" value="{{ $directoryUser->samAccountName }}"><select name="category" required class="rounded-lg border-slate-300"><option value="">—</option>@foreach(['student','faculty','staff'] as $category)<option value="{{ $category }}">{{ $c['categories'][$category] }}</option>@endforeach</select><button class="rounded-lg bg-cyan-700 px-3 py-2 font-bold text-white">{{ $c['register'] }}</button></form>@endif</td></tr>
        @empty<tr><td colspan="6" class="p-6 text-center text-slate-500">{{ $c['empty'] }}</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @endif

    <section class="rounded-2xl bg-white p-6 shadow-sm"><h2 class="font-headline text-2xl">{{ $c['local'] }}</h2><div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">{{ __('common.fields.name') }}</th><th class="p-3">Email</th><th class="p-3">{{ $c['ticket'] }}</th><th class="p-3">{{ $c['category'] }}</th><th class="p-3">{{ $c['status'] }}</th></tr></thead><tbody>@forelse($local as $reader)<tr class="border-b"><td class="p-3 font-semibold">{{ $reader->name }}</td><td class="p-3">{{ $reader->email }}</td><td class="p-3 font-mono">{{ $reader->readerProfile?->ticket_number ?: '—' }}</td><td class="p-3">{{ $c['categories'][$reader->readerProfile?->category] ?? '—' }}</td><td class="p-3">{{ $c['statuses'][$reader->readerProfile?->status] ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="p-6 text-center text-slate-500">{{ $c['empty'] }}</td></tr>@endforelse</tbody></table></div><div class="mt-5"><x-admin.pagination :paginator="$local" /></div></section>
</div>
@endsection
