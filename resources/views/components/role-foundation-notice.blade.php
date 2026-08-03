@props(['role'])

<section {{ $attributes->class(['rounded-xl border border-secondary/20 bg-secondary/5 px-5 py-4']) }}>
    <div class="flex items-start gap-3">
        <span class="material-symbols-outlined mt-0.5 text-[21px] text-secondary">construction</span>
        <div>
            <h2 class="text-sm font-bold text-primary-container">
                {{ __('roles.names.'.$role) }}
            </h2>
            <p class="mt-1 text-sm leading-6 text-on-surface-variant">
                {{ __('roles.landing_notice', ['role' => __('roles.names.'.$role)]) }}
            </p>
            {{-- What this role can already do. Listed before the roadmap so the
                 banner never implies a delivered feature is still pending. --}}
            @if (\Illuminate\Support\Facades\Lang::has('roles.delivered.'.$role))
                <p class="mt-2 text-xs leading-5 text-on-surface-variant">
                    <strong class="text-primary-container">{{ __('roles.delivered_label') }}:</strong>
                    {{ __('roles.delivered.'.$role) }}
                </p>
            @endif

            @if (\Illuminate\Support\Facades\Lang::has('roles.upcoming.'.$role))
                <p class="mt-2 text-xs leading-5 text-on-surface-variant">
                    <strong>{{ __('roles.upcoming_label') }}:</strong>
                    {{ __('roles.upcoming.'.$role) }}
                </p>
            @endif
        </div>
    </div>
</section>
