@extends('layouts.admin')
@section('title', __('news.analytics.title').' — '.__('common.admin_portal'))
@section('content')
<x-admin.page-header :eyebrow="__('news.ledger')" :title="__('news.analytics.title')" :subtitle="__('news.analytics.help')"><a class="admin-btn admin-btn-secondary" href="{{ route('admin.news.index') }}">{{ __('common.actions.back') }}</a></x-admin.page-header>
<div class="grid gap-5 md:grid-cols-3"><div class="admin-card"><p>{{ __('news.analytics.total') }}</p><strong class="text-4xl">{{ $status->sum() }}</strong></div><div class="admin-card"><p>{{ __('news.analytics.published') }}</p><strong class="text-4xl">{{ $status['published']??0 }}</strong></div><div class="admin-card"><p>{{ __('news.analytics.review_time') }}</p><strong class="text-4xl">{{ number_format((float)$averageReviewHours,1) }} h</strong></div></div><section class="admin-card mt-6"><h2 class="text-xl font-bold">{{ __('news.analytics.popular') }}</h2><table class="mt-4 w-full"><tbody>@foreach($popular as $item)<tr class="border-b"><td class="p-3">{{ $item->localized('title') }}</td><td class="p-3 text-right">{{ $item->view_count }}</td></tr>@endforeach</tbody></table></section>
@endsection
