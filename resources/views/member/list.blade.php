@extends('layouts.member', ['title' => __('librarian.member.shortlist.title').' — '.__('common.app_name')])

@section('content')
  @include('member.partials.flash')

  <header class="mb-10 md:mb-12">
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
      <span class="w-2 h-2 rounded-full bg-secondary"></span>
      <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">
        {{ __('librarian.member.shortlist.count', ['count' => $shortlistItems->count()]) }}
      </span>
    </div>
    <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-tight mb-4">
      {{ __('librarian.member.shortlist.title') }}
    </h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      {{ __('librarian.member.shortlist.subtitle') }}
    </p>
    @if (! empty($draft['title']))
      <p class="mt-4 font-headline text-xl text-primary">{{ $draft['title'] }}</p>
    @endif
    @if (! empty($draft['notes']))
      <p class="mt-1 font-body text-sm text-on-surface-variant max-w-2xl">{{ $draft['notes'] }}</p>
    @endif
  </header>

  @if ($shortlistItems->isEmpty())
    <div class="bg-surface-container-lowest rounded-xl p-10 md:p-14 text-center">
      <span class="material-symbols-outlined text-outline-variant text-5xl mb-4">bookmark</span>
      <h2 class="font-headline text-2xl text-primary mb-3">{{ __('librarian.member.shortlist.empty_title') }}</h2>
      <p class="font-body text-base text-on-surface-variant max-w-xl mx-auto mb-8">{{ __('librarian.member.shortlist.empty_body') }}</p>
      <a href="/catalog" class="inline-flex items-center gap-2 bg-gradient-to-r from-primary to-primary-container text-on-primary font-body font-medium text-sm px-6 py-3 rounded-md hover:opacity-90 transition-opacity">
        <span class="material-symbols-outlined text-[18px]">menu_book</span>
        <span>{{ __('librarian.member.common.back_to_catalog') }}</span>
      </a>
    </div>
  @else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
      @foreach ($shortlistItems as $item)
        @php
          $record = $item['record'] ?? null;
          $type = $item['type'] ?? 'book';
          $addedAt = ! empty($item['addedAt']) ? \Illuminate\Support\Carbon::parse($item['addedAt']) : null;
          $canReserve = $record !== null && auth()->user()?->can('reservation.create');
        @endphp
        <article class="bg-surface-container-lowest rounded-xl p-6 flex flex-col">
          <div class="flex justify-between items-start mb-4">
            <div class="w-20 h-28 shrink-0 rounded-md bg-gradient-to-br from-primary-fixed to-primary-container flex items-center justify-center">
              <span class="material-symbols-outlined text-on-primary text-3xl">{{ $type === 'external_resource' ? 'travel_explore' : 'menu_book' }}</span>
            </div>
            <form method="POST" action="{{ route('member.list.remove', ['identifier' => $item['identifier']]) }}"
                  onsubmit="return confirm('{{ __('librarian.member.shortlist.remove_confirm') }}');">
              @csrf
              @method('DELETE')
              <button type="submit" class="text-on-surface-variant hover:text-error transition-colors" title="{{ __('librarian.member.shortlist.remove') }}" aria-label="{{ __('librarian.member.shortlist.remove') }}">
                <span class="material-symbols-outlined">bookmark_remove</span>
              </button>
            </form>
          </div>

          <div class="flex-1">
            <span class="text-xs text-secondary font-label uppercase tracking-widest mb-1 block">
              {{ __('librarian.member.shortlist.types.'.$type) }}
            </span>
            <h2 class="text-lg font-headline text-primary mb-1 leading-snug">
              @if ($record !== null)
                <a href="/book/{{ $record->isbn ?: $record->getKey() }}" class="hover:text-secondary transition-colors">{{ $item['title'] }}</a>
              @else
                {{ $item['title'] }}
              @endif
            </h2>
            <p class="text-on-surface-variant text-sm font-body">{{ $item['author'] ?: __('librarian.member.common.unknown_author') }}</p>
            <p class="text-on-surface-variant text-xs font-body mb-3">
              {{ $item['publisher'] ?: '—' }}@if (! empty($item['year'])), {{ $item['year'] }}@endif
            </p>
            @if ($addedAt !== null)
              <p class="font-label text-[11px] text-outline uppercase tracking-wider mb-3">
                {{ __('librarian.member.shortlist.added_at') }}: {{ $addedAt->format('d.m.Y') }}
              </p>
            @endif
            @if ($record !== null)
              <p class="font-body text-xs {{ ($item['available_copies'] ?? 0) > 0 ? 'text-secondary' : 'text-on-surface-variant' }} mb-4">
                {{ __('librarian.member.shortlist.available_copies', ['count' => $item['available_copies'] ?? 0]) }}
              </p>
            @else
              <p class="font-body text-xs text-on-surface-variant mb-4">{{ __('librarian.member.shortlist.not_in_catalog') }}</p>
            @endif
          </div>

          <div class="flex gap-3 mt-4 pt-4 border-t border-outline-variant/10">
            @if ($canReserve)
              <form method="POST" action="{{ route('member.reservations.store') }}" class="flex-1">
                @csrf
                <input type="hidden" name="bibliographic_record_id" value="{{ $record->getKey() }}" />
                <button type="submit" class="w-full py-2 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md font-semibold text-sm hover:opacity-90 transition-opacity">
                  {{ __('librarian.member.reserve.button') }}
                </button>
              </form>
            @elseif ($record !== null)
              <a href="/book/{{ $record->isbn ?: $record->getKey() }}" class="flex-1 py-2 text-center bg-transparent text-secondary rounded-md font-semibold text-sm border border-outline-variant/20 hover:bg-surface-variant transition-colors">
                {{ __('librarian.member.common.open_record') }}
              </a>
            @elseif (! empty($item['url']))
              <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="flex-1 py-2 text-center bg-transparent text-secondary rounded-md font-semibold text-sm border border-outline-variant/20 hover:bg-surface-variant transition-colors">
                {{ __('librarian.member.shortlist.open_resource') }}
              </a>
            @endif
          </div>
        </article>
      @endforeach

      <a href="/catalog" class="bg-surface-container-high rounded-xl p-8 border-l-4 border-secondary flex flex-col hover:bg-surface-container transition-colors">
        <div class="flex items-center gap-3 mb-6 text-primary">
          <span class="material-symbols-outlined text-2xl">search</span>
          <h2 class="text-xl font-headline">{{ __('librarian.member.dashboard.services.catalog') }}</h2>
        </div>
        <p class="text-sm font-body text-on-surface mb-6 leading-relaxed">
          {{ __('librarian.member.shortlist.empty_body') }}
        </p>
        <div class="mt-auto">
          <span class="text-secondary font-semibold text-sm flex items-center gap-2">
            <span>{{ __('librarian.member.common.back_to_catalog') }}</span>
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
          </span>
        </div>
      </a>
    </div>
  @endif
@endsection
