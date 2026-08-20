@extends('layouts.librarian')
@section('title', ($item->exists ? __('news.edit') : __('news.create')).' — '.__('brand.library.name'))
@section('content')
@include('news.editor-form', ['publication'=>$item,'formAction'=>$item->exists?route('librarian.news.update',$item):route('librarian.news.store'),'backUrl'=>route('librarian.news.index'),'transitionRoute'=>$item->exists?route('librarian.news.transition',$item):null,'emergencyRoute'=>$item->exists?route('librarian.news.emergency-publish',$item):null,'autosaveUrl'=>$item->exists?route('librarian.news.autosave',$item):null])
@endsection
