@if (session('success'))
    <div role="status" class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        <span class="material-symbols-outlined text-[20px]">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if (session('error'))
    <div role="alert" class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        <span class="material-symbols-outlined text-[20px]">error</span>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if ($errors->any())
    <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        <div class="mb-2 flex items-center gap-2 font-bold">
            <span class="material-symbols-outlined text-[20px]">error</span>
            <span>{{ __('common.feedback.validation_failed') }}</span>
        </div>
        <ul class="list-disc space-y-1 pl-6">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
