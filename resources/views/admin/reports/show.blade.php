@extends('layouts.admin')

@php
    $rows = $reportRows;
    $columnLabel = static function (string $column): string {
        $key = 'reports.columns.'.$column;

        return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : $column;
    };
@endphp

@section('title', $reportTitle.' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="$reportTitle" :subtitle="__('reports.data_integrity_notice')">
        <a href="{{ route('admin.reports.index', $filters) }}" class="admin-btn admin-btn-secondary"><span class="material-symbols-outlined text-[19px]">arrow_back</span>{{ __('common.actions.back') }}</a>
        @can('reports.export')
            <a href="{{ route('admin.reports.export', ['type' => $reportType, 'format' => 'csv'] + $filters) }}" class="admin-btn admin-btn-secondary">CSV</a>
            <a href="{{ route('admin.reports.export', ['type' => $reportType, 'format' => 'pdf'] + $filters) }}" class="admin-btn admin-btn-primary">PDF</a>
        @endcan
    </x-admin.page-header>

    @if ($rows->isEmpty())
        <div class="admin-card py-16 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300">query_stats</span>
            <p class="mt-4 text-sm text-slate-500">{{ $reportType === 'circulation' ? __('reports.data_unavailable_circulation') : ($reportType === 'catalog' ? __('reports.unavailable.catalog') : __('reports.empty')) }}</p>
        </div>
    @else
        <div class="admin-card overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead>
                        <tr>
                            @foreach (array_keys($rows->first()) as $heading)
                                <th>{{ $columnLabel($heading) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ is_array($cell) ? implode(', ', $cell) : ($cell ?? '—') }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
