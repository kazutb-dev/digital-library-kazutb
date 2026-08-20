@extends('layouts.public')
@section('title', __('news.preview').' — '.$publication->localized('title'))
@section('head')<meta name="robots" content="noindex,nofollow"><meta http-equiv="Cache-Control" content="no-store">@endsection
@section('content')
<article class="public-v2 mx-auto max-w-4xl px-5 py-16"><div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 font-bold text-amber-900">{{ __('news.preview_warning') }}</div><p class="text-xs font-bold uppercase tracking-wider text-teal-700">{{ __('news.types.'.$publication->type) }} · {{ __('news.statuses.'.$publication->status) }}</p><h1 class="mt-3 font-headline text-5xl text-primary">{{ $publication->localized('title') }}</h1><p class="mt-4 text-xl text-slate-600">{{ $publication->localized('excerpt') }}</p>@if($publication->cover_image)<img class="my-8 aspect-video w-full rounded-2xl object-cover" src="{{ asset('storage/'.$publication->cover_image) }}" alt="{{ $publication->localized('image_alt') }}">@endif<div class="prose mt-8">{!! app(\App\Services\News\NewsContentSanitizer::class)->sanitize($publication->localized('content')) !!}</div></article>
@endsection
