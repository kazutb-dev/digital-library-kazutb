@php
    $children = $node->relationLoaded('children') ? $node->children : collect();
@endphp

<li class="discover-canonical__udc-node" data-udc-node="{{ $node->code }}">
    @if ($children->isNotEmpty())
        <details @if ($depth === 0) open @endif>
            <summary>
                <span class="discover-canonical__udc-code">УДК {{ $node->code }}</span>
                <span class="discover-canonical__udc-node-title">{{ $node->localizedDescription() }}</span>
                <span class="discover-canonical__udc-count">{{ number_format((int) $node->getAttribute('records_count'), 0, ',', ' ') }} записей</span>
            </summary>
            <a
                class="discover-canonical__udc-open"
                href="{{ $withLang('/catalog', ['udc' => $node->code]) }}"
                data-test-id="discover-canonical-udc-{{ $node->code }}"
            >
                Открыть раздел в каталоге
            </a>
            <ul>
                @foreach ($children as $child)
                    @include('discover._udc-node', ['node' => $child, 'depth' => $depth + 1])
                @endforeach
            </ul>
        </details>
    @else
        <a
            class="discover-canonical__udc-leaf"
            href="{{ $withLang('/catalog', ['udc' => $node->code]) }}"
            data-test-id="discover-canonical-udc-{{ $node->code }}"
        >
            <span class="discover-canonical__udc-code">УДК {{ $node->code }}</span>
            <span class="discover-canonical__udc-node-title">{{ $node->localizedDescription() }}</span>
            <span class="discover-canonical__udc-count">{{ number_format((int) $node->getAttribute('records_count'), 0, ',', ' ') }} записей</span>
        </a>
    @endif
</li>
