@extends('layouts.admin')
@section('title', __('messages.manage_routing'))
@section('content')
<x-admin.flash /><x-admin.page-header :title="__('messages.manage_routing')" :subtitle="__('messages.routing_help')" />
<form class="admin-card mb-6 grid gap-3 md:grid-cols-4" method="POST" action="{{ route('admin.messages.routing.store') }}">@csrf<input class="admin-input" name="name" required placeholder="Rule name"><select class="admin-input" name="category_id"><option value="">—</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->localizedName() }}</option>@endforeach</select><select class="admin-input" name="target_role">@foreach($roles as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach</select><input type="hidden" name="director_visibility" value="0"><input type="hidden" name="active" value="1"><input type="hidden" name="sort_order" value="100"><button class="admin-btn admin-btn-primary">{{ __('common.actions.add') }}</button></form>
<section class="admin-card"><table class="admin-table"><thead><tr><th>{{ __('messages.fields.category') }}</th><th>role</th><th>active</th></tr></thead><tbody>@foreach($rules as $rule)<tr><td>{{ $rule->category?->localizedName() ?? $rule->name }}</td><td>{{ $rule->target_role }}</td><td>{{ $rule->active ? '' : '—' }}</td></tr>@endforeach</tbody></table></section>
@endsection
