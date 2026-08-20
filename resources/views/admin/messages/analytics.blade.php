@extends('layouts.admin')
@section('title', __('messages.analytics'))
@section('content')
<x-admin.page-header :title="__('messages.analytics')" :subtitle="__('messages.analytics_help')" />
<div class="grid gap-4 md:grid-cols-3"><div class="admin-card"><span>{{ __('messages.metrics.total') }}</span><strong class="block font-headline text-4xl">{{ $total }}</strong></div><div class="admin-card"><span>{{ __('messages.metrics.overdue') }}</span><strong class="block font-headline text-4xl">{{ $overdue }}</strong></div><div class="admin-card"><span>{{ __('messages.metrics.satisfaction') }}</span><strong class="block font-headline text-4xl">{{ $satisfaction }}</strong></div><div class="admin-card"><span>{{ __('messages.metrics.first_response') }}</span><strong class="block text-2xl">{{ $averageFirstResponseMinutes }} min</strong></div><div class="admin-card"><span>{{ __('messages.metrics.resolution') }}</span><strong class="block text-2xl">{{ $averageResolutionMinutes }} min</strong></div></div>
@endsection
