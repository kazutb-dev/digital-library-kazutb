@props(['status', 'label' => null])
@php
    $tone = match ((string) $status) {
        'active', 'published', 'resolved', 'healthy', 'configured', 'open' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'scheduled', 'in_review', 'medium', 'expiring_soon', 'pending' => 'border-amber-200 bg-amber-50 text-amber-800',
        'inactive', 'archived', 'not_configured', 'unknown' => 'border-slate-200 bg-slate-100 text-slate-600',
        'expired', 'failed', 'high', 'critical' => 'border-red-200 bg-red-50 text-red-800',
        default => 'border-cyan-200 bg-cyan-50 text-cyan-800',
    };
@endphp
<span {{ $attributes->class("inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {$tone}") }}>
    {{ $label ?? $status }}
</span>
