{{-- Shared feedback strip for the reader cabinet: success flashes and the
     localised CirculationException messages surfaced by CabinetController. --}}
@if (session('success'))
  <div class="mb-8 flex items-start gap-3 rounded-xl bg-secondary-container/50 px-5 py-4 text-on-secondary-container" role="status">
    <span class="material-symbols-outlined text-[20px] mt-0.5">check_circle</span>
    <p class="font-body text-sm leading-relaxed">{{ session('success') }}</p>
  </div>
@endif

@if ($errors->any())
  <div class="mb-8 flex items-start gap-3 rounded-xl bg-error-container px-5 py-4 text-on-error-container" role="alert">
    <span class="material-symbols-outlined text-[20px] mt-0.5">error</span>
    <div class="font-body text-sm leading-relaxed space-y-1">
      @foreach ($errors->all() as $message)
        <p>{{ $message }}</p>
      @endforeach
    </div>
  </div>
@endif
