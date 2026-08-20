@extends('layouts.admin')
@section('title',__('news.title').' — '.__('common.admin_portal'))
@section('content')
<x-admin.page-header :eyebrow="__('news.ledger')" :title="__('news.title')" :subtitle="__('news.subtitle')">@can('news.view_analytics')<a class="admin-btn admin-btn-secondary" href="{{ route('admin.news.analytics') }}">{{ __('news.analytics.title') }}</a>@endcan @can('news.manage_categories')<a class="admin-btn admin-btn-secondary" href="{{ route('admin.news.categories') }}">{{ __('news.categories_admin.title') }}</a>@endcan<a class="admin-btn admin-btn-primary" href="{{ route('admin.news.create') }}">{{ __('news.create') }}</a></x-admin.page-header>
@include('news.staff-ledger',['items'=>$newsItems,'createRoute'=>route('admin.news.create'),'editRouteName'=>'admin.news.edit'])
@endsection
