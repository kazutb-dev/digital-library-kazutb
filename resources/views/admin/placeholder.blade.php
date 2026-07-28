@extends('layouts.admin', ['title' => ($title ?? 'Admin Module') . ' — KazUTB Smart Library'])

@section('content')
  <div class="mb-12">
    <h1 class="font-headline text-[3.5rem] leading-tight text-primary tracking-tight">{{ $title ?? 'Admin Module' }}</h1>
    <p class="font-body text-[1rem] text-on-surface-variant mt-2 max-w-2xl">{{ $description ?? 'This governance surface is staged next after the Admin Overview foundation.' }}</p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    <section class="lg:col-span-8 bg-surface-container-lowest rounded-xl p-8 border border-outline-variant/20 shadow-sm">
      <div class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-primary mb-4">
        <span class="material-symbols-outlined text-[16px]">construction</span>
        <span>Phase planned</span>
      </div>
      <h2 class="font-headline text-3xl text-primary mb-3">Foundation is ready for the next admin wave</h2>
      <p class="text-on-surface-variant leading-7">The reusable admin shell, governance navigation, and protected route foundation are now in place. This module will be expanded in the next phase without changing the core shell.</p>
    </section>

    <section class="lg:col-span-4 bg-surface-container-lowest rounded-xl p-6 border border-outline-variant/20 shadow-sm">
      <h3 class="font-bold text-primary mb-3">Next implementation note</h3>
      <ul class="space-y-3 text-sm text-on-surface-variant">
        <li>• Preserve the same admin shell and side navigation.</li>
        <li>• Keep content tied to KazUTB library governance reality.</li>
        <li>• Wire real backend aggregations in follow-up phases.</li>
      </ul>
    </section>
  </div>
@endsection
