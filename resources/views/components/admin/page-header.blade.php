@props(['eyebrow' => null, 'title', 'subtitle' => null])
<div class="mb-8 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
    <div class="max-w-4xl">
        @if ($eyebrow)
            <p class="mb-2 text-xs font-bold uppercase tracking-[.16em] text-secondary">{{ $eyebrow }}</p>
        @endif
        <h1 class="font-headline text-4xl leading-none tracking-tight text-primary sm:text-5xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim((string) $slot) !== '')
        <div class="flex flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
