@extends('layouts.admin')
@section('title', ($newsItem->exists ? __('news.edit') : __('news.create')).' — '.__('common.admin_portal'))
@section('content')
@include('news.editor-form', ['publication'=>$newsItem,'formAction'=>$newsItem->exists?route('admin.news.update',$newsItem):route('admin.news.store'),'backUrl'=>route('admin.news.index'),'transitionRoute'=>$newsItem->exists?route('admin.news.transition',$newsItem):null,'emergencyRoute'=>$newsItem->exists?route('admin.news.emergency-publish',$newsItem):null,'autosaveUrl'=>$newsItem->exists?route('admin.news.autosave',$newsItem):null])
@endsection
