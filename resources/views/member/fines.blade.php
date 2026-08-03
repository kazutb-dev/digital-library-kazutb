@extends('layouts.member', ['title' => __('librarian.member.fines.title').' — '.__('common.app_name')])

@section('content')
  @include('member.partials.flash')

  <header class="mb-10 md:mb-14">
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
      <span class="w-2 h-2 rounded-full {{ $pendingCount > 0 ? 'bg-error' : 'bg-secondary' }}"></span>
      <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">
        {{ __('librarian.fines.pending_count') }}: {{ $pendingCount }}
      </span>
    </div>
    <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-none mb-6">
      {{ __('librarian.member.fines.title') }}
    </h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      {{ __('librarian.member.fines.subtitle') }}
    </p>
  </header>

  <section class="mb-10 grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
    <div class="bg-surface-container-lowest rounded-xl px-6 py-6">
      <p class="font-headline text-4xl {{ $pendingTotal > 0 ? 'text-error' : 'text-primary' }} leading-none mb-2">
        {{ number_format($pendingTotal, 0, ',', ' ') }} ₸
      </p>
      <p class="font-label text-[11px] text-on-surface-variant uppercase tracking-widest">{{ __('librarian.fines.pending_total') }}</p>
    </div>
    <div class="bg-surface-container-lowest rounded-xl px-6 py-6">
      <p class="font-headline text-4xl text-primary leading-none mb-2">{{ $fines->count() }}</p>
      <p class="font-label text-[11px] text-on-surface-variant uppercase tracking-widest">{{ __('librarian.fines.title') }}</p>
    </div>
  </section>

  <p class="mb-8 flex items-start gap-3 font-body text-sm text-on-surface-variant max-w-3xl">
    <span class="material-symbols-outlined text-[20px] mt-0.5">info</span>
    <span>{{ __('librarian.member.fines.read_only') }}</span>
  </p>

  @if ($fines->isEmpty())
    <div class="bg-surface-container-lowest rounded-xl p-10 text-center">
      <span class="material-symbols-outlined text-outline-variant text-5xl mb-4">verified</span>
      <p class="font-body text-base text-on-surface-variant">{{ __('librarian.member.fines.empty') }}</p>
    </div>
  @else
    <div class="bg-surface-container-lowest rounded-xl overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-[44rem]">
        <thead>
          <tr class="border-b border-outline-variant/20">
            <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest">{{ __('librarian.member.fines.related_book') }}</th>
            <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest">{{ __('librarian.fines.filters.reason') }}</th>
            <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest">{{ __('librarian.fines.charged_at') }}</th>
            <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest">{{ __('librarian.fines.resolved_at') }}</th>
            <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest text-right">{{ __('librarian.fines.amount') }}</th>
            <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest">{{ __('librarian.fines.filters.status') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($fines as $fine)
            @php $record = $fine->copy?->bibliographicRecord; @endphp
            <tr class="border-b border-outline-variant/10 last:border-0 {{ $fine->status === 'pending' ? 'bg-error-container/10' : '' }}">
              <td class="px-6 py-4">
                @if ($record !== null)
                  <a href="/book/{{ $record->isbn ?: $record->getKey() }}" class="font-body text-sm text-primary font-medium hover:text-secondary transition-colors">{{ $record->title }}</a>
                  <p class="font-body text-xs text-on-surface-variant">
                    {{ $record->primary_author ?: __('librarian.member.common.unknown_author') }}
                    @if ($fine->copy?->inventory_number)
                      · {{ $fine->copy->inventory_number }}
                    @endif
                  </p>
                @else
                  <span class="font-body text-sm text-on-surface-variant">{{ __('librarian.member.fines.no_related_book') }}</span>
                @endif
              </td>
              <td class="px-6 py-4 font-body text-sm text-on-surface">{{ __('librarian.fines.reasons.'.$fine->reason) }}</td>
              <td class="px-6 py-4 font-body text-sm text-on-surface-variant">{{ $fine->charged_at?->format('d.m.Y') ?? '—' }}</td>
              <td class="px-6 py-4 font-body text-sm text-on-surface-variant">{{ $fine->resolved_at?->format('d.m.Y') ?? '—' }}</td>
              <td class="px-6 py-4 font-body text-sm text-right">
                <strong class="{{ $fine->status === 'pending' ? 'text-error' : 'text-primary' }}">{{ number_format((float) $fine->amount, 0, ',', ' ') }} ₸</strong>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full font-label text-[11px] tracking-widest uppercase {{ $fine->status === 'pending' ? 'bg-error-container text-on-error-container' : 'bg-surface-variant text-on-surface-variant' }}">
                  {{ __('librarian.fines.statuses.'.$fine->status) }}
                </span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
